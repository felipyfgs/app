## Why

O detalhe fiscal da empresa expõe abas internas (Execuções, Achados, Cadastro e Vínculos, Renúncias, Processos Fiscais) e CCMEI mesmo quando não fazem sentido para o regime — CCMEI não deve aparecer para Simples Nacional “puro”. Além disso, o cadastro em `/clients` não leva o usuário à carteira de monitoramento correta, e hoje não há fluxo honesto para **excluir** um cliente do monitoramento (nem no modal de associação nem no menu de ações da linha).

## What Changes

- **Enxugar o rail** do detalhe `/monitoring/clients/:id`: ocultar na UI as seções Execuções, Achados, Cadastro e Vínculos, Renúncias e Processos Fiscais (rotas profundas podem redirecionar para overview; dados de API não são apagados).
- **CCMEI só para MEI**: a aba/card CCMEI aparece apenas quando o cliente é MEI (`tax_regime` / `clientIsMei`); Simples Nacional sem MEI não vê CCMEI.
- **Pós-cadastro → carteira**: ao criar cliente em `/clients` com regime Simples Nacional, redirecionar para a aba de monitoramento Simples (PGDAS-D); se for MEI, para a aba MEI (PGMEI). Outros regimes mantêm o fluxo atual da ficha.
- **Membros da carteira**: permitir incluir e **excluir** cliente do monitoramento do módulo via modal “Associar clientes” (reincluir quem foi removido; excluir quem está na carteira) e via item **Excluir** no dropdown de ações da linha — em todas as abas/carteiras de monitoramento aplicáveis.
- Membership continua alinhada a `tax_regime` para elegibilidade; a exclusão é um **opt-out explícito por módulo** (fail-closed, tenant-scoped), sem inventar status fiscal nem chamar SERPRO.

Non-goals:
- SERPRO live / mutações fiscais / abrir flags de produção.
- Apagar histórico, snapshots ou o registro CRM do cliente.
- Redesign completo do shell ou das carteiras por módulo.
- Mei/mei-worker no Compose.

## Capabilities

### New Capabilities

- `client-fiscal-rail`: catálogo do rail do detalhe fiscal da empresa (seções visíveis + gate CCMEI por regime MEI).
- `monitoring-portfolio-membership`: inclusão/exclusão explícita na carteira de monitoramento por módulo (modal + ação de linha) e redirect pós-cadastro para a aba correta (SN → PGDASD, MEI → PGMEI).

### Modified Capabilities

- (nenhuma em `openspec/specs/` — contratos novos)

## Impact

- Web: `client-fiscal-detail-navigation.ts`, `[clientId].vue`, overview (`client-monitoring-overview.ts`), `ClientCatalogList` / pós-save do form, modal de associação de clientes ao módulo, builders de colunas/ações (`pgdasd-table`, `pgmei-table`, `dctfweb-table`, demais carteiras).
- API: endpoint(s) tenant-scoped para listar elegíveis, incluir e excluir da carteira por módulo (opt-out); filtro em `ModulePortfolioQueryService` para respeitar exclusões.
- Dados: tabela/coluna de exclusão por `(office_id, client_id, module_key)` — sem remover o cliente do CRM.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `simples-mei-portfolio-regime-scope` (filtro por `tax_regime` SN↔MEI); `company-first-monitoring` / `slim-monitoring-client-pgdasd` (overview/rail — coordenar se ainda ativas no merge)
- Depende de: nenhuma (bloqueante)
- Relação com company-first / slim: `coordenada` no marco `apply` (mesmo `[clientId].vue` / overview)
- Desbloqueia: rail utilizável + carteira com opt-out honesto
- Paralelismo: independente de `pgdasd-history-period-layout` (sem ownership compartilhado de capability)
