# Evidence template — 12.4 / 12.5 TLS + OAuth smoke

| Campo | Valor sanitizado |
|-------|------------------|
| Janela ops (UTC) | _TBD_ |
| `SERPRO_SMOKE_ENABLED` durante janela | true → false ao fim |
| CI | não (bloqueado se true) |
| TLS host | autenticacao.sapi.serpro.gov.br (ou oficial) |
| TLS ok | sim / não |
| TLS latency_ms | _n_ |
| Peer cert sha256 prefix | _xxxxxxxxxxxx_ |
| OAuth ok | sim / não |
| has_access_token | true/false |
| has_jwt_token | true/false |
| expires_at | ISO8601 |
| contract_id | _id_ |
| Readiness recorded | TLS_OK / OAUTH_OK |
| Consultar/Emitir/Declarar | **zero** |

## Comandos (referência)

```text
serpro:smoke tls --confirm=I_UNDERSTAND_LIVE_SERPRO --record-readiness
serpro:smoke oauth --confirm=I_UNDERSTAND_LIVE_SERPRO --record-readiness
```

## Runtime

Tooling completo; **live smoke ops-gated** (não executado nesta sessão de implementação).
