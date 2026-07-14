# Baseline e matriz de fidelidade do dashboard

## Proveniência congelada

| Campo | Valor verificável |
|---|---|
| Origem | `https://github.com/nuxt-ui-templates/dashboard.git` |
| Commit | `0f30c09d697160ef5dd0aaaec27fae8d7195d930` |
| Árvore Git | `807ea5d2f9ee5125f1a2cc16451b2698d73a5c69` |
| Estado auditado | `HEAD` destacado, índice limpo e worktree limpa |
| Licença | MIT, preservada em `frontend/LICENSE` |
| Arquivos versionados na origem | 48 |
| Nuxt | `4.4.8` |
| Nuxt UI | `4.9.0` |
| Vue | `3.5.39` |
| TypeScript | `6.0.3` |
| Tailwind CSS | `4.3.1` na referência; `4.3.2` no produto, sem mudança de major/minor |

Comandos de conferência:

```bash
git -C .reference/nuxt-dashboard-template rev-parse HEAD
git -C .reference/nuxt-dashboard-template rev-parse 'HEAD^{tree}'
git -C .reference/nuxt-dashboard-template diff --exit-code
git -C .reference/nuxt-dashboard-template diff --cached --exit-code
```

Árvore congelada:

```text
.editorconfig
.env.example
.github/workflows/ci.yml
.gitignore
LICENSE
README.md
app/app.config.ts
app/app.vue
app/assets/css/main.css
app/components/NotificationsSlideover.vue
app/components/TeamsMenu.vue
app/components/UserMenu.vue
app/components/customers/AddModal.vue
app/components/customers/DeleteModal.vue
app/components/home/HomeChart.client.vue
app/components/home/HomeChart.server.vue
app/components/home/HomeDateRangePicker.vue
app/components/home/HomePeriodSelect.vue
app/components/home/HomeSales.vue
app/components/home/HomeStats.vue
app/components/inbox/InboxList.vue
app/components/inbox/InboxMail.vue
app/components/settings/MembersList.vue
app/composables/useDashboard.ts
app/error.vue
app/layouts/default.vue
app/pages/customers.vue
app/pages/inbox.vue
app/pages/index.vue
app/pages/settings.vue
app/pages/settings/index.vue
app/pages/settings/members.vue
app/pages/settings/notifications.vue
app/pages/settings/security.vue
app/types/index.d.ts
app/utils/index.ts
eslint.config.mjs
nuxt.config.ts
package.json
pnpm-lock.yaml
pnpm-workspace.yaml
public/favicon.ico
renovate.json
server/api/customers.ts
server/api/mails.ts
server/api/members.ts
server/api/notifications.ts
tsconfig.json
```

## Matriz origem → destino

| Arquétipo ou bloco de origem | Destino no produto | Preservar literalmente | Adaptações autorizadas |
|---|---|---|---|
| `app/app.vue` | `frontend/app/app.vue` | `UApp`, loading, layout e página | locale pt-BR, metadados e marca do produto |
| `app/assets/css/main.css` | `frontend/app/assets/css/main.css` | imports, Public Sans e paleta verde | nenhuma sem exceção registrada |
| `app/app.config.ts` | `frontend/app/app.config.ts` | cores semânticas | remover presets que substituam classes explícitas da origem |
| `app/layouts/default.vue` | `frontend/app/layouts/default.vue` | grupo, sidebar, slots, busca, navegação, rodapé e slideover | rotas, textos, permissões e remoção de conteúdo demonstrativo |
| `app/components/TeamsMenu.vue` | `frontend/app/components/OfficeIdentity.vue` | botão, dimensões, collapse, variantes e classes | escritório da sessão; sem dropdown ou troca de tenant |
| `app/components/UserMenu.vue` | `frontend/app/components/UserMenu.vue` | dropdown, gatilho, largura e comportamento recolhido | usuário real, logout, pt-BR e paleta fixa |
| `app/components/NotificationsSlideover.vue` | `frontend/app/components/NotificationsSlideover.vue` | slideover, lista, ritmo e estado visual | alertas operacionais sanitizados e links internos reais |
| `app/composables/useDashboard.ts` | `frontend/app/composables/useDashboard.ts` | estado compartilhado, fechamento por rota e atalhos | rotas e ações filtradas por perfil |
| `app/pages/index.vue` | `frontend/app/pages/index.vue` | painel, navbar, ação, toolbar e ordem do corpo | métricas e ações reais; sem filtro ou gráfico fictício |
| `app/components/home/HomeStats.vue` | bloco de indicadores de `frontend/app/pages/index.vue` | grade, cards, gaps, bordas, tipografia e proporções | títulos, ícones, links e valores operacionais reais |
| `app/pages/customers.vue` | `frontend/app/pages/clients/index.vue` | navbar, faixa utilitária, tabela, footer e hierarquia de ações | paginação server-side, colunas e endpoints de Clientes |
| `app/components/customers/AddModal.vue` | `frontend/app/components/clients/ClientCreateModal.vue` | modal, formulário, campos e footer | CNPJ alfanumérico, busca apenas para numérico, erros 422 e API real |
| `app/pages/customers.vue` | `frontend/app/pages/exports/index.vue` | arquétipo de lista administrativa | dados, estados, filtros, modal e paginação da API de Exportações |
| `app/pages/customers.vue` | `frontend/app/pages/syncs/index.vue` | arquétipo de lista administrativa | dados, estados, cursor e ações reais de Sincronizações |
| `app/pages/customers.vue` | `frontend/app/pages/health/index.vue` | arquétipo de lista administrativa (tabela, toolbar, filtros) | inbox operacional (`severity`/`type` na URL), empty state positivo, deep-links; sem restore/bulk |
| `app/pages/settings.vue` e subpáginas | `frontend/app/pages/clients/[id].vue` e `frontend/app/components/clients/*` | navbar, toolbar, navegação, largura e cards | seções reais, contratos da API e limpeza de segredos A1 |
| `app/pages/settings.vue` e subpáginas | `frontend/app/pages/admin/index.vue` | arquétipo Settings | gate `ADMIN` + 2FA e operações administrativas reais |
| `app/pages/inbox.vue` | `frontend/app/pages/notes/index.vue` e `NotesWorkspace.vue` | mestre–detalhe, painel redimensionável e slideover móvel | rota canônica, filtros e cursor da API |
| `app/components/inbox/InboxList.vue` | `frontend/app/components/notes/NotesCatalog.vue` | seleção, lista, densidade e estados | metadados fiscais sanitizados e paginação por cursor |
| `app/components/inbox/InboxMail.vue` | `frontend/app/components/notes/NotesDetail.vue` | painel de detalhe, navbar e ações | download auditado e ausência de XML bruto |
| `app/pages/inbox.vue` | `frontend/app/pages/notes/[accessKey].vue` | comportamento de seleção e detalhe | chave de acesso canônica e retorno à lista |

`nuxt.config.ts`, `server/api/*`, dados de demonstração e rotas externas não possuem destino: a aplicação permanece SPA estática, same-origin, autenticada por Fortify/Sanctum e sem mocks Nitro.

## Classificação das diferenças

### Texto, dado ou integração permitida

- Tradução para pt-BR e locale do Nuxt UI.
- Rotas, títulos, ícones semanticamente necessários e dados reais.
- `useApi`, Sanctum, middleware de autenticação e contratos Laravel.
- Loading, vazio, erro e sucesso exigidos pelos endpoints reais.

### Exceção técnica obrigatória

- SSR, CORS e `server/api` da referência são substituídos pela SPA estática same-origin.
- Troca de equipe é removida para impedir troca arbitrária de `office_id`.
- Paginação local de Customers é substituída por paginação server-side.
- Lista local de Inbox é substituída por cursor e rota canônica da nota.
- Paleta livre, links externos, avatares remotos e toast demonstrativo de cookies são removidos por não terem função real.
- Gráfico, período e variação comercial são omitidos quando a API não fornece série ou métrica real.
- XML, PFX, senha, PEM, chave privada, cookies e tokens nunca entram em DOM, fixture ou evidência.

### Deriva a remover

- Wrappers ou presets que ocultem classes, slots, ordem ou props presentes na origem.
- Composições próprias quando existe arquétipo direto no template.
- Cards, toolbars, filtros, ações e breakpoints reposicionados sem exceção obrigatória.
- Estado assíncrono que colapse a geometria ou trate erro como lista vazia.

## Regra de execução

Para cada arquivo: **copiar o código da referência → adaptar somente conteúdo e integração autorizados → conferir diff origem/destino → registrar cada exceção não textual**. Reimplementar de memória, substituir por componente apenas equivalente ou simplificar a composição é reprovação.

## Exceções auditadas — fundação e shell

| Destino | Diferença não textual | Regra que exige a diferença | Evidência |
|---|---|---|---|
| `app/app.vue` e `error.vue` | `UApp` recebe `pt_br`; metadados são do produto | idioma pt-BR e acessibilidade dos componentes internos | `lang="pt-BR"`, locale estática do Nuxt UI e typecheck |
| `app/app.config.ts` | mantém somente paletas `green`/`zinc` | presets não podem ocultar classes literais da origem | configuração visual coincide com a referência |
| `layouts/default.vue` | destinos e grupos são computados a partir de permissões | perfis e 2FA; nenhuma ação proibida pode aparecer | `mainDestinations`, `quickActions` e testes de permissão |
| `layouts/default.vue` | toast demonstrativo de cookies e links de código-fonte foram omitidos | ausência de função real no produto interno | nenhum endpoint ou requisito correspondente |
| `OfficeIdentity.vue` | gatilho visual sem `UDropdownMenu` nem chevron | tenancy: escritório vem da sessão e não pode ser trocado | botão replica avatar, dimensões, classes e collapse de `TeamsMenu` |
| `UserMenu.vue` | paleta livre, Billing, Templates e links externos removidos | identidade visual fixa e ausência de função real | permanecem gatilho, largura, aparência claro/escuro e logout real |
| `NotificationsSlideover.vue` | ícone sem avatar e estados loading/erro/vazio adicionados | alertas operacionais não possuem remetente e vêm de duas APIs reais | mesmo slideover/lista/ritmo; `Promise.allSettled` e erros sanitizados |
| `useDashboard.ts` | rotas, gates e limpeza de sessão ampliados | autorização, 2FA e não retenção de UI entre identidades | atalhos condicionados e `sessionEpoch` |

## Exceções auditadas — dashboard, listas, Settings e Inbox

| Destino | Diferença não textual | Regra que exige a diferença | Evidência |
|---|---|---|---|
| `pages/index.vue` e `components/home/*` | gráfico, seletor de período e vendas foram substituídos por alertas e totais operacionais | não existe série temporal nem métrica comercial real no contrato da API | mesmos containers, grade e tabela; snapshots por zona em claro/escuro |
| `pages/clients/index.vue` | paginação TanStack local foi substituída por query e metadados server-side | conjunto de clientes é paginado pela API e isolado pelo escritório | footer e `UPagination` preservados; query `q/page` testada |
| listas administrativas | seleção, visibilidade de colunas e exclusão em massa foram omitidas | nenhuma ação em massa funcional existe no MVP | classes literais do `UTable` permanecem em cada arquivo e overlays usam ações reais |
| listas administrativas | loading, vazio e erro são estados distintos; falha preserva dados anteriores | erro de rede não pode ser apresentado como conjunto vazio | nove cenários E2E em `list-states.spec.ts` |
| `clients/[id].vue` | seções usam query `section` em vez de subrotas | mantém URL direta sem multiplicar endpoints ou páginas artificiais | toolbar Settings e links Resumo/Estabelecimentos/A1/Sincronização |
| componentes de Cliente | ações de cabeçalho ficam no conteúdo do `UPageCard` horizontal | `UPageCard` não possui slot `links`; o padrão oficial usa conteúdo direto | botões Adicionar/Substituir renderizados e modal A1 coberto visualmente |
| `admin/index.vue` | toolbar omitida | há uma única seção administrativa real no MVP | gate ADMIN+2FA ocorre antes do conteúdo; perfis não autorizados redirecionam |
| `NotesWorkspace.vue` | filtros fiscais ocupam o corpo do painel mestre e paginação usa cursor | catálogo real exige filtros e cursor da API | mestre–detalhe, painel adjacente e slideover preservados |
| páginas de Notas | nome do auto-import é `NotesWorkspace`, sem prefixo duplicado | o Nuxt colapsa `notes/NotesWorkspace.vue` para esse nome | rota canônica voltou a renderizar e passa nos testes autenticados |
| arquivos visuais | atributos `data-testid` foram adicionados | captura por zonas sem seletor dependente de classes internas do Nuxt UI | atributos não aplicam CSS nem alteram geometria; 43 snapshots sintéticos |

`.reference/nuxt-dashboard-template` permaneceu com índice e worktree limpos durante a aplicação. O diretório está ignorado pelo Git principal, excluído pelo `.dockerignore` e fora do contexto `frontend/` usado no build; nenhum import de runtime aponta para ele.

## Checklist por arquivo

- [ ] Origem e bloco exato registrados na matriz.
- [ ] Ordem dos componentes e blocos igual à origem.
- [ ] Slots (`header`, `body`, `footer`, `leading`, `right`, `content`) preservados.
- [ ] Props visuais, variantes e tamanhos preservados.
- [ ] Classes, gaps, paddings, bordas, cantos e larguras preservados.
- [ ] Breakpoints, collapse, painel móvel e ausência de overflow verificados.
- [ ] Posição, prioridade e comportamento de ações preservados.
- [ ] Loading, vazio, erro, sucesso e dados anteriores diferenciados.
- [ ] Foco, retorno de foco, atalhos e teclado verificados.
- [ ] Textos em pt-BR e componentes internos com locale pt-BR.
- [ ] Permissões `ADMIN`/`OPERATOR`/`VIEWER` derivadas da mesma fonte tipada.
- [ ] Nenhum dado demonstrativo, mock Nitro ou material sensível copiado.
- [ ] Toda diferença não textual possui classificação e justificativa.
- [ ] Lint, typecheck, testes, build e comparação visual executados.
