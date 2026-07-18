## 1. N0 — Referência protegida da lista DEFIS

- [x] 1.1 Corrigir o contrato local da 142 para validar `idDefis` canônico somente em memória e criar referência opaca no cofre sem mudar a saída pública.
  Depende de: `cobrir-consulta-declaracoes-defis-142` (verify)
- [x] 1.2 Criar modelo/migração tenant-scoped para a referência opaca e testes de idempotência, isolamento e ausência de identificador em banco público.
  Depende de: 1.1

## 2. N1 — Consulta e evidência DEFIS 144

- [x] 2.1 Registrar `DEFIS/CONSDECREC144`, operação do catálogo, payload seguro por referência e fixtures Fake/Simulated.
  Depende de: 1.2
- [x] 2.2 Implementar codec fail-closed e pós-consulta para recibo/declaração, persistindo bytes no cofre e apenas descritores locais.
  Depende de: 2.1
- [x] 2.3 Expor POST confirmado, GET de histórico e download autenticado por `CurrentOffice`, com `TenantAuthorization`, erros e logs sanitizados.
  Depende de: 2.2

## 3. N2 — Interface e testes de contrato

- [x] 3.1 Adicionar tipos, API, composable, ações por referência opaca e modal de histórico no monitor PGDAS-D.
  Depende de: 2.3
- [x] 3.2 Cobrir Laravel e Nuxt para referências estrangeiras, confirmação, cofre, download, estados vazios/erro e ausência de `idDefis`/Base64.
  Depende de: 3.1

## 4. N3 — Gates e evidências

- [x] 4.1 Executar Pint, Composer, PHPUnit, catálogo, ESLint, typecheck, Vitest, generate, fidelidade, OpenSpec e atualizar matriz/evidência com pendência de homologação externa.
  Depende de: 3.2
