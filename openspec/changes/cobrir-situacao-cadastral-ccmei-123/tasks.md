## 1. N0 — Contrato e persistência

- [x] 1.1 Confirmar coordenadas e schema oficial do `CCMEISITCADASTRAL123`; registrar catálogo, adapter e fixtures Fake/Simulated.
- [x] 1.2 Criar codec allowlist e projeção tenant-scoped, com migração e testes de resposta inválida, idempotência e ausência de CNPJ/CPF.

## 2. N1 — API de monitoramento

- [x] 2.1 Integrar o pós-consulta ao adapter e criar GET local/POST confirmado com `CurrentOffice`, `TenantAuthorization`, erros e logs sanitizados.
  Depende de: 1.1, 1.2
- [x] 2.2 Cobrir API para confirmação, `office_id`, referência estrangeira e resposta sem dados fiscais.
  Depende de: 2.1

## 3. N2 — Interface

- [x] 3.1 Adicionar tipos, cliente API, composable e painel CCMEI derivado do modal do dashboard.
  Depende de: 2.1
- [x] 3.2 Cobrir UI para loading, vazio, erro, confirmação e ausência de CNPJ/CPF/payload bruto.
  Depende de: 3.1

## 4. N3 — Verificação e evidências

- [x] 4.1 Executar Pint, Composer, PHPUnit, catálogo, ESLint, typecheck, Vitest, generate, fidelidade, OpenSpec e atualizar checklist/evidência com a pendência de homologação externa.
  Depende de: 2.2, 3.2
