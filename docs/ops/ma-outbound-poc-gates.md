# PoCs e gates G1–G5 — status

**Change:** `build-ma-outbound-nfe-nfce-capture`  
**Atualizado:** 2026-07-15 (fechamento residual G1–G3)

| Gate | Status | Notas |
|------|--------|-------|
| G0 Segurança | **OK** | Backup+verify, flags default off no CI, fixtures, `NO_GO_M2M` |
| G1 Pacote oficial | **OK c/ residual** | 65 importado + SHA=emissor. Residual `NO_PACKAGE_55` — ver `ma-outbound-g1-g3-residual-closure-2026-07-15.md` |
| G2 Consulta homolog | **OK c/ residual** | Prod 65 matriz completa; homolog conectividade 217. Residuais `NO_HOMOLOG_NFE55` / `NO_HOMOLOG_NFCE65` |
| G3 Produção leitura | **OK c/ residual** | 65 série 1 ≤10 sem CSC, 656=0. Residual `NO_SERIES_55` |
| G4 M2M | **`NO_GO_M2M`** | Ver `ma-outbound-g4-g5-decision.md` |
| G5 Mutação | **Desabilitado** | Ver `ma-outbound-g4-g5-decision.md` |

## Piloto read-only (task 10.1)

- **Habilitado** em produção restrita: perfil ACTIVE, allowlist, mandato, modelo 65, série 1.  
- M2M e mutação **independentes e off**.  
- Log: `docs/ops/ma-outbound-pilot-log-2026-07-15.md`.

## Rollback drill (task 10.4)

- **OK** em 2026-07-15: kill switch + flags off no processo preservam cursores, chaves, estados e retrievals; reativação limpa.  
- Detalhe no log do piloto; CI: `OutboundDrillScenariosTest`.

## Drills CI (task 9.6)

`OutboundDrillScenariosTest`: 656 sem avanço de nNF, timeout ambíguo preserva candidata, SHA divergente em quarentena, pacote expirado, kill switch, 562 sem chave.

## Descoberta técnica (XML automático)

Consulta ≠ download de `procNFe`. Limites DistDFe 641/618 e caminhos ASSISTED/ERP/M2M/autXML:

→ `docs/ops/ma-outbound-xml-auto-discovery.md`

## Decisão G5

Enquanto parecer jurídico, mandato mutante e série fechada não estiverem registrados, `SEFAZ_MA_MUTATING_PROBE_ENABLED` permanece `false`. O caminho read-only não é afetado.

## CI

Testes unitários/feature + vitest/playwright de superfície outbound — **sem rede fiscal nem certificado real**.
