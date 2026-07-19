## 1. N0 — Contratos e persistência fundamentais

- [x] 1.1 Criar enums/configuração de `FISCAL_PROFILE`, classes de operação, catálogo canônico de módulos e kill switch, com testes unitários de política.
- [x] 1.2 Criar migration/model de `fiscal_module_controls`, unicidade por escopo e relacionamento com `Office`/usuário, com teste de persistência e tenancy.
- [x] 1.3 Corrigir os quatro campos obrigatórios de `PROCURACOES/OBTERPROCURACAO41` e ampliar a matriz serviços × procurações, com teste de contrato do catálogo.

## 2. N1 — Serviços centrais independentes

- [x] 2.1 Implementar o resolvedor explicável de disponibilidade e precedência fail-closed, incluindo preservação de leitura histórica, com testes de perfil/kill switch/restrições.
  Depende de: 1.1, 1.2
- [x] 2.2 Implementar serviço transacional de restrição, auditoria, autenticação recente e contagem de bloqueios/recuperação, com testes de autorização e isolamento.
  Depende de: 1.2
- [x] 2.3 Implementar sincronização de procurações por cliente, validade/frescor de sete dias e mapeamento completo de poderes, com testes sem rede.
  Depende de: 1.3
- [x] 2.4 Implementar manutenção diária e alertas internos de certificado/procuração em 30, 7 e 1 dia, com testes de borda temporal.
  Depende de: 1.3

## 3. N2 — Integração nos fluxos de execução

- [x] 3.1 Expor APIs administrativas globais e por escritório para listar/alterar restrições, protegidas por `PLATFORM_ADMIN` e senha recente.
  Depende de: 2.1, 2.2
- [x] 3.2 Integrar resolvedor em consultas manuais, scheduler, dispatch e início dos jobs, registrando abortos sem chamada externa.
  Depende de: 2.1, 2.2
- [x] 3.3 Implementar liberação com coleta de recuperação idempotente, global em lotes e por escritório.
  Depende de: 2.2
- [x] 3.4 Evoluir onboarding do escritório para `202` e estados completos, automatizando Termo, token, procurações e primeira coleta.
  Depende de: 2.3, 2.4
- [x] 3.5 Sincronizar automaticamente a procuração de clientes novos e retornar `202` em consulta manual com evidência antiga.
  Depende de: 2.1, 2.3
- [x] 3.6 Descontinuar flags antigas, atualizar `.env.example`/compose e garantir transporte sem rede em `dev` e leitura apenas em `production`.
  Depende de: 2.1

## 4. N3 — Experiência web

- [x] 4.1 Criar client tipado e página “Módulos fiscais” da plataforma com resumo, tabela global, busca/matriz por escritório e modais de restrição/liberação.
  Depende de: 3.1, 3.3
- [x] 4.2 Adaptar onboarding/status do escritório e páginas fiscais para mostrar progresso, procurações e pausa sem ocultar dados históricos.
  Depende de: 3.2, 3.4, 3.5

## 5. N4 — Gates integrados e prontidão

- [x] 5.1 Executar suites focadas e gates completos de API/web, validar OpenSpec e registrar evidências de perfil, tenancy, fila, onboarding e UI.
  Depende de: 3.6, 4.1, 4.2

### Evidências de validação — 2026-07-19

- `php artisan test`: 96 testes, 361 asserções; cobre perfil sem rede, tenancy, restrições, fila, recuperação, onboarding e procurações.
- `vendor/bin/pint --test`: 1.450 arquivos aprovados.
- `pnpm run test:gate`: ESLint, Nuxt typecheck e 72 testes Vitest aprovados em 17 arquivos.
- `NITRO_OUTPUT_DIR=<temporário> pnpm run generate`: build SPA/PWA aprovado e 62 rotas pré-renderizadas. A saída alternativa evita apenas o `.output` local preexistente com owner `root`.
- `openspec validate simplificar-ativacao-fiscal --strict`: change válido.
