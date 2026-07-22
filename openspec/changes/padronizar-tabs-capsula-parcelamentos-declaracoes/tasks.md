## 1. N0 — Cápsulas locais alinhadas ao KPI de Simples Nacional

- [x] 1.1 Ajustar `installments.vue` e `declarations.vue` para reutilizar o mesmo `ShellScrollableTabs`, tamanho `md`, classe de contenção e defaults pill/primary da faixa KPI; manter `Operações` fora do wrapper rolável interno e preservar integralmente filtros, modalidades, obrigações e ações.
  Depende de: `completar-central-declaracoes-serpro` no marco `apply`, `refatorar-ui-ux-parcelamentos` no marco `apply`
- [x] 1.2 Atualizar os testes Vitest de tabs locais, Parcelamentos e Declarações para comparar o contrato canônico com `MonitoringKpiStrip`, provar ausência de overrides visuais locais e manter overflow contido.
- [x] 1.3 Expor `metrics.tab_counts` no overview de Parcelamentos e Declarações com contagens tenant-scoped que ignoram somente a dimensão da tab ativa.
  Depende de: 1.1
- [x] 1.4 Incluir `badge` e placeholder de primeiro carregamento nos itens das tabs de Parcelamentos e Declarações, preservando seleção, prospecção e `Operações`.
  Depende de: 1.3
- [x] 1.5 Atualizar testes Feature e Vitest para validar valores reais, estabilidade entre tabs, DIRF/prospecção zero e contrato visual `label + badge`.
  Depende de: 1.3, 1.4

## 2. N1 — Gates integrados e prontidão

- [x] 2.1 Executar lint direcionado, `pnpm run typecheck` e os testes Vitest focados de navegação, Parcelamentos e Declarações; auditar o diff para preservar as changes upstream.
  Depende de: 1.1, 1.2
  Evidência: ESLint direcionado e typecheck verdes; Vitest com 3 arquivos/33 testes verdes; `git diff --check` direcionado sem erros.
- [x] 2.2 Validar `padronizar-tabs-capsula-parcelamentos-declaracoes` e as specs canônicas com OpenSpec strict.
  Depende de: 1.1, 1.2
  Evidência: change válida em strict e 56/56 specs canônicas válidas.
- [x] 2.3 Executar Pint direcionado, testes Feature focados, ESLint direcionado, typecheck e Vitest focado; auditar o diff compartilhado.
  Depende de: 1.5
  Evidência: Pint e ESLint direcionados verdes; 7 testes Feature/44 asserções e 3 arquivos Vitest/33 testes verdes; typecheck verde (somente warning preexistente de auto-import duplicado).
- [x] 2.4 Revalidar a change e as specs canônicas com OpenSpec strict após a extensão dos contadores.
  Depende de: 1.5
  Evidência: change válida em strict e 56/56 specs canônicas válidas.
