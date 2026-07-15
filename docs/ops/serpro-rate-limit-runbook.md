# Runbook: rate limit SERPRO (HTTP 429 / Retry-After)

## Escopo

Limites de taxa no gateway Integra Contador (global da software house) e proteção de franquia por tenant. Respostas **429** e headers `Retry-After` devem ser respeitados; retries cegos agravam o bloqueio.

## Sinais

- Métrica `serpro.http.429` / `ops.http_429` com `channel=serpro_http`
- Logs estruturados `serpro.http.transport` com `http_class=429`
- Inbox: `usage_high` / `usage_franchise_exceeded` (limite comercial do plano)
- Jobs fiscais com `skip_reason=RATE_LIMITED` ou requeue frequente

## Resposta

1. **Parar fan-out** de jobs manuais massivos no ambiente afetado.
2. Confirmar se é **429 de rede SERPRO** vs **bloqueio de franquia do tenant**:
   - 429 HTTP → este runbook.
   - `FRANCHISE_EXCEEDED` / alerta de consumo → ajustar plano/quota ou aguardar período; ledger em `serpro_api_usage_*`.
3. Respeitar `Retry-After` (transporte já propaga o valor).
4. Reduzir concorrência de filas de monitoramento fiscal (`fiscal_monitoring` / Horizon).
5. Se um tenant ruidoso consumir share global: avaliar `NOISY_TENANT_SHARE` e suspender jobs daquele office (PLATFORM_ADMIN), sem expor dados de outros tenants.

## Verificação

```bash
# Saúde (sem segredos)
php artisan serpro:contract health --env=PRODUCTION

# Métricas via logs (labels de baixa cardinalidade)
# grep metrics.counter / logs — name=serpro.http.429
```

## Não fazer

- Retry imediato em loop após 429.
- Aumentar RPS global sem evidência de liberação SERPRO.
- Expor `Retry-After` com payload de erro bruto (pode conter tokens) em API tenant.
- Usar CNPJ completo como label de métrica.

## Encerramento

Quando `http_class=429` voltar ao baseline e filas drenarem sem crescimento de backlog, registrar janela e correlation ids. Se a franquia do tenant foi o gatilho, alinhar comercialmente o plano — ver `serpro-invoice-divergence-runbook.md` se houver divergência de fatura.
