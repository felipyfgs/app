# Matriz de fidelidade — Monitoramento Fiscal

**Change:** `complete-monitoring-visual-fixtures` · task **1.3**  
**Template fixado:** `.reference/nuxt-dashboard-template`  
**Regra:** forma, slots, ações e responsividade vêm do template; capturas externas (ex. HubStorm) orientam só densidade/hierarquia informacional.

Referência de capability: `openspec/changes/complete-monitoring-visual-fixtures/specs/dashboard-template-fidelity/spec.md`.

## Legenda de arquétipos

| Arquétipo | Arquivos canônicos no template |
|-----------|--------------------------------|
| **Home** | `app/pages/index.vue`, `app/components/home/HomeStats.vue`, `HomeChart.*.vue`, `HomeSales.vue`, `HomeDateRangePicker.vue`, `HomePeriodSelect.vue` |
| **Customers** | `app/pages/customers.vue`, `app/components/customers/AddModal.vue`, `DeleteModal.vue` |
| **Inbox** | `app/pages/inbox.vue`, `app/components/inbox/InboxList.vue`, `InboxMail.vue` |
| **Settings** | `app/pages/settings.vue`, `app/pages/settings/index.vue`, `members.vue`, `notifications.vue`, `security.vue`, `app/components/settings/MembersList.vue` |
| **Shell** | `app/layouts/default.vue`, `app/components/TeamsMenu.vue`, `UserMenu.vue`, `NotificationsSlideover.vue` |

Todas as rotas abaixo preservam o shell do produto (`UDashboardPanel` + navbar + sidebar collapse), alinhado ao layout default do template.

## Matriz por rota

| Rota pública | Arquivo frontend | Arquétipo (template) | Arquivos exatos de origem | Adaptações funcionais justificadas |
|--------------|------------------|----------------------|---------------------------|-------------------------------------|
| `/monitoring` | `frontend/app/pages/monitoring/index.vue` | **Home** | `pages/index.vue`; `components/home/HomeStats.vue`; blocos de `home/*` para cards/listas | KPIs gerais de carteira fiscal (não vendas); cobertura por módulo; pendências/findings/consumo SERPRO sanitizado; atalhos para módulos. Sem `HomeChart` de receita se não houver série fiscal homóloga. |
| `/monitoring/simples-mei` | `…/simples-mei.vue` | **HomeStats + Customers** | `components/home/HomeStats.vue`; `pages/customers.vue` | Tabs de submódulo (PGDAS-D, PGMEI, DASN-SIMEI, Regime) em `UTabs` — conteúdo fiscal sem paralelo no template; tabela de carteira e toolbar = Customers; KPIs só com métricas reais da API. |
| `/monitoring/dctfweb` | `…/dctfweb.vue` | **HomeStats + Customers** | idem | Tabs DCTFWeb/MIT; eixos independentes (encerramento, transmissão, recibo, evidência, DARF, pagamento). Filtro pós-paginação proibido — filtros server-side. |
| `/monitoring/installments` | `…/installments.vue` | **HomeStats + Customers** | idem | Tabs/modalidades do catálogo Integra-Parcelamento; saldo/parcelas/próxima parcela; deep-link só para abas existentes. |
| `/monitoring/sitfis` | `…/sitfis.vue` | **Customers + Slideover** | `pages/customers.vue`; padrão slideover do shell (`NotificationsSlideover.vue` como referência de overlay) | Carteira + idade/TTL; detalhe de achados normalizados em slideover — **nunca** JSON bruto. `client_id` obrigatório na API atual. |
| `/monitoring/mailbox` | `…/mailbox.vue` | **Inbox** | `pages/inbox.vue`; `components/inbox/InboxList.vue` | Lista mestre tenant-scoped; campos `subject_preview`, `received_at_official`, triagem interna. Desktop: lista+detalhe adjacentes; mobile: detalhe em drawer/slideover. |
| `/monitoring/mailbox/[id]` | `…/mailbox/[id].vue` | **InboxMail** | `components/inbox/InboxMail.vue` | Rota canônica do detalhe; corpo/anexos só via download autorizado; triagem `NEW`/`IN_REVIEW`/`RESOLVED` sem alterar ciência oficial. |
| `/monitoring/declarations` | `…/declarations.vue` | **HomeStats + Customers** | `HomeStats.vue`; `customers.vue` | KPIs do `summary` real da API; colunas de obrigação/aplicabilidade/competência/entrega; deep-links para módulos de origem. |
| `/monitoring/guides` | `…/guides.vue` | **Customers + modal** | `customers.vue`; `components/customers/AddModal.vue` (padrão modal) | Colunas `amount_cents` / `payment_status` (não `amount`/`status`); detalhe e download efêmero; modal de emissão só se mutação autorizada (demo: bloqueada). |
| `/monitoring/fgts` | `…/fgts.vue` | **HomeStats + Customers** | `HomeStats.vue`; `customers.vue` | Banner permanente de cobertura parcial; estados `UNSUPPORTED` honestos para guia/pagamento portal; sem ação de scraping/portal. |
| `/monitoring/clients/[clientId]` | `…/clients/[clientId].vue` | **Settings** | `pages/settings.vue` + `pages/settings/*`; `components/settings/MembersList.vue` (lista seccionada) | Seções lazy (resumo, execuções, findings, pendências, parcelamentos, declarações, guias, FGTS, SITFIS); falha parcial com retry — não silenciar em lista vazia. |

## Componentes compartilhados (mapeamento futuro desta change)

| Componente produto (planejado) | Origem template | Notas de fidelidade |
|--------------------------------|-----------------|---------------------|
| `MonitoringModuleNav` | Toolbar de Settings (`UNavigationMenu` / highlight) | Dentro de `UDashboardToolbar`; mobile sem overflow do documento |
| `FiscalKpiStrip` | `HomeStats.vue` / faixa de insights | Total, Em dia, Processando, Pendências, Atenção — acionáveis |
| `FiscalModuleTable` | `pages/customers.vue` + `DASHBOARD_TABLE_UI` | Ordem: Panel → Navbar → Toolbar → utilitários → Table → empty/error → paginação |
| `FiscalClientPicker` | Busca/filtros de Customers | Server-side por razão social / CNPJ — sem ID manual obrigatório |
| `FiscalClientCell` / badges | Células e badges de Customers | CNPJ mascarado; texto semântico; origem `DEMO`/`LIVE` visível |
| `FiscalTableEmptyState` | Empty states de Customers/Inbox | Distingue loading, vazio, erro, `UNSUPPORTED`, `BLOCKED` |

## O que **não** copiar de referências externas

| Padrão externo | Tratamento no produto |
|----------------|----------------------|
| Coluna lateral fixa de ações | Ação primária em `UDashboardNavbar #right`; linha em menu; filtros na toolbar |
| Cores/marca de terceiro | Tokens Nuxt UI + identidade MonitorHub; ícones `i-lucide-*` |
| Densidade “dashboard de marketing” sem dados | Só KPIs com API real; vazio honesto sem fallback sintético em produção |

## Viewports de regressão visual (aceite desta change)

| Viewport | Uso |
|----------|-----|
| `1440×900` | Desktop — shell, nav do módulo, KPIs, tabela, mestre–detalhe |
| `390×844` | Mobile — nav/filtros prioritários, identidade, situação, ação principal / overlay |
| min `360px` | Sem overflow horizontal do documento |

Baselines e Playwright ficam nas tasks da seção 9; esta matriz é a fonte de rastreio arquétipo ↔ rota.

## Status de implementação da matriz (UI + fixtures)

| Rota | Matriz registrada | UI fiel (seções 6–7) | Fixtures / visual (seções 3–4 / 9) |
|------|------------------|----------------------|-----------------------------------|
| `/monitoring` | **Sim** | **Concluído** | **Concluído** (e2e visual + overflow) |
| `/monitoring/simples-mei` | **Sim** | **Concluído** | **Concluído** |
| `/monitoring/dctfweb` | **Sim** | **Concluído** | **Concluído** |
| `/monitoring/installments` | **Sim** | **Concluído** | **Concluído** |
| `/monitoring/sitfis` | **Sim** | **Concluído** | **Concluído** (+ overlay achados) |
| `/monitoring/mailbox` | **Sim** | **Concluído** | **Concluído** |
| `/monitoring/mailbox/[id]` | **Sim** | **Concluído** | **Concluído** (detalhe visual) |
| `/monitoring/declarations` | **Sim** | **Concluído** | **Concluído** |
| `/monitoring/guides` | **Sim** | **Concluído** | **Concluído** |
| `/monitoring/fgts` | **Sim** | **Concluído** | **Concluído** (banner parcial) |
| `/monitoring/clients/[clientId]` | **Sim** | **Concluído** | **Concluído** (settings panel) |

Checklist canônico por rota: `UDashboardPanel` → navbar (`page-navbar`) → toolbar (`page-toolbar` / `MonitoringModuleNav`) → KPIs ou lista → tabela/empty/error → paginação server-side; viewports `1440×900`, `390×844`, overflow em `360px` (`frontend/tests/e2e/monitoring-visual.spec.ts`).

Runbook demo: `docs/ops/monitoring-demo-runbook.md`.
