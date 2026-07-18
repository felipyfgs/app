## 1. N0 — Contrato oficial e limites

- [x] 1.1 Confirmar a documentação oficial de `REGIMEAPURACAO` / `CONSULTAROPCAOREGIME103`, incluindo rota, versão, entrada, resposta e poderes; registrar fonte e campos proibidos.
- [x] 1.2 Mapear os pontos de extensão de catálogo, adapter, projeção, rota e UI, mantendo 101/102/104 inalteradas.

## 2. N1 — Backend seguro

- [x] 2.1 Implementar fixture sanitizada, codec fail-closed e testes de payload/resposta inválida para a coordenada 103.
  Depende de: 1.1, 1.2
- [x] 2.2 Implementar adapter, pós-consulta e projeção idempotente tenant-scoped sem retenção de payload bruto.
  Depende de: 2.1
- [x] 2.3 Expor POST explícito e GET local autorizado por `CurrentOffice`, recusando `office_id` e sem coleta em leitura.
  Depende de: 2.2

## 3. N2 — Interface e cobertura

- [x] 3.1 Implementar tipos, composable e histórico local no monitor PGDAS-D sem identificação fiscal ou payload bruto.
  Depende de: 2.3
- [x] 3.2 Adicionar confirmação explícita de coleta potencialmente faturável e testes Nuxt para vazio, erro, sucesso e atualização.
  Depende de: 3.1
- [x] 3.3 Cobrir Laravel fake/simulated para autorização, isolamento, idempotência, codec e logs sanitizados.
  Depende de: 2.3

## 4. N3 — Gates e evidências

- [x] 4.1 Executar Pint, Composer, PHPUnit, ESLint, typecheck, Vitest, generate, catálogo e OpenSpec; revisar diff/status e atualizar a matriz com pendência de homologação externa.
  Depende de: 3.2, 3.3
