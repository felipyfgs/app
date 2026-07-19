## 1. N0 — Fundações independentes

- [x] 1.1 Criar o pacote Python, configuração, modelos de contrato e testes de validação sem habilitar egress live.
- [x] 1.2 Criar configuração Laravel e tipos de provider/proveniência com defaults OFF e testes unitários.
- [x] 1.3 Criar migração/modelo tenant-scoped de `mei_automation_attempts` e testes de escopo/idempotência.

## 2. N1 — Contratos executáveis

- [x] 2.1 Implementar HMAC, replay cache e endpoints de job/health no FastAPI com testes de contrato.
  Depende de: 1.1
- [x] 2.2 Implementar Celery worker, ciclo de estados, executor fixture e limpeza de contexto/artefato com testes.
  Depende de: 1.1
- [ ] 2.3 Implementar client HMAC Laravel e DTOs do microserviço com testes de assinatura, replay e erros.
  Depende de: 1.2
- [ ] 2.4 Implementar repositório/service de tentativas e redaction de metadados no Laravel.
  Depende de: 1.2, 1.3

## 3. N2 — Roteamento e operação

- [ ] 3.1 Implementar `MeiProviderRouter`, provider SERPRO compatível e provider portal desabilitado/fixture com testes de fallback.
  Depende de: 2.3, 2.4
- [ ] 3.2 Integrar API/worker aos Compose local e produção, healthchecks, rede interna e exemplos de ambiente.
  Depende de: 2.1, 2.2
- [ ] 3.3 Expor metadados públicos seguros de tentativa/proveniência sem permitir `office_id` fornecido pelo cliente.
  Depende de: 2.4, 3.1

## 4. N3 — Gates integrados

- [ ] 4.1 Executar validação OpenSpec, testes Python, testes Laravel/Pint e smoke Docker interno, registrando somente evidências sem segredos.
  Depende de: 3.1, 3.2, 3.3
