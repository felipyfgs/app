## Context

- Hoje: captura **somente NFS-e** via ADN (JSON, NSU, mTLS A1, vault). Catálogo `/docs` lista kinds comuns, mas só `NFSE` retorna dados.
- Meta: capturar **NF-e, eventos NF-e, CT-e, MDF-e** (e NFC-e no que for viável) via canais oficiais SEFAZ, persistir no mesmo vault/catálogo, operar no painel do escritório.
- Restrições estáveis (AGENTS / ADR 001): PFX só em memória; TLS ≥1.2 + hostname; nunca expor PFX/senha/PEM; tenancy `office_id`; cursor sem salto silencioso; sem cert em CI.

## Goals / Non-Goals

**Goals:**
1. Distribuição NF-e (DistDFe) com cursor por estabelecimento+ambiente, jobs justos, rate limit SEFAZ.
2. Manifestação do destinatário quando só há resumo.
3. CT-e e MDF-e no catálogo com captura dedicada.
4. Projeções e export multi-tipo na UI Documentos.
5. Interface de domínio estável (`SefazDistDfeClient`, etc.) com implementação própria auditável.

**Non-Goals:** emissão, DANFE/PDF, ADN NFS-e reescrito, scraping municipal, NFC-e como produto de PDV.

## Decisions

### D1 — Cliente próprio de DistDFe (não sped-nfe em runtime) — **confirmado por pesquisa**

- **Decisão:** interface `SefazDistDfeClient` + transporte SOAP 1.2/mTLS próprio (cURL/libcurl **PFX BLOB**, mesmo padrão `HttpAdnContributorClient`).
- **Referência:** `nfephp-org/sped-nfe` (`Tools::sefazDistDFe`, DistDFe.md, schemas) **somente** fixtures/dev — **não** `composer require` em produção.
- **Evidência (pesquisa 2026-07-14):** `sped-common` SoapCurl materializa **PEM em disco** e mantém verificação de peer frágil/`SSL_VERIFYPEER` inadequada — colide com ADR 001 e testes anti-PEM do monorepo.
- **Alternativa rejeitada:** sped-nfe runtime (emissão+captura), ACBr, SaaS com cert no provedor.

### D2 — Cursors multi-canal em tabela única

- Preferência: **`channel_sync_cursors`** (ou generalizar `sync_cursors`) com unique  
  `(office_id, establishment_id, environment, source, channel)`.
- Canais: `NFSE_ADN` | `NFE_DISTDFE` | `CTE_DISTDFE` | `MDFE_DISTDFE`.
- Campos: `last_nsu`, `max_nsu_seen` (SEFAZ), `status`, `next_sync_at`, `last_cstat` sanitizado, lock, falhas decode.
- NSU **nunca** compartilhado entre canais; início 0; avançar só após commit do lote.
- Serializar DistDFe por **root_cnpj** além de lock por estabelecimento (filiais da mesma base).

### D3 — Modelo de catálogo: índice unificado (Path A)

- Introduzir `catalog_documents` (ou renomear projeção genérica) com: `office_id`, `kind`, `source`, `access_key`, `number`, partes, `amount`, `competence`/`issued_at`, `status`, `dfe_document_id`, `fiscal_role`.
- Manter `nfse_notes` / eventos NFS-e; adicionar projeções `nfe_documents`, `cte_documents`, `mdfe_documents` **ou** colunas JSON por kind — preferência: **tabelas de projeção por família** + view/query unificada na API `/documents`.
- `dfe_documents` continua imutável (bytes + SHA-256 + vault).

### D4 — Ordem de entrega (refinada pela pesquisa)

| Fase | Escopo | Entrega |
|------|--------|---------|
| **P0** | Schema cursors multi-canal + flags + interfaces + filas Horizon | Fundação sem rede |
| **P1** | DistDFe NF-e (`nfeDistDFeInteresse`) + vault + projeção | `resNFe`/`procNFe`/eventos no `/docs` |
| **P1.1** | Ops: cStat 137/138/656, métricas, inbox, coexistência ADN | Produção segura |
| **P2** | MD-e: ciência (e conclusivas manuais) + reconsulta `procNFe` | XML completo de entrada |
| **P2.1** | Export ZIP multi-kind | Entrega ao escritório |
| **P3** | `CTeDistribuicaoDFe` / `cteDistDFeInteresse` | kind `CTE` |
| **P4** | `MDFeDistribuicaoDFe` / `mdfeDistDFeInteresse` | kind `MDFE` opt-in |
| **P5** | NFC-e: **gap documentado** (MAY); polish UI | `NFCE` sem captura falsa |

**NFC-e:** DistDFe de entrada B2B **não** é o canal clássico do modelo 65; manter empty state honesto.

### D5 — Rate limit e job fairness (SEFAZ) — **números oficiais/operacionais**

- Loop: ≥ **2 s** entre páginas DistDFe.
- Após **137** ou `ultNSU == maxNSU`: ≥ **1 h** quiet (mesmo CNPJ/canal).
- **656** consumo indevido: `BLOCKED`/`BACKOFF ≥1h`; **não** retry imediato; inbox; consultas durante bloqueio reiniciam timer.
- Preferir só `distNSU` no agendamento; `consNSU`/`consChNFe` limitados (~20/h) — suporte pontual.
- Máx. **12–20** páginas/job × até **50** docs; requeue.
- Filas Horizon separadas: `sync-sefaz-nfe`, `manifest-nfe`, `sync-sefaz-cte`, `sync-sefaz-mdfe` (não competir com ADN/export).
- Onboarding: **um dono** de DistDFe por A1 (desligar ERP concorrente).

### D6 — Certificado e tenancy

- Mesmo A1 por **raiz** do cliente (8 primeiros do CNPJ); DistDFe autentica pela base do cert.
- Consulta com CNPJ do estabelecimento (regra SEFAZ: base do cert = base consultada).
- Nunca confiar `office_id` do cliente HTTP.

### D7 — Manifestação (códigos e prazos da pesquisa)

| tpEvento | Nome | Uso no produto |
|----------|------|----------------|
| **210210** | Ciência | Default para **abrir XML**; opcional antes de conclusiva |
| **210200** | Confirmação | Conclusiva; bloqueia cancelamento do emitente |
| **210220** | Desconhecimento | Conclusiva; sempre manual (ou 2ª aprovação) |
| **210240** | Operação não realizada | Conclusiva + `xJust` obrigatório |

- WS: **NFeRecepcaoEvento4** (Ambiente Nacional, `cOrgao` 91).
- Prazos: ciência **10 dias** da autorização; conclusiva **90 dias** (SINIEF 14/2026 desde 01/06/2026); silêncio → **confirmação automática** do fisco.
- Ciência **não** é obrigatória antes da confirmação; ciência **após** conclusiva → rejeição 655.
- Desconhecimento **não** é liberador listado de `procNFe` no MOC — se precisar do XML, **Ciência primeiro**.
- UX: automação só de **Ciência** (opt-in por cliente); conclusivas human-in-the-loop.
- Pós-evento: job assíncrono reconsulta DistDFe (NSU ou `consChNFe`); `procNFe` **não** é síncrono.
- Certificado **do destinatário (cliente)** — nunca do escritório.

### D8 — Parsers

- Versionados por schema (`procNFe`, `resNFe`, `procEventoNFe`, CT-e, MDF-e).
- XML bem-formado desconhecido: guardar + `parse_status=REVIEW`; não bloquear NSU por XSD novo (igual NFS-e).

### D9 — Frontend

- Filtro kind: kinds com `capture_available=true` após cada fase.
- Empty state só para kinds ainda não ligados.
- Tela de manifestação no detalhe do documento (quando `status` indica resumo pendente).
- Syncs/health: cursors SEFAZ visíveis.

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Bloqueio SEFAZ 656 | Backoff ≥1h, limite de loops, métricas, inbox |
| Resumo sem XML completo | Fluxo de manifestação P2 explícito na UI |
| Lib tentadora (sped-nfe) | Interface + testes anti-PEM/anti-TLS-off |
| Volume de XML / vault | Mesmo envelope crypto; retenção/backup já existentes |
| CT-e/MDF-e APIs distintas | Fases P3/P4; não forçar no mesmo SOAP de NF-e |
| NFC-e pouco útil no DistDFe de entrada | Spec marca MUST/MAY e gap documentado |

## Migration Plan

1. Schema: cursors SEFAZ + projeções + (opcional) `catalog_documents`.
2. Deploy client + jobs desligados por feature flag `SEFAZ_DISTDFE_ENABLED`.
3. Piloto: 1–3 raízes com A1 real (smoke restrito, fora do CI).
4. Habilitar captura por cliente/estabelecimento.
5. Rollback: desligar flag; cursors preservados; XML já no vault permanece.

## Open Questions

- ~~NFC-e~~ → **Resolvido:** gap/MAY; não MVP (pesquisa).
- ~~Tabela de cursors~~ → **Resolvido:** multi-canal com `channel` (preferência tabela única).
- ~~Ciência automática~~ → **Resolvido:** P2 manual; bulk ciência só após estabilidade (opt-in).
- Homologação DistDFe: usar para contrato/cert; volume real só em produção (smoke restrito).
- Obrigatoriedade legal de MD-e setorial (combustíveis etc.): política configurável por cliente/UF na P2+.

## Research state

- Levantamento completo em `research/sintese-tecnica.md` (2026-07-14).
- 5 frentes: DistDFe oficial · libs PHP · CT-e/MDF-e/NFC-e · MD-e · arquitetura multi-canal.
- Specs e tasks abaixo incorporam endpoints, cStat, tpEvento e priorização.
