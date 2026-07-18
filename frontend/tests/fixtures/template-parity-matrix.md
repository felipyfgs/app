# Matriz de paridade estrutural do template

Inventário restaurado para o gate estrutural do Nuxt UI Dashboard. `SHELL`
exige chrome próprio; `CHILD` herda a casca do pai; `AUTH` e `REDIRECT` ficam
fora do chrome do dashboard. A classificação não altera comportamento de rota.

| Arquivo | Rota | Bundle | Observação |
|---|---|---|---|
| `pages/activate.vue` | — | `AUTH` | layout de ativação |
| `pages/admin/departments.vue` | — | `REDIRECT` | alias |
| `pages/admin/index.vue` | — | `REDIRECT` | alias |
| `pages/admin/offices/[id].vue` | — | `SHELL` | painel próprio |
| `pages/admin/offices/index.vue` | — | `SHELL` | painel próprio |
| `pages/admin/offices/new.vue` | — | `SHELL` | painel próprio |
| `pages/admin/owner/index.vue` | — | `REDIRECT` | alias |
| `pages/admin/serpro.vue` | — | `SHELL` | casca SERPRO |
| `pages/admin/serpro/catalog.vue` | — | `CHILD` | herda SERPRO |
| `pages/admin/serpro/configuration.vue` | — | `CHILD` | herda SERPRO |
| `pages/admin/serpro/contracts.vue` | — | `CHILD` | herda SERPRO |
| `pages/admin/serpro/dte-canary.vue` | — | `CHILD` | herda SERPRO |
| `pages/admin/serpro/index.vue` | — | `CHILD` | herda SERPRO |
| `pages/admin/serpro/rollout.vue` | — | `CHILD` | herda SERPRO |
| `pages/admin/serpro/usage.vue` | — | `CHILD` | herda SERPRO |
| `pages/clients.vue` | — | `SHELL` | casca clientes |
| `pages/clients/[id].vue` | — | `CHILD` | herda clientes |
| `pages/clients/[id]/cadastro.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/ccmei.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/comprovantes.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/certificado.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/sicalc.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/estabelecimentos.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/index.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/pagamentos.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/renuncias.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/saidas.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/[id]/sincronizacao.vue` | — | `CHILD` | herda detalhe |
| `pages/clients/dashboard.vue` | — | `CHILD` | herda clientes |
| `pages/clients/index.vue` | — | `CHILD` | herda clientes |
| `pages/closing.vue` | — | `SHELL` | painel próprio |
| `pages/conta.vue` | — | `SHELL` | casca conta |
| `pages/conta/assinatura.vue` | — | `CHILD` | herda conta |
| `pages/conta/consumo.vue` | — | `CHILD` | herda conta |
| `pages/conta/departamentos.vue` | — | `CHILD` | herda conta |
| `pages/conta/equipe.vue` | — | `CHILD` | herda conta |
| `pages/conta/escritorio.vue` | — | `CHILD` | herda conta |
| `pages/conta/index.vue` | — | `CHILD` | herda conta |
| `pages/docs/[accessKey].vue` | — | `SHELL` | painel próprio |
| `pages/docs/catalog.vue` | — | `SHELL` | painel próprio |
| `pages/docs/import-batches.vue` | — | `REDIRECT` | alias |
| `pages/docs/imports/[id].vue` | — | `SHELL` | painel próprio |
| `pages/docs/imports/index.vue` | — | `SHELL` | painel próprio |
| `pages/docs/index.vue` | — | `SHELL` | painel próprio |
| `pages/exports.vue` | — | `SHELL` | painel próprio |
| `pages/first-access.vue` | — | `AUTH` | layout de primeiro acesso |
| `pages/health.vue` | — | `SHELL` | painel próprio |
| `pages/index.vue` | — | `SHELL` | painel próprio |
| `pages/login.vue` | — | `AUTH` | layout de login |
| `pages/monitoring/clients/[clientId].vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/dctfweb/[submodule].vue` | — | `CHILD` | redirect legado por middleware |
| `pages/monitoring/dctfweb/index.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/declarations.vue` | `pages/customers.vue` | `LIST` | carteira agregada; detalhe em slideover |
| `pages/monitoring/fgts.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/guides.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/index.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/installments.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/mailbox.vue` | — | `SHELL` | casca mailbox |
| `pages/monitoring/mailbox/[id].vue` | — | `CHILD` | herda mailbox |
| `pages/monitoring/mailbox/index.vue` | — | `CHILD` | herda mailbox |
| `pages/monitoring/registrations.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/simples-mei/[submodule].vue` | — | `CHILD` | redirect legado por middleware |
| `pages/monitoring/simples-mei/index.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/sitfis.vue` | — | `SHELL` | painel próprio |
| `pages/monitoring/tax-processes.vue` | — | `SHELL` | painel próprio |
| `pages/notes/[accessKey].vue` | — | `REDIRECT` | alias |
| `pages/notes/index.vue` | — | `REDIRECT` | alias |
| `pages/onboarding.vue` | — | `AUTH` | layout de onboarding |
| `pages/settings.vue` | — | `REDIRECT` | casca legada |
| `pages/settings/cte.vue` | — | `CHILD` | herda settings |
| `pages/settings/departments.vue` | — | `CHILD` | herda settings |
| `pages/settings/index.vue` | — | `CHILD` | herda settings |
| `pages/settings/proxies.vue` | — | `REDIRECT` | alias |
| `pages/settings/subscription.vue` | — | `CHILD` | herda settings |
| `pages/settings/team.vue` | — | `CHILD` | herda settings |
| `pages/settings/usage.vue` | — | `CHILD` | herda settings |
| `pages/syncs.vue` | — | `SHELL` | painel próprio |
| `pages/two-factor-challenge.vue` | — | `AUTH` | redirect autenticado |
| `pages/two-factor/setup.vue` | — | `AUTH` | redirect autenticado |
| `pages/work/calendar.vue` | — | `SHELL` | painel próprio |
| `pages/work/index.vue` | — | `SHELL` | painel próprio |
| `pages/work/processes/[id].vue` | — | `SHELL` | painel próprio |
| `pages/work/processes/index.vue` | — | `SHELL` | painel próprio |
| `pages/work/tasks/[id].vue` | — | `SHELL` | painel próprio |
| `pages/work/templates/index.vue` | — | `SHELL` | painel próprio |
