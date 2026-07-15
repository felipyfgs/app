# Alertas operacionais — SVRS NFC-e XML

**Change:** `add-svrs-nfce-outbound-xml-retrieval` · task 11.7

## Sinais (sem CNPJ/chave como label)

| Alerta | Condição sugerida | Severidade |
|--------|-------------------|------------|
| Backlog alto | `svrs_nfce` backlog > 50 **ou** idade do mais antigo > 24h | high |
| Breaker global open | `breaker_global.state = open` | critical |
| Queda de captura | taxa capturada/hora < 20% da baseline do piloto por 2h | high |
| Rate limit recorrente | contador `svrs_nfce_retry` com motivo RATE_LIMITED em burst | medium |
| Contrato alterado | motivo `RESPONSE_CONTRACT_CHANGED` (abre breaker) | critical |

## Fontes

- `GET /api/v1/outbound/svrs-nfce/summary`
- `GET /api/v1/operations/summary` → `data.svrs_nfce`
- Inbox: tipos `svrs_nfce_*`
- Logs: `metrics.counter` com `name=svrs_nfce_*`

## Ação padrão

1. Não ampliar rate limit.
2. Verificar kill switch / flags.
3. Fallback assistido (upload).
4. Se contrato: smoke + fixture antes de reset do breaker (ADMIN+2FA).
