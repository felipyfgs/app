## 1. N0 — Contrato e persistência protegida

- [x] 1.1 Confirmar contrato oficial 143, coordenadas, ano de entrada, poder e campos proibidos; adicionar fixture sanitizada e codec fail-closed.
- [x] 1.2 Criar modelo/migração tenant-scoped para descritores de recibo e declaração no cofre, sem `idDefis` ou bytes em banco.

## 2. N1 — Backend de monitoramento

- [x] 2.1 Registrar a coordenada 143 e executar o pós-consulta que persiste PDFs somente no `SecureObjectStore`.
  Depende de: 1.1, 1.2
- [x] 2.2 Expor POST confirmado, GET de histórico local e download autenticado por `CurrentOffice`, com autorização, erros e logs sanitizados.
  Depende de: 1.2
- [x] 2.3 Cobrir codec, cofre, isolamento e Fake/Simulated em testes Laravel; download é autorizado exclusivamente por descritor tenant-scoped.
  Depende de: 2.1, 2.2

## 3. N2 — Interface do monitor

- [x] 3.1 Adicionar tipos, cliente de API, composable, modal de histórico e ação de consulta confirmada por ano no monitor PGDAS-D.
  Depende de: 2.2
- [x] 3.2 Cobrir estados de vazio, erro, consulta confirmada e descritores sem conteúdo sensível em testes Nuxt.
  Depende de: 3.1

## 4. N3 — Gates e evidências

- [x] 4.1 Executar Pint, Composer, PHPUnit, ESLint, typecheck, Vitest, generate, catálogo, OpenSpec e atualizar matriz/evidência com pendência de homologação externa.
  Depende de: 2.3, 3.2
