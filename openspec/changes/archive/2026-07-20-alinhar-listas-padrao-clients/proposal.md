## Why

O kit `Shell*` (change `shell-ui-kit`) já existe, mas as ~85 pages do painel ainda não seguem de forma uniforme o padrão ouro de lista refatorado em `/clients` (`ClientCatalogList` + `ShellDataTable` `monitoring-compact`). Sem adoção catalogada, UI continua inconsistente (UTable residual, chrome misto, densidade/footer divergentes). Agora o inventário está fechado e a régua é explícita.

## What Changes

- Adotar `/clients` como contrato visual/comportamental de **lista admin** (anatomia KPI → toolbar → tabela/cards → footer `mt-auto`; per-page 10/20/50; densidades alinhadas).
- **W0:** auditar e alinhar listas que já usam `ShellDataTable` / ModuleTable (exports, closing, imports, work, offices, syncs, health, carteiras monitoring).
- **W1:** migrar superfícies que ainda montam `<UTable`: docs Catalog/ByClient, ClientListDashboard, admin/serpro catalog|contracts|usage, settings/usage, tabelas de seção em `monitoring/clients/[clientId]`.
- **W2:** unificar chrome de lista autenticada em `ShellPagePanel` + `ShellPageNavbar` (+ Refresh/Back quando couber).
- Expandir gate automatizado de migração (zero `<UTable` nas superfícies cobertas + presença de `ShellDataTable`).
- **Não** inclui nesta change: mestre–detalhe (mailbox, work queue, calendar), settings forms (TeamList/Departments), home/auth/stubs — changes follow-up.

## Capabilities

### New Capabilities

- `listas-padrao-clients`: contrato de adoção do padrão ouro de lista `/clients` no painel — inventário de superfícies, requisitos de anatomia/chrome/densidade/paginação, migração das listas com UTable residual e gate de não-regressão.

### Modified Capabilities

- _(nenhuma em `openspec/specs/` — `panel-shell-kit` ainda só na change ativa `shell-ui-kit`; esta change consome esse kit sem alterar o contrato do componente base)_

## Impact

- **Código:** `apps/web/app/pages/**` (listas A/B), `apps/web/app/components/docs/{Catalog,ByClient}.vue`, `ClientListDashboard.vue`, `monitoring/clients/[clientId].vue` (seções tabulares), chrome via `components/shell/*`, testes `tests/unit/shell-list-migration-gate.test.ts`.
- **API / backend:** nenhuma.
- **Non-goals:** SERPRO live, mutações fiscais, outbound; SplitWorkspace/mailbox/work queue; FormSection/modals settings; redesign de KPIs de domínio de clientes.

### Dependências entre changes

- **Nível:** `C1`
- **Bases estáveis:** template `@ 0f30c09`; referência ouro `ClientCatalogList`
- **Depende de:** `shell-ui-kit`
- **Capability/contrato:** `panel-shell-kit` (ShellDataTable, Footer, PagePanel, ListEmpty, LoadError)
- **Marco exigido:** `apply` (kit implementado no working tree; archive preferível mas não bloqueia se o código do kit já estiver presente)
- **Relação:** `bloqueante`
- **Desbloqueia:** changes futuras de docs-workspace split, settings-shell, inbox-shell
- **Paralelismo:** não editar artifacts de `shell-ui-kit`; mudanças de domínio MEI/orquestrador em paralelo se não tocarem as mesmas pages de lista
