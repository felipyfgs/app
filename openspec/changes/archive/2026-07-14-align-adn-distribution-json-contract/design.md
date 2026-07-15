## Context

O monorepo já implementa captura de NFS-e via ADN: cofre A1, `CurlMtlsTransport` (PFX em memória), job de sync por NSU, processador de página atômico, catálogo e exportação. O bloqueio atual não é de infraestrutura — é de **contrato wire**.

### Evidência empírica (dev, 2026-07-14)

- Base: `client_id=8`, `establishment_id=9`, CNPJ `34194865000158`, credencial ACTIVE com o mesmo fingerprint do PFX em `secrets/dev/`.
- `GET https://adn.nfse.gov.br/contribuintes/DFe/0?cnpjConsulta=34194865000158&lote=true` → HTTP 200, corpo **JSON** (~245 KB).
- Campos de topo: `StatusProcessamento`, `LoteDFe`, `Alertas`, `Erros`, `TipoAmbiente`, `VersaoAplicativo`, `DataHoraProcessamento`.
- Item de lote: `NSU`, `ChaveAcesso`, `TipoDocumento` (`NFSE` | `EVENTO`), `ArquivoXml` (Base64 + GZip do XML NFS-e/evento), `DataHoraGeracao`.
- Lote observado: 50 documentos por página; NSU 0 → 1–50; NSU 50 → 51–100 (inclui EVENTO); NSU alto → `NENHUM_DOCUMENTO_LOCALIZADO` com erro E2220.
- `TipoAmbiente` real do certificado: `PRODUCAO`.
- O parser atual espera XML `retDistDFeInt` / `cStat` 137|138 / `docZip` → `AdnInvalidResponseException`.

### Fontes oficiais e comunitárias consultadas

| Fonte | Achado relevante |
|-------|------------------|
| Manual Contribuintes APIs ADN v1.0 (12/02/2026, gov.br) | Métodos `GET /DFe/{NSU}` e `GET /NFSe/{ChaveAcesso}/Eventos`; `cnpjConsulta` com mesma raiz do A1; Swagger produção restrita |
| Swagger restrita | `https://adn.producaorestrita.nfse.gov.br/contribuintes/docs/index.html` |
| Fórum ACBr / integradores | Path correto é `/contribuintes` (com **s**), não `/contribuinte` |
| Smoke local | Contrato JSON acima; envelope XML das fixtures **não** aparece na API real |

### Dependências PHP / nfephp (estado do projeto)

| Pacote | Situação | Conclusão |
|--------|----------|-----------|
| `nfephp-org/sped-common` (^5.1) | Já instalado; usado só em `PfxReader` | **Manter** para metadados A1 |
| `nfephp-org/sped-common` SoapCurl | Grava PEM em tempdir; pode desligar `SSL_VERIFY*` | **Não** usar para ADN |
| `nfephp-org/sped-nfse*` / `sped-nfse-nacional` | Emissão municipal/nacional (RPS), não cliente ADN de distribuição maduro | Fora de escopo MVP |
| `Focus599Dev/sped-nfs-nacional` (e forks “nfse-nacional”) | Conhece JSON/`StatusProcessamento`/`LoteDFe`, mas mTLS via PEM temporário e `SSL_VERIFYHOST/PEER=0`; path de eventos no README diverge do manual (`/DFe/.../Eventos` vs `/NFSe/.../Eventos`) | **Não** adicionar como runtime; usar só como **referência de shape** |
| SDKs de emissão (pedrocasado, librecode, etc.) | Foco SEFIN/emissão | Fora de captura |

Isso reforça o **ADR 001**: cliente próprio + `sped-common` só para PFX.

## Goals / Non-Goals

**Goals:**

- Fazer a distribuição ADN funcionar de ponta a ponta com o envelope JSON oficial.
- Preservar garantias de domínio: mTLS com PFX em memória, página atômica, sem salto de NSU, decode falho não avança, rate limit e job justos.
- Normalizar status JSON para o DTO interno sem acoplar o job ao JSON bruto.
- Atualizar fixtures e testes de contrato; smoke documentado no cliente piloto 8 (manual/dev).
- Manter `events(accessKey)` alinhado ao path oficial do manual (`/NFSe/{chave}/Eventos`) com parse JSON se a API real for JSON.

**Non-Goals:**

- Adotar SDK comunitário ADN.
- Emitir/cancelar NFS-e, DANFSe, portal cliente.
- Override de NSU na UI; carga 1000+ nesta change.
- Reprocessar o histórico de fixtures XML legadas como se fossem oficiais.

## Decisions

### 1. Corrigir o parser JSON no cliente próprio (não trocar de stack)

**Decisão:** Estender `HttpAdnContributorClient` para detectar e parsear o envelope JSON da distribuição; mapear para `DistributionPageDto` / `DistributionDocumentDto`.

**Alternativas rejeitadas:**

- Adotar `Focus599Dev/sped-nfs-nacional` ou similar → viola PFX-em-memória e TLS verify (ADR 001 / AGENTS.md).
- Manter dual XML+JSON “por compatibilidade” com fixtures inventadas → confunde testes e não reflete o ADN real; fixtures XML legadas saem ou ficam arquivadas como “incorretas”.

### 2. Mapeamento de status e paginação

| JSON `StatusProcessamento` | Comportamento interno |
|----------------------------|------------------------|
| `DOCUMENTOS_LOCALIZADOS` | Página com 1+ docs; `ultimoNsu` = max `NSU` do lote; `hasMore` = true se lote não vazio e (tamanho do lote ≥ limiar configurável **ou** status indica continuidade — ver abaixo) |
| `NENHUM_DOCUMENTO_LOCALIZADO` | Zero docs; **não** avança NSU além do cursor atual; `hasMore` = false; próximo ciclo horário |
| `REJEICAO` | Erro permanente ou tratável conforme códigos em `Erros[]` (sanitizados); não avançar NSU |

**Semântica de `maxNsu` no DTO:** o JSON real **não** envia `maxNSU`. Para não reescrever todo o job:

- `ultimoNsu` = maior NSU do lote (ou `lastNsu` solicitado se vazio e status “nenhum”).
- `maxNsu` = `ultimoNsu` quando `hasMore=false`; quando `hasMore=true`, `maxNsu` = `ultimoNsu` (sinaliza “ainda há trabalho” via flag `hasMore`, não via max).
- `hasMore`: `true` se status = `DOCUMENTOS_LOCALIZADOS` **e** `count(LoteDFe) > 0`. (Smoke mostrou páginas cheias de 50; se a última página real vier com N&lt;50 ainda com status “localizados”, uma chamada extra deve retornar `NENHUM_DOCUMENTO_LOCALIZADO` — aceitável e seguro.)  
  Alternativa mais agressiva (só hasMore se count==50) arrisca parar cedo se o ADN mudar o tamanho do lote; preferimos a regra por **status**.

**Consulta:** `GET /DFe/{lastNsu}` com `cnpjConsulta` = CNPJ do estabelecimento e `lote=true` (já implementado). NSU informado é o **último consumido**; o ADN devolve documentos **posteriores**.

### 3. Documento no lote → DTO interno

| Campo JSON | Uso |
|------------|-----|
| `NSU` | Cursor / `document_interests.nsu` |
| `TipoDocumento` | `NFSE` → `AdnDocumentType::Nfse`; `EVENTO` → `Event`; outros → `Unknown` |
| `ArquivoXml` | Entrada de `DocumentDecoder::decodeBase64Gzip` (já existe e funciona no smoke) |
| `ChaveAcesso` | Hint para projeção / validação pós-parse (não substitui parse do XML) |
| `DataHoraGeracao` | Metadado opcional de auditoria interna; não altera imutabilidade do XML |

O XML descompactado continua a fonte de verdade da projeção (`NfseXmlParser`).

### 4. Campo `rawXml` do DTO

**Decisão:** Renomear semanticamente para payload bruto da resposta de rede (JSON string) ou manter propriedade `rawXml` preenchida com o corpo bruto **somente em memória de debug/teste**, nunca em log estruturado completo. Preferível: renomear no DTO para `rawBody` no design de implementação (ou manter nome legado com comentário) sem persistir o body bruto no banco.

### 5. Eventos por chave

**Decisão:** Manter path `GET /NFSe/{chave}/Eventos` (manual oficial). Parsear JSON se a resposta for JSON (mesmo padrão de status/lote se aplicável). Não bloquear o backfill: eventos já chegam no `LoteDFe` com `TipoDocumento=EVENTO`.

### 6. Fixtures e testes

- Substituir `distribution_*.xml` de envelope por `distribution_*.json` sanitizados (NSUs baixos, chaves fictícias, `ArquivoXml` com payload de teste já existentes em `.b64`).
- Testes de contrato: recusar `verify_tls=false`; recusar gravação de PEM; aceitar JSON real shape; rejeitar envelope XML legado se ainda chegar.
- Feature de sync: mock do `AdnContributorClient` continua; atualizar stubs para status string oficiais.

### 7. Base de desenvolvimento

| Entidade | ID / valor |
|----------|------------|
| Cliente piloto | `8` — S. E. L. DE SOUZA SUARES VEICULOS |
| Estabelecimento | `9` — `34194865000158` |
| Credencial | `7` ACTIVE |
| PFX local | `secrets/dev/sel-de-souza-suares-veiculos-34194865000158.pfx` (gitignored) |
| Smoke | Comando/runbook manual; **nunca** no CI |

### 8. Configuração

- `ADN_BASE_URL` default e env não vazio: `https://adn.nfse.gov.br/contribuintes` (já corrigido no ambiente local).
- Opcional: `ADN_LOTE_SIZE_HINT=50` só para métricas; **não** usar como única regra de hasMore.
- Produção restrita: base `https://adn.producaorestrita.nfse.gov.br/contribuintes` quando `ADN_ENVIRONMENT` indicar.

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Manual oficial v1.0 tem só 3 páginas e omite schema JSON | Fixtures + testes baseados no smoke real; versionar shape no repositório |
| Tamanho do lote mudar (≠50) | `hasMore` por status, não por constante 50 |
| `ArquivoXml` ocasionalmente raw XML sem gzip | `DocumentDecoder` pode tentar gzip e, se falhar com payload já `<?xml`, aceitar bytes se bem-formados **somente** após evidência; default continua Base64+GZip (comportamento atual e observado) |
| REJEICAO com códigos não catalogados | Tratar como permanente sanitizado; bloquear cursor sem avançar NSU |
| Biblioteca comunitária “parece pronta” | ADR 001 + testes que falham se PEM for escrito ou TLS verify off |
| Backfill grande no cliente piloto | Limite 20 páginas/job + requeue; rate 4 rps; observar Horizon |
| Dados reais em PRODUCAO no dev | Operar só cliente 8; não copiar XML para git; vault cifrado |

## Migration Plan

1. Merge do parser JSON + fixtures + testes verdes (sem dependência de A1 no CI).
2. Dev: smoke distribution + job sync no estabelecimento 9; verificar `nfse_notes` / `nfse_events` e avanço de NSU.
3. Confirmar download XML e export ZIP com documentos reais (amostra mínima).
4. Só então habilitar scheduler horário para esse estabelecimento em escala.
5. Rollback: reverter imagem/commit do cliente ADN; cursores e documentos já persistidos **permanecem** (imutáveis); NSU não regride automaticamente.

## Open Questions

Nenhuma bloqueante para iniciar implementação. Pontos a confirmar no smoke pós-parser:

1. Shape exato de `GET /NFSe/{chave}/Eventos` (JSON vs XML) — ajustar parse no mesmo PR se divergir.
2. Códigos em `Erros[]` além de E2220 que devam ser retryable vs permanent — catalogar conforme aparecerem no piloto, sem inventar lista fechada agora.
