# Fontes lidas do template — ui-template-fidelity-total

Data: 2026-07-16  
Referência: `.reference/nuxt-dashboard-template`  
Commit confirmado: `0f30c09d697160ef5dd0aaaec27fae8d7195d930`

## Regra de uso

Cada superfície deve começar pelo arquivo-fonte exato abaixo. O template define a **forma**: árvore de componentes, slots, ordem, classes críticas, densidade, hierarquia de ações, breakpoints e interação. O produto adapta somente textos pt-BR, rotas, API real, permissões, tenancy, dados, estados e segurança; qualquer outra divergência é defeito.

O markup canônico deve ficar diretamente na página ou no pai Nuxt que implementa integralmente Settings/Inbox. Wrappers de chrome, bundles híbridos e presets de apresentação não constituem cópia literal.

## Fundação e shell

| Fonte lida | Contrato que deve permanecer reconhecível | Adaptação do produto |
|------------|-------------------------------------------|----------------------|
| `app/app.vue` | `UApp` → `NuxtLoadingIndicator` → `NuxtLayout` → `NuxtPage`; meta de viewport e theme color | `lang=pt-BR`, SEO e identidade MonitorHub |
| `app/app.config.ts` | cores semânticas pelo Nuxt UI | marca controlada; sem seletor arbitrário exposto ao tenant |
| `app/assets/css/main.css` | Tailwind + Nuxt UI e uma única fonte/tokens | sem design system paralelo |
| `app/layouts/default.vue` | `UDashboardGroup`; sidebar collapsible/resizable; header; search; duas navegações; footer; slot; notifications | `OfficeIdentity`, destinos/permissões reais, sem cookie toast e sem view-source |
| `app/components/TeamsMenu.vue` | dimensões, botão ghost/block, comportamento collapsed e trailing icon | `OfficeIdentity`; troca somente entre memberships válidas, nunca `office_id` livre |
| `app/components/shell/UserMenu.vue` | dropdown ancorado no botão de usuário, collapsed e aparência | perfil/logout/2FA/tema reais; remover billing/templates demo |
| `app/components/shell/NotificationsSlideover.vue` | slideover global, lista compacta, chip/avatar, timestamp e deep-link | alertas sanitizados do escritório ativo |
| `app/composables/useDashboard.ts` | shortcuts compartilhados e fechamento do overlay na troca de rota | atalhos/destinos do produto e filtros por papel |

## Arquétipo Home

Fontes lidas:

- `app/pages/index.vue`
- `app/components/home/HomeStats.vue`
- `app/components/home/HomeChart.client.vue`
- `app/components/home/HomeChart.server.vue`
- `app/components/home/HomeSales.vue`
- `app/components/home/HomeDateRangePicker.vue`
- `app/components/home/HomePeriodSelect.vue`

Contrato:

1. `UDashboardPanel` com `UDashboardNavbar`.
2. `UDashboardSidebarCollapse` no `#leading`.
3. Notificação/ação compacta no `#right`.
4. `UDashboardToolbar` somente se o filtro temporal ou controle equivalente tiver efeito real.
5. Body na ordem stats → chart quando houver série real → lista/tabela compacta.
6. Stats usam `UPageGrid` e `UPageCard` com `variant="subtle"`, `gap-4 sm:gap-6 lg:gap-px`, cantos apenas nas extremidades e tokens semânticos.
7. Não inventar gráfico, variação ou período quando a API só fornecer totais pontuais.

## Arquétipo Lista administrativa

Fontes lidas:

- `app/pages/customers.vue`
- `app/components/customers/AddModal.vue`
- `app/components/customers/DeleteModal.vue`

Contrato:

1. Panel → navbar → ação primária no `#right` → body.
2. Faixa utilitária com busca à esquerda e filtros/colunas/ações à direita.
3. `UTable` com a forma crítica:
   - `base: table-fixed border-separate border-spacing-0`;
   - `thead: bg-elevated/50` sem separator artificial;
   - headers arredondados somente nas extremidades, bordas semânticas;
   - `td` com border bottom e última linha sem duplicação.
4. Checkbox somente quando existe ação em massa real; ação de linha no menu de reticências.
5. Modal curto usa `UModal` + `UForm` + Zod, body com campos e ações Cancelar/Confirmar alinhadas à direita.
6. Footer, contagem e `UPagination` são obrigatórios como em `customers.vue`; a fonte de dados é server-side, sem paginação sobre coleção integral local.
7. Infinite scroll, sentinel, sticky/virtualização custom, `Carregar mais` e footer encapsulado em wrapper não são permitidos.

## Arquétipo Mestre–detalhe

Fontes lidas:

- `app/pages/inbox.vue`
- `app/components/inbox/InboxList.vue`
- `app/components/inbox/InboxMail.vue`

Contrato:

1. Primeiro `UDashboardPanel` redimensionável com `default-size=25`, limites e navbar.
2. Lista vertical rolável com divisão, borda de seleção, estados de não lido e atalhos ArrowUp/ArrowDown mantendo o item visível.
3. Segundo painel adjacente em desktop ou empty central quando nada está selecionado.
4. Detalhe móvel em `USlideover` abaixo de `lg`.
5. Segundo painel usa navbar sem toggle, fechar no leading e ações no right.
6. Aplicação canônica nesta change: `/docs`, `/work`, `/monitoring/mailbox` e toda carteira classificada como mestre–detalhe.

## Arquétipo Settings e lista em card

Fontes lidas:

- `app/pages/settings.vue`
- `app/pages/settings/index.vue`
- `app/pages/settings/members.vue`
- `app/pages/settings/notifications.vue`
- `app/pages/settings/security.vue`
- `app/components/settings/MembersList.vue`

Contrato:

1. Panel com `body: lg:py-12`, navbar e collapse.
2. Toolbar com `UNavigationMenu highlight`, alinhada por `-mx-1`.
3. Body `w-full lg:max-w-2xl mx-auto`, gaps `4/6/12`, sem largura alternativa herdada.
4. Formulário: card naked horizontal de título/descrição/ação, seguido por card subtle; `UFormField` responsivo com descrição e `USeparator` entre campos.
5. Lista em card: card naked de título/ação e card subtle com busca no header e lista `role=list` dividida por bordas.
6. Segurança: formulário compacto e ação destrutiva em card separado com cor semântica `error`.

## Checklist de derivação por página

Antes de uma linha da matriz receber `estrutura=PASS`:

- [ ] o commit da referência foi confirmado;
- [ ] os arquivos-fonte exatos estão registrados, sem wildcard ou “inferido”;
- [ ] a página, o pai Nuxt e os componentes delegados foram lidos;
- [ ] a árvore/slots/ordem e classes críticas foram comparados com a fonte;
- [ ] toda diferença está limitada a textos, rotas, dados/API, permissões, tenancy, estados ou segurança;
- [ ] o DOM renderizado comprova a cópia direta do bundle, sem wrapper de chrome;
- [ ] desktop, mobile, teclado e estados aplicáveis preservam a interação do arquétipo.

Esta leitura orienta a change, mas não substitui os gates e evidências por rota.

## Limite verificável da referência

O commit fixado não contém página de login, challenge 2FA ou setup 2FA. Essas três páginas permanecem no inventário funcional, mas recebem `template_dashboard=N/A` justificado e seguem um contrato Nuxt UI de autenticação separado. Redirects também não possuem superfície visual artificial. Nenhuma dessas classificações autoriza divergência nas páginas autenticadas do dashboard.
