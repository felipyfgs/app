## Fase A — Direction no catálogo

- [x] A.1 Coluna `direction` (IN|OUT|UNKNOWN) em `nfse_notes` e `nfe_documents` (+ futuras projeções)
- [x] A.2 Derivar no parse ADN e DistDFe; comando backfill
- [x] A.3 API `direction` em list/detail + filtro query
- [x] A.4 UI filtro Entradas / Saídas / Todas
- [x] A.5 Testes unit/feature direction

## Fase B — Entrada NF-e full (entrega XML)

- [x] B.1 Aplicar / integrar `nfe-manifestacao-destinatario` (unlock via ciência, não MD-e forçada)
- [x] B.2 Download prefer full; label resumo vs completo
- [x] B.3 Smoke client 8 unlock se ainda houver só-resumo

## Fase C — Saídas NF-e / NFC-e (import)

- [x] C.1 Endpoint import multipart XML/ZIP + policy
- [x] C.2 Parser NFE/NFCE → vault + projeção OUT
- [x] C.3 Idempotência sha256; relatório de import
- [x] C.4 UI upload saídas em Documentos ou Cliente
- [x] C.5 Testes import + 403 VIEWER

## Fase D — CT-e

- [x] D.1 Client CTeDistribuicaoDFe + cursor + job
- [x] D.2 Parser + projeção CTE + direction
- [x] D.3 Flag SEFAZ_CTE_ENABLED; listagem kind=CTE
- [x] D.4 Testes contrato fixtures

## Fase E — MDF-e + export

- [x] E.1 Client MDF-e + projeção (opt-in) — **desconsiderado** (fora do escopo a pedido)
- [x] E.2 Export ZIP pastas entrada/saida + kind
- [x] E.3 Inbox/ops cursors multi-canal se faltar

## Fase F — Hardening

- [x] F.1 Matriz de cobertura documentada em docs/ops (o que cada kind/direction usa)
- [x] F.2 Onboarding: DistDFe único por A1; import de saídas do ERP
- [x] F.3 NFC-e só import (sem DistDFe fake)
