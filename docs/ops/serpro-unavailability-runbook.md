# Runbook: indisponibilidade SERPRO / Integra Contador

## Escopo

Indisponibilidade da API Integra Contador (gateway SERPRO) afeta **todos** os tenants que dependem de consultas/mutações fiscais oficiais. Canais ADN/SEFAZ documentais do tenant **não** usam o contrato SERPRO e podem permanecer saudáveis.

## Sinais

- Inbox tenant: `source_unavailable` / summary `platform_health.available=false`
- Platform: `GET /api/v1/platform/serpro/health` → `active_contract.health_status` degradado
- Métricas: `serpro.http.result` com `http_class=5xx` elevado; latência `gte_10s`
- Kill switch acidentalmente ativo (`SERPRO_KILL_SWITCH` ou API)
- Circuit breaker global aberto (`serpro.breaker.trip`)

## Resposta imediata

1. **Confirmar escopo** (global vs um tenant):
   - Global: vários offices com `source_unavailable` + health platform unavailable.
   - Tenant: Termo/token/procuração — ver onboarding; health platform pode estar OK.
2. **Não rotacionar certificado** só por indisponibilidade de rede/API (ver runbook de rotação).
3. Se incidente de segurança/credencial: kill switch ON — ver `serpro-suspected-leak-runbook.md` e `serpro-global-cert-rotation-runbook.md`.
4. Comunicar tenants apenas: “integração oficial temporariamente indisponível” — **sem** detalhe de contrato, CNPJ contratante, tokens ou outros escritórios.

## Diagnóstico (sanitizado)

```bash
php artisan serpro:contract health --env=PRODUCTION
# ou
GET /api/v1/platform/serpro/health?environment=PRODUCTION
```

Verificar:

| Campo | Interpretação |
|-------|----------------|
| `kill_switch.active` | Kill manual/config |
| `circuit_breaker.state=open` | Ver `serpro-circuit-breaker-runbook.md` |
| `active_contract=null` | Sem contrato ACTIVE no ambiente |
| `health_status=BLOCKED` | Contrato bloqueado (OAuth/cert) |
| `cert_valid_to` | Expiração próxima do e-CNPJ contratante |

## Mitigação

1. Aguardar recuperação do gateway se for outage SERPRO (status oficial SERPRO).
2. Rate limit / 429: ver `serpro-rate-limit-runbook.md`.
3. Breaker aberto por cascata: ver `serpro-circuit-breaker-runbook.md`.
4. OAuth 401/403: validar vigência do cert e Consumer Key/Secret; smoke mTLS fora de CI.
5. Jobs de monitoramento fiscal: filas drenam com requeue; **não** apagar snapshots/evidências/ledger.

## Pós-incidente

- Registrar correlação (`correlation_id`) e janela temporal em postmortem interno.
- Comparar ledger de consumo com relatório SERPRO se houve cobrança anômala.
- Tenants só veem saúde sanitizada (`available`, `status`, `kill_switch`, `circuit_open`) — validar que nenhum payload de suporte vazou material sensível.
