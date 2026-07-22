## 1. N0 — Carteira Parcelamentos alinhada ao padrão operacional

- [x] 1.1 Refatorar `apps/web/app/pages/monitoring/installments.vue` para usar uma tab compacta por modalidade oficial, aplicar um código por vez no filtro server-side, integrar PAEX/SIPADE como tabs próprias indisponíveis, condensar/reordenar a spine da tabela e preservar o slideover local; atualizar `apps/web/tests/unit/installments-monitoring.test.ts` com asserções do novo contrato e executar o teste direcionado.
  Depende de: `monitorar-todos-parcelamentos-serpro` no marco `apply` (relação coordenada)
  Evidência: Vitest direcionado verde com 9 testes, incluindo catálogo, feedback, client HTTP de catálogo/lote, erro propagado, tabs, tabela e status; Playwright no Chrome validou 11 tabs, filtro individual, fail-closed e responsividade.

- [x] 1.2 Fazer as tabs superiores reutilizarem exatamente os mesmos tokens canônicos das tabs de KPI (`ShellScrollableTabs`, `size="md"`, sem overrides locais de `list`/`trigger`); atualizar o teste unitário e validar visualmente em 1366×639.
  Depende de: 1.1
  Evidência: teste compara os dois markups sem `:ui`; captura Playwright em 1366×639 revisada sem overflow horizontal da página.

## 2. N1 — Gates integrados e prontidão

- [x] 2.1 Executar os gates Web `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity` e `pnpm run test:artifacts`, corrigindo somente regressões causadas por esta change.
  Depende de: 1.1
  Evidência: lint direcionado, typecheck e generate verdes; Vitest de Parcelamentos 9/9 e Playwright 1/1 verdes. A suíte global executou 265 testes verdes e manteve uma falha externa no grafo por dois endpoints concorrentes de Declarações; lint global manteve 13 aspas em `dashboard-theme-selector.test.ts`. Fidelity/artifacts foram executados, mas o baseline segue sem `template-parity-matrix.md` e `scan-artifacts.mjs`.
- [x] 2.2 Executar `openspec validate refatorar-ui-ux-parcelamentos --type change --strict` e `openspec validate --specs --strict`, auditar o diff compartilhado para preservar a change upstream e confirmar ausência de segredos, egress, flags abertas ou serviços Compose novos.
  Depende de: 1.1
  Evidência: change e 56 specs válidas em strict; diff direcionado limpo; Compose dev/prod válido; stack E2E removido; nenhuma flag de produção, segredo, egress PAEX/SIPADE ou serviço Compose novo.
