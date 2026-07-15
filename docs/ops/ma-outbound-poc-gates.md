# PoCs e gates G1–G5 — status

**Change:** `build-ma-outbound-nfe-nfce-capture`  
**Atualizado:** 2026-07-15

| Gate | Status | Notas |
|------|--------|-------|
| G0 Segurança | **OK** | Backup+verify, flags off, fixtures, `NO_GO_M2M` |
| G1 Pacote oficial | **Pendente (operacional)** | Código de ingestão pronto; falta piloto real com ZIP SEFAZ-MA |
| G2 Consulta homolog | **Pendente (operacional)** | Cliente HTTP + parser + fakes CI; smoke SVAN/SVRS requer A1 homolog |
| G3 Produção leitura | **Bloqueado** | Depende G1/G2 + allowlist + mandato |
| G4 M2M | **`NO_GO_M2M`** | Ver `ma-outbound-g4-g5-decision.md` |
| G5 Mutação | **Desabilitado** | Ver `ma-outbound-g4-g5-decision.md` |

## Drills CI (task 9.6)

`OutboundDrillScenariosTest`: 656 sem avanço de nNF, timeout ambíguo preserva candidata, SHA divergente em quarentena, pacote expirado, kill switch, 562 sem chave.

## Decisão G5

Enquanto parecer jurídico, mandato mutante e série fechada não estiverem registrados, `SEFAZ_MA_MUTATING_PROBE_ENABLED` permanece `false`. O caminho read-only não é afetado.

## CI

Testes unitários/feature + vitest/playwright de superfície outbound — **sem rede fiscal nem certificado real**.
