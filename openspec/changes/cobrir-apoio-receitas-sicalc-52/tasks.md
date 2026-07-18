## 1. N0 — Contrato e persistência

- [x] 1.1 Confirmar a coordenada e schema oficiais, criar codec allowlist, migração e projeção tenant-scoped com testes de inválidos/idempotência.

## 2. N1 — Monitor e API

- [x] 2.1 Registrar adapter SICALC, fixtures Fake/Simulated e pós-consulta com evidência sanitizada.
  Depende de: 1.1
- [x] 2.2 Expor GET de histórico e POST confirmado com `CurrentOffice`, permissões, erros e logs seguros.
  Depende de: 2.1

## 3. N2 — Interface

- [x] 3.1 Adicionar tipos, cliente API, composable e painel derivado do detalhe do dashboard.
  Depende de: 2.2
- [x] 3.2 Cobrir interface para loading, vazio, erro, consulta e ausência de dados fiscais.
  Depende de: 3.1

## 4. N3 — Verificação e evidências

- [x] 4.1 Executar gates backend/frontend/OpenSpec e atualizar checklist/evidências, mantendo explícita a pendência de homologação externa.
  Depende de: 2.2, 3.2
