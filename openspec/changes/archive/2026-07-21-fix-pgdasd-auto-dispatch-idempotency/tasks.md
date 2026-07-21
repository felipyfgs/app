## 1. N0 — Idempotência e dedupe de dispatches

- [x] 1.1 Compactar `idempotency_key` em `PgdasdCommunicationService::queueDispatches` (≤64), chave estável para `scheduled_consult` e nonce curto para `manual`
- [x] 1.2 Skip de create automático quando a chave estável já existir (dedupe por período/canal)
- [x] 1.3 Testes Feature: automático persiste chave ≤64; segunda chamada não duplica; manual reenviável

## 2. N1 — Sort RBT12 alinhado ao display

- [x] 2.1 Reescrever `pgdasdRbt12SortSubquery` para preferir PARSED do período de display da declaração
  Depende de: 1.1
- [x] 2.2 Atualizar/estender `ModulePortfolioSimplesMeiSubmoduleTest` para mismatch declaração vs PA esperado
  Depende de: 2.1

## 3. N2 — Gates

- [x] 3.1 Rodar `php artisan test --filter='MonitoringCommunicationSend|ModulePortfolioSimplesMei'` e `vendor/bin/pint --test` nos arquivos tocados
  Depende de: 1.3, 2.2
- [x] 3.2 `npx @fission-ai/openspec@1.6.0 validate --specs --strict` e validate da change
  Depende de: 1.3, 2.2
