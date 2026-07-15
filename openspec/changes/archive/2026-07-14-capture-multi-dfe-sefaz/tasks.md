## 0. Pesquisa (concluída 2026-07-14)

- [x] 0.1 Levantamento DistDFe SEFAZ (endpoints, cStat, NSU, rate limit)
- [x] 0.2 Levantamento sped-nfe/libs (PEM/TLS → cliente próprio)
- [x] 0.3 Levantamento CT-e/MDF-e/NFC-e (canais e priorização)
- [x] 0.4 Levantamento MD-e (tpEvento, prazos 10d/90d, UX)
- [x] 0.5 Levantamento arquitetura multi-canal
- [x] 0.6 Síntese em `research/sintese-tecnica.md` + refinamentos design/specs

## 1. Fundação de domínio e schema (P0)

- [x] 1.1 Definir enums/canais: `NFE_DISTDFE`, `CTE_DISTDFE`, `MDFE_DISTDFE`, `NFSE_ADN`; `DocumentKind.captureAvailable` por feature flag
- [x] 1.2 Migration: `channel_sync_cursors` com unique (office, establishment, environment, source, channel) + last_nsu, max_nsu_seen, last_cstat, next_sync_at
- [x] 1.3 Migration: projeções NFE (resumo vs completo, manifestation_status) + índices office/access_key/kind
- [x] 1.4 Interfaces: `SefazDistDfeClient`, DTOs distNSU/retDistDFeInt/docZip/cStat 137|138|656; binding
- [x] 1.5 Config `config/sefaz.php` (URLs AN prod/hom, timeouts, 2s loop, 1h quiet, max pages, flags off)
- [x] 1.6 Filas Horizon: `sync-sefaz-nfe`, `manifest-nfe` (e stubs cte/mdfe)
- [x] 1.7 Testes decode Base64+GZip + classificação schema (fixtures sanitizadas estilo DistDFe.md)
- [x] 1.8 Testes de segurança: falham se PEM em disco ou SSL_VERIFYPEER off

## 2. Fase P1 — DistDFe NF-e

- [x] 2.1 Implementar transporte SOAP/mTLS próprio (PFX BLOB, TLS ≥1.2, hostname) sem sped-nfe em runtime
- [x] 2.2 `sefazDistDFe(ultNSU)` → parse `retDistDFeInt` → DTO; mapear cStat 137/138/656
- [x] 2.3 Job `SyncSefazDistDfeJob`: lock, loop com sleep ≥2s, max iterações, requeue
- [x] 2.4 Processor: persistir `dfe_documents` + interesse + projeção NFE/resumo/evento; avançar NSU só após commit
- [x] 2.5 Scheduler: `sefaz:dispatch-due-syncs` a cada minuto + SefazSyncDispatchService
- [x] 2.6 API listagem `/documents?kind=NFE` com dados reais; serialização kind/source
- [x] 2.7 Feature tests: listagem NFE; kind sem captura vazio (processador 137/656 cobertos no unit parser + processor)
- [x] 2.8 Marcar `DocumentKind::Nfe` capture_available quando flag on; UI kinds atualizados

## 3. Fase P2 — Manifestação do destinatário

- [x] 3.1 Cliente de registro de evento de manifestação (ciência, confirmação, desconhecimento, não realizada)
- [x] 3.2 Endpoint API OPERATOR/ADMIN + policy; auditoria
- [x] 3.3 UI no detalhe Documentos: ações de manifestação com confirmação
- [x] 3.4 Pós-sucesso: reconsulta DistDFe / obtenção procNFe e atualização da projeção
- [x] 3.5 Testes: 403 VIEWER; happy path com fixtures; sem vazamento de cert

## 4. Fase P3 — CT-e

- [x] 4.1 Interface + client CT-e (distribuição/consulta oficial) com mTLS e cursor próprio
- [x] 4.2 Job + processor + projeção `kind=CTE`
- [x] 4.3 Parser CT-e versionado (campos de catálogo)
- [x] 4.4 Listagem/filtro CTE na API e UI; testes de contrato

## 5. Fase P4 — MDF-e

- [x] 5.1 Interface + client MDF-e com mTLS e cursor próprio
- [x] 5.2 Job + processor + projeção `kind=MDFE`
- [x] 5.3 Parser MDF-e versionado
- [x] 5.4 Listagem/filtro MDFE na API e UI; testes

## 6. Fase P5 — NFC-e, export e ops

- [x] 6.1 Avaliar canal NFC-e; implementar captura se viável ou documentar gap e manter MAY da spec
- [x] 6.2 Export ZIP multi-kind (prefixo/pasta por kind); filtros kind no job de export
- [x] 6.3 Inbox/health: itens para cursors SEFAZ BLOCKED/656/decode
- [x] 6.4 Elegibilidade por canal no cadastro do cliente (API + UI resumo)
- [x] 6.5 Coexistência: testes de que bloqueio DistDFe não para ADN

## 7. Segurança, ops e piloto

- [x] 7.1 Testes que falham se PEM for materializado em disco ou TLS verify for desligado
- [x] 7.2 Runbook smoke restrito (produção) com A1 piloto — fora do CI
- [x] 7.3 Feature flags por canal; default off até smoke
- [x] 7.4 Atualizar docs/ops e CONTEXT se necessário no archive/sync
- [x] 7.5 Validar change OpenSpec (`openspec validate` quando disponível)

## 8. Frontend polimento multi-tipo

- [x] 8.1 Insights/contagens por kind (quando API expuser)
- [x] 8.2 Detalhe por kind (campos específicos NFE/CTE/MDFE além do layout comum)
- [x] 8.3 Syncs: abas ou badges de canal ADN vs SEFAZ
- [x] 8.4 Ajustar e2e/fixtures sintéticas para documents multi-kind
