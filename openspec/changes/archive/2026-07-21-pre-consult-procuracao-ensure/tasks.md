## 1. N0 — Serviço ensure + invalidação de snapshot

- [x] 1.1 Criar `EnsureClientProcuracaoForConsult` (check local → `syncOfficial` se necessário → recheck → resultado/código)
- [x] 1.2 Em `invalidateDerivedAuthorization` / remoção de A1, marcar snapshots de procuração do office/ambiente como não verificados
  - Depende de: 1.1

## 2. N1 — Inserir ensure nos caminhos de consulta

- [x] 2.1 Chamar o ensure em `SimplesMeiAdapter::execute` antes de `IntegraEligibilityService::evaluate`
  - Depende de: 1.1
- [x] 2.2 Alinhar `ManualConsultExecutionService` ao mesmo ensure (substituir só-enqueue quando aplicável)
  - Depende de: 1.1

## 3. N1 — Testes da capability

- [x] 3.1 Feature/unit: poder local usável → não chama Integra Procurações; ausente → sync → consulta segue ou bloqueia
  - Depende de: 2.1
  - Evidência: `php artisan test --filter=PreConsultProcuracaoEnsure`
- [x] 3.2 Teste: pós invalidação A1, snapshot não mascara ensure na próxima consulta
  - Depende de: 1.2, 2.1
  - Evidência: `php artisan test --filter=PreConsultProcuracaoEnsure`

## 4. N2 — Gates integrados

- [x] 4.1 Gates API: `vendor/bin/pint --test` + testes da área
  - Depende de: 3.1, 3.2
- [x] 4.2 `openspec validate --changes --strict` (change `pre-consult-procuracao-ensure`)
  - Depende de: 4.1
