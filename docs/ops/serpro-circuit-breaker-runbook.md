# Runbook: circuit breaker SERPRO

## Escopo

Breaker **global** (e opcionalmente por solução) protege a plataforma de cascata quando OAuth/mTLS/API falham em sequência. Enquanto aberto, novas chamadas Integra são bloqueadas; evidências e ledger **não** são apagados.

## Sinais

- `circuit_breaker.state=open` em health platform
- Tenants veem `platform_health.circuit_open=true` / inbox `source_unavailable` (reason `circuit_open`)
- Auditoria `serpro.breaker.trip`
- Métrica `serpro.breaker.trip` com label `breaker_state=open`

## Abertura automática

Threshold e janela em `config/serpro.php` (`circuit_breaker.failure_threshold`, `open_seconds`). Falhas de transporte/5xx contam; sucesso fecha o contador.

## Ações

1. **Não resetar imediatamente** sem diagnóstico — o open window evita thundering herd.
2. Diagnosticar causa raiz:
   - Outage SERPRO → aguardar + `serpro-unavailability-runbook.md`
   - Cert/OAuth → `serpro-global-cert-rotation-runbook.md`
   - Rate limit → `serpro-rate-limit-runbook.md`
3. Após causa mitigada e smoke OK:

```bash
# Reset controlado (PLATFORM_ADMIN / CLI conforme implementação)
# Registrar reason sanitizado na auditoria serpro.breaker.global_reset
php artisan serpro:contract health --env=PRODUCTION
```

4. Validar `state=closed` e uma chamada trial/smoke **fora de CI**.
5. Destravar filas de monitoramento gradualmente (coortes).

## Tenants

- Mensagem sanitizada: indisponibilidade geral — **sem** métricas de outros offices, fingerprint de contrato ou consumer key.
- Jobs em requeue retomam sozinhos quando breaker fecha e elegibilidade passa.

## Não fazer

- Reset em loop enquanto 5xx persistem.
- Desabilitar breaker em produção para “passar” jobs.
- Logar corpo de resposta ou certificado ao investigar.
