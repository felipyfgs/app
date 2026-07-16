# Evidence template — 12.6–12.8 free smoke ladder

**Não versionar Office id/CNPJ/cliente aqui.** Seleção somente na aplicação.

| Etapa | OK? | Notas sanitizadas |
|-------|-----|-------------------|
| Office real não-demo selecionado na app | | |
| Consentimento/autor confirmados | | |
| TERM_LOCAL_VALID | | |
| `/Apoiar` 200/304 | | |
| Token/Expires/ETag no vault (metadados) | | |
| Zero segredo em log/Redis/API | | |
| POWERS_VERIFIED (offline/aprovado) | | |
| `/Monitorar` dentro dos limites | | |
| Zero `/Consultar` `/Emitir` `/Declarar` | | |

## Promoção

```text
serpro:go-live free-smoke-promote --confirm-ladder --termo-local --apoiar-ok \
  --powers-verified --monitorar-ok --zero-billable [--kill-switch-tested]
```

| Campo | Valor |
|-------|-------|
| readiness run_id | _TBD_ |
| highest_gate | FREE_SMOKE_OK |
| CANARY_READY | **não** |

## Runtime

Implementação completa; **execução live ops-gated**.
