# Evidence template — 12.3 clean prod deploy

| Campo | Valor |
|-------|-------|
| Data/hora deploy | _TBD_ |
| Imagem/tag | _TBD_ |
| `serpro:smoke checklist` | OK / FAIL |
| `serpro:prod-check` | OK / FAIL |
| Drivers real | nenhum / listar |
| `SERPRO_SMOKE_ENABLED` | false |
| Kill switch no deploy | ON / OFF |
| Kill switch drill | feito / pendente |
| Demo segregado | sim |
| Budgets positivos preparados | sim / não |

## Checklist

- [ ] Flags mutantes OFF
- [ ] Capabilities sem `real` (ou allowlist explícita documentada fora do OpenSpec)
- [ ] Demo fora de allowlist real
- [ ] Horizon consumers SERPRO OK

## Runtime

Implementação/checklist completa; **deploy live ops-gated**.
