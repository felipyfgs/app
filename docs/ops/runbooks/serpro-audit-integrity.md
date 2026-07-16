# Runbook — quebra de integridade da auditoria

## Sintoma

- Comando `php artisan audit:verify-chain` retorna `AUDIT_CHAIN_BREAK`.
- Log `audit.integrity.break` com `reason_code` (`PREV_HASH_MISMATCH` | `ENTRY_HASH_MISMATCH`).
- Métrica `audit.integrity.break` (labels: `reason_code`, `channel=audit` — sem PII).

## O que **não** fazer

- Não dump de `context` completo em ticket.
- Não “reescrever” hashes para forçar OK.
- Não apagar `audit_logs`.

## Resposta

1. Registrar incidente com `broken_at_seq`, `reason_code`, `checked` apenas.
2. Congelar writes suspeitos se houver indício de adulteração (kill switch se correlacionado a SERPRO).
3. Snapshot de backup DB imutável.
4. Investigar acesso privilegiado / migração parcial / bug de backfill.
5. Após root cause, documentar; se necessário, iniciar **nova cadeia** apenas com aprovação formal (não silencioso).

## Verificação rotineira

```bash
docker compose exec -T php php artisan audit:verify-chain --json
# agendado: audit:verify-chain --alert (diário)
```
