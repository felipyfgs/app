# Descobertas: captura automática de XML de saída (MA)

**Change:** `build-ma-outbound-nfe-nfce-capture`  
**Atualizado:** 2026-07-15  
**Status:** NFC-e 65 por chave provada no portal SVRS; G4 desassistido permanece `NO_GO_M2M`

Este documento registra o que **já foi deduzido e validado** sobre descoberta de chave vs. obtenção do XML completo (`procNFe`). Serve como base para não reabrir caminhos impossíveis e para planejar o recovery automático.

---

## 1. Resumo executivo

| Capacidade | Automático? | Canal / evidência |
|------------|-------------|-------------------|
| Descobrir chave de acesso a partir de `nNF` (série monitorada) | **Sim** | `NFeConsultaProtocolo` (read-only) |
| Baixar **XML completo** de NFC-e 65 por chave + A1 | **Sim, tecnicamente** | Portal oficial SVRS; smoke mTLS validado |
| Baixar XML por webservice/API M2M documentado | **Não encontrado** | DistDFe/consulta não entregam; portal não publica contrato M2M |
| Guardar `procNFe` no vault e projetar catálogo `OUT` | **Sim, quando o XML chega** | Pacote MA assistido / import ERP |
| Recuperação M2M SEFAZ-MA | **Bloqueado** | Sem contrato formal → G4 `NO_GO_M2M` |

**Conclusão de produto:** o motor de sequência por `nNF` **não** é, sozinho, um “download de XML”. Porém, para NFC-e 65 do MA, foi provada a cadeia técnica `nNF -> chave -> nfeProc` usando o portal oficial da SVRS e A1 do emitente. O uso desassistido desse formulário ainda não possui contrato/autorização documentada e continua desligado. Evidência completa: [`svrs-nfce-downloadxml-dfe-research.md`](svrs-nfce-downloadxml-dfe-research.md).

---

## 2. Pipeline atual (implementado)

```text
SEED (procNFe semente)
  → CONSULT_QUEUED / consulta protocolo por nNF
  → KEY_DISCOVERED (chave 44 validada)
  → XML_PENDING  (+ MaOutboundRetrievalRequest modo ASSISTED)
  → [humano sobe ZIP/XML do portal MA ou import]
  → XML_CAPTURED / COMPLETE
```

Pontos de código relevantes:

- Reconciliação e abertura de pendência assistida: `OutboundSequenceReconciler::openAssistedRetrievalPending`
- Cliente M2M desligado: `DisabledMaOutboundXmlRetrievalClient` → status `NO_GO_M2M`
- Interface pronta para G4: `MaOutboundXmlRetrievalClient` (`requestExport` / `poll` / `download`)
- Ingestão estrita de pacote: `MaOfficialPackageIngestionService` + `MaOfficialPackageValidator` (só `procNFe` com protocolo)
- Flags: `SEFAZ_MA_OUTBOUND_ENABLED`, `SEFAZ_MA_PROTOCOL_QUERY_ENABLED`, `SEFAZ_MA_M2M_RETRIEVAL_ENABLED` (off), `SEFAZ_MA_MUTATING_PROBE_ENABLED` (off)

Estados de número (não confundir descoberta com captura):

```text
KEY_DISCOVERED ≠ XML_CAPTURED
```

---

## 3. O que a consulta de protocolo devolve (e o que não)

### 3.1 Comportamento observado / desenhado

| cStat / caso | Interpretação no motor | XML completo? |
|--------------|------------------------|---------------|
| **562** com `chNFe` (XML ou `xMotivo`) | Chave descoberta se DV/UF/emitente/modelo/série/`nNF` batem | Não |
| **613** com chave em colchetes no `xMotivo` | Parser prefere chave `[44]` — aceita como descoberta quando válida | Não |
| **100** (autorizada na chave candidata) | Candidata confirmada / descoberta conforme parser | Só `protNFe` na resposta — **não** é `procNFe` de guarda |
| **217** | Número não localizado → `GAP_PENDING` / retry 12h | Não |
| **656** | Uso indevido → circuit breaker / bloqueio série | Não |
| **562/613 sem chave suficiente** | `LIMITED_NO_KEY` — **sem** força bruta de `cNF` | Não |

### 3.2 Endpoints MA

| Modelo | Autorizador de consulta | Observação |
|--------|-------------------------|------------|
| **55** (NF-e) | SVAN | mTLS A1 da raiz do cliente |
| **65** (NFC-e) | SVRS | mTLS A1; CSC **não** entra na consulta 562 |

### 3.3 Deduzido e proibido

- Remontar `procNFe` juntando resposta da consulta + protocolo → **proibido** (design D2): não é XML original de guarda.
- CSC **não** é canal de download de XML (só eventual fallback ativo 65, se aprovado — fora do caminho read-only).

---

## 4. Por que DistDFe não fecha o gap de saída do emitente

Evidência normativa + comportamento esperado (já na change e em `nfce-capture-gap.md` / design autXML):

| Fato | cStat / regra | Consequência |
|------|---------------|--------------|
| DistDFe **não** entrega a NF-e ao **próprio emitente** | **641** — *NF-e indisponível para o emitente* | A1 do cliente emitente **não** baixa a própria emissão |
| DistDFe `consChNFe` só modelo **55** | **618** se modelo ≠ 55 | **NFC-e 65** não entra por DistDFe nacional |
| DistDFe por NSU é canal de **interesse** (destinatário / autXML), não de reconciliação por `nNF` | — | Cursor NSU **não** se confunde com `outbound_series_cursors` |

Smoke/observação de produto (sessão piloto): tentativa de DistDFe por chave de NFC-e descoberta → alinhado a **618** (modelo ≠ 55).

---

## 5. SEFAZ-MA e recuperação de pacote

| Item | Status documentado |
|------|--------------------|
| Plataforma autenticada de pacotes por competência (OUT, NF-e e NFC-e) | Existe no domínio público / notícias; operador obtém ZIP |
| Contrato / API **M2M** estável documentada no repositório | **Ausente** |
| Decisão G4 | **`NO_GO_M2M`** — ver `ma-outbound-g4-g5-decision.md` e `ma-outbound-sefaz-ma-status.md` |
| RPA / scraping / cookie Gov.br / SEFAZNET | **Proibido** (spec + design) |
| Modo atual de produto | **`ASSISTED`**: operador sobe pacote oficial; sistema valida e fecha `XML_PENDING` |

Perguntas ainda abertas à SEFAZ-MA (ofício): pacote idêntico para contador; `procNFe` sempre original (incl. cancelados); autorização escrita M2M.

### 5.1 Evidência pública da HubStrom (XMLHub)

Inspeção estática realizada em 2026-07-15 nos bundles JavaScript públicos do
`app.hubstrom.com`, sem acessar dados autenticados de cliente:

- [`XmlBatchConfiguration.f395fc9797e406c784bd.js`](https://app.hubstrom.com/XmlBatchConfiguration.f395fc9797e406c784bd.js)
- [`Login.3c9d3071282c5dbaeae1.js`](https://app.hubstrom.com/Login.3c9d3071282c5dbaeae1.js)

O frontend público confirma o seguinte onboarding para saída:

1. O usuário envia um `procNFe` de saída recente (até 60 dias), do próprio
   emitente, para cadastrar modelo, série e dados-base da última nota.
2. NF-e e NFC-e são configuradas separadamente. Para NFC-e, o formulário também
   exige CSC e ID do CSC; o certificado A1 do cliente precisa estar válido.
3. A configuração é gravada em uma fila proprietária da HubStrom e dispara um
   backend privado em Firebase/Cloud Functions. Isso **não** constitui contrato
   público M2M da SEFAZ.
4. A cobertura exposta pelo bundle inclui **MA para NFC-e 65**, mas não inclui MA
   na lista de emissão técnica de **NF-e 55**.
5. A função “Baixar por chave” do frontend valida e enfileira somente chaves do
   modelo **55**; ela não oferece esse caminho para NFC-e 65.
6. A interface informa que, nos estados cobertos, é emitido mensalmente um
   documento de valor zero e cancelado após autorização. No caso de NFC-e, o
   tooltip descreve a finalidade como manutenção/validação da integração e da
   série.

Limite da evidência: o bundle prova configuração, filas e a existência da
emissão/cancelamento técnico, mas **não expõe o algoritmo servidor que recupera
o `procNFe` histórico**. Logo, não é válido concluir que a nota de valor zero,
por si só, baixa XMLs antigos. A tela de lacunas mostra novas tentativas a cada
12 horas, mas a origem final dos bytes permanece proprietária.

Consequência para este projeto: o que pode ser reproduzido com segurança é o
onboarding por XML-semente/A1/CSC, a reconciliação por série e a fila de
pendências. Copiar a emissão/cancelamento para produção não elimina a necessidade
de uma fonte de `procNFe` e continua sujeito aos gates G5.

### 5.2 Ressalva sobre a base legal exibida pela HubStrom

O frontend associa a emissão mensal ao art. 1º do Ato COTEPE/ICMS 33/2008.
Entretanto, a descrição oficial do Portal Nacional da NF-e informa que esse ato
trata de **prazo de cancelamento** e de transmissão de NF-e emitida em
contingência. Ele não foi identificado como autorização para emitir nota de
valor zero com finalidade de validação técnica.

Portanto, antes de qualquer reprodução do comportamento mutante, exigir parecer
fiscal/jurídico específico para MA, modelo 55/65, finalidade, CFOP/NCM/tributação,
série exclusiva, escrituração e cancelamento. A alegação comercial da HubStrom
não substitui esse parecer.

---

## 6. Smoke / evidências de ambiente (piloto)

Registro operacional (ambiente local com flags MA ligadas; não substituir gates formais G2/G3):

| Item | Resultado |
|------|-----------|
| Perfil ACTIVE + allowlist + A1 | OK |
| CSC vault + exibição ADMIN (metadados/valor sob política de UI) | OK sob override de produto; **API M2M/auditoria** continuam sem vazar PFX/PEM |
| Semente `procNFe` (upload) + avanço de posição `nNF` | OK |
| Consulta automática avançando série (ex.: seed 160 → descoberta em 161) | OK (read-only) |
| Números inexistentes (217) em faixas adjacentes | Tratados como gap/retry, sem mutação |
| XML completo só com consulta de protocolo | **Não obtido** |
| XML completo NFC-e por chave + A1 no portal SVRS | **Obtido e validado** (`nfeProc`, protocolo 100, digest e assinatura válidos) |
| DistDFe chave NFC-e | Não serve como fonte de full XML de saída |

Detalhes de gates formais: `ma-outbound-poc-gates.md`.

---

## 7. Fontes reais de XML completo (matriz)

| Fonte | Modelos | Automático? | Pré-requisito | Proveniência típica |
|-------|---------|-------------|---------------|---------------------|
| **Pacote oficial SEFAZ-MA (ASSISTED)** | 55 + 65 OUT | Não (humano no portal) | Operador + ZIP válido | `SEFAZ_MA_PORTAL_PACKAGE` |
| **M2M SEFAZ-MA (G4)** | 55 + 65 OUT | Sim, se contrato | Ofício + flag + adapter | Futuro, via `MaOutboundXmlRetrievalClient` |
| **Portal SVRS por chave + A1** | **65 OUT** | Tecnicamente sim; não aprovado para produção | Chave conhecida + A1 relacionado | Futuro candidato; wrapper HTML/JS sem contrato M2M |
| **Import / push ERP-PDV** | 55 + 65 OUT | Sim, se integrado | Emissor entrega `procNFe` | `IMPORT_XML` / canal outbound package |
| **autXML + DistDFe do escritório** | **só 55** | Sim, emissão **futura** | CNPJ do escritório em `autXML` + A1/cursor office | Change `add-office-autxml-and-bulk-xml-import` |
| **Consulta protocolo** | 55 + 65 | Só chave/situação | A1 | **Não** é fonte de guarda |
| **DistDFe A1 emitente** | — | Não para própria nota | — | cStat 641 |

---

## 8. Ajuste de arquitetura deduzido (ainda não implementado como orquestrador)

Ordem lógica de recovery após `KEY_DISCOVERED` / `XML_PENDING`:

```text
OutboundXmlRecovery (proposto)
  1. Match em pacote MA já ingerido / lote assistido
  2. Inbox ERP/PDV (push ou pull) por chave
  3. Portal SVRS por chave (somente 65 e somente após decisão formal)
  4. M2M MA (somente se G4 GO)
  5. Stream autXML escritório (somente 55 + enrollment)
  6. Mantém XML_PENDING + item de inbox — sem afirmar "capturado"
```

Regras:

- Nunca promover resposta de consulta a documento de guarda.
- Nunca Selenium/RPA. Se aprovado, usar adapter HTTP+mTLS isolado e parser estrito.
- `capture_mode=ASSISTED|AUTOMATIC` deve refletir fonte real (`ma-outbound-xml-retrieval` spec).
- Upload manual vira **fallback de contingência**, não o único caminho de produto — desde que exista fonte A–D acima.

---

## 9. Relação com outras decisions / docs

| Documento | Papel |
|-----------|--------|
| `ma-outbound-g0-decision.md` | Segurança / flags off baseline |
| `ma-outbound-g4-g5-decision.md` | M2M NO_GO; mutação off |
| `ma-outbound-sefaz-ma-status.md` | Pedidos formais à SEFAZ-MA |
| `ma-outbound-runbooks.md` | Operação assistida e consulta 562 |
| `nfce-capture-gap.md` | Por que NFC-e não usa DistDFe |
| `document-coverage-matrix.md` | Cobertura kind × direction (atualizar com MA) |
| OpenSpec design `build-ma-outbound-nfe-nfce-capture` | D1–D9, gates G0–G5 |
| OpenSpec `add-office-autxml-and-bulk-xml-import` | Saída 55 via terceiro autorizado |

---

## 10. Decisões de produto já tomadas (não reabrir sem evidência nova)

1. **Manual-only como única fonte permanente de XML é inaceitável** para o objetivo de captura de saídas — precisa de ERP, M2M ou autXML (55).
2. **Não** prometer “download automático SEFAZ” enquanto o uso desassistido do formulário SVRS não tiver decisão formal.
3. **Não** usar DistDFe do emitente nem inventar DistDFe de NFC-e.
4. **Não** sintetizar `procNFe` a partir de `retConsSitNFe`.
5. Descoberta por `nNF` + consulta permanece valiosa para **lacunas, auditoria de numeração e fila de recovery** — mesmo sem XML imediato.

---

## 11. Próximos passos sugeridos (fora deste registro)

1. Solicitar posição formal da SVRS/SEFAZ-MA sobre automação e limites do `DownloadXMLDFe`.
2. Propor OpenSpec delta para adapter SVRS 65, orquestrador `OutboundXmlRecovery` e estados honestos na UI.
3. Atualizar este arquivo quando houver resposta formal da SEFAZ-MA ou smoke G2/G3 assinado.
