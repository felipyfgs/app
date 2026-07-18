## 1. N0 — Projeção local de validade

- [x] 1.1 Criar resolvedor read-only de procuração que derive `authorized`, `expiring`, `expired`, `missing` ou `unverified` apenas de `ClientProcuracaoSync`/snapshot oficial, sem write nem egress; cobrir limites de vigência e ausência de evidência.
- [x] 1.2 Integrar a projeção em `GET /clients` e `GET /clients/{client}` com eager loading tenant-scoped, sem N+1, `office_id` do navegador ou exposição de evidência/poderes/identificadores fiscais.
  Depende de: 1.1
- [x] 1.3 Criar Feature tests para clientes do escritório atual, cliente estrangeiro, expiração local, estado ausente e ausência de chamadas Integra na leitura.
  Depende de: 1.2

## 2. N1 — Coluna operacional de clientes

- [x] 2.1 Evoluir tipo e helper Nuxt para `expiring`, validade e última verificação, preservando compatibilidade dos quatro estados oficiais e o contrato sanitizado.
  Depende de: 1.2
- [x] 2.2 Adaptar a coluna existente de `/clients` pelo arquétipo `customers.vue`: badge, data de vencimento e orientação de renovação, mantendo desktop/mobile, loading, vazio e sem ação de sync automática.
  Depende de: 2.1
- [x] 2.3 Cobrir no Vitest as cinco situações de procuração, a renderização da coluna e a garantia de que abrir a lista só dispara GET local.
  Depende de: 2.2

## 3. N2 — Gates integrados

- [x] 3.1 Executar Pint, testes Laravel focados, lint, typecheck, Vitest, generate, fidelity, artifacts, OpenSpec estrito e varredura de segredos; registrar que não houve egress SERPRO.
  Evidência em 18/07/2026: Pint (1.682 arquivos), Feature clients (8 testes/74 assertions), Vitest de procurações (3/3), `typecheck`, `lint`, `generate`, `test:fidelity` e `test:artifacts` passaram; OpenSpec estrito e `git diff --check` passaram. Revisão independente: `VERDICT PASS`. Não houve egress SERPRO.
  Depende de: 1.3, 2.3
