## Purpose

Contrato de UX e estado de filtros de listas do painel: padrão ouro (KPI + busca + chips + presets), Filter Lite, sync de URL e integração com `/api/v1/list-filters`.

## Requirements

### Requirement: LFU-01 — Padrão ouro de toolbar de lista
Listas operacionais com dois ou mais eixos de filtro SHALL apresentar, nesta ordem visual: (1) faixa KPI/tabs de resumo quando a superfície tiver contadores; (2) toolbar com busca textual; (3) chips de filtro estruturado via `DataTableFilter*`; (4) ações Limpar tudo, Salvar e Filtros salvos quando houver `surface`; (5) refresh; (6) slot opcional Exibir/export. A toolbar SHALL ser o shell compartilhado (`ListFilterToolbar` ou equivalente), não uma cópia ad hoc por página.

#### Scenario: Superfície monitoring com chips
- **WHEN** o usuário abre uma carteira de monitoramento (ex. parcelamentos) com surface configurada
- **THEN** vê busca, chips adicionáveis, Limpar, Salvar/Filtros salvos, refresh e Exibir quando a tabela suportar colunas

#### Scenario: Lista sem surface de preset
- **WHEN** uma lista operacional usa o shell sem `surface`
- **THEN** as ações Salvar e Filtros salvos ficam ocultas e os demais elementos do padrão ouro permanecem

### Requirement: LFU-02 — Sync de URL em listas operacionais
Listas operacionais SHALL sincronizar `q`, filtros estruturados, `page`, `per_page` e ordenação (`sort`, `sort_direction`) com a query string. Reload e compartilhamento do URL MUST restaurar o mesmo estado de filtro. A aplicação de um preset salvo MUST atualizar a URL. Listas MUST NOT apagar a query após hidratar (exceto params one-shot documentados fora deste contrato, ex. `?new=1`).

#### Scenario: Reload preserva filtros
- **WHEN** o usuário aplica busca e um chip e recarrega a página
- **THEN** busca, chips, página e sort permanecem iguais aos da URL

#### Scenario: Closing/Health deep-link
- **WHEN** o usuário abre closing ou health com query de filtros válida
- **THEN** os filtros são aplicados e a query permanece (não é removida por `router.replace` só de path)

### Requirement: LFU-03 — KPI como filtro de um eixo
Quando existir faixa de contadores/tabs de resumo, selecionar um item SHALL filtrar exatamente o eixo correspondente (ex. situação, triage operacional) e MUST manter os demais filtros. Os contadores SHALL ser calculados com os filtros ativos exceto o próprio eixo do KPI.

#### Scenario: Tab Desconhecido em parcelamentos
- **WHEN** o usuário seleciona a tab/KPI “Desconhecido”
- **THEN** a lista filtra por situação desconhecido, um chip equivalente fica ativo (ou o estado estruturado equivalente) e os demais filtros permanecem

### Requirement: LFU-04 — Filter Lite para listas leves
Settings (equipe, departamentos), admin offices e listas só-busca (ex. modelos de trabalho) MAY usar Filter Lite: busca textual e no máximo um select auxiliar, sem chips nem presets obrigatórios. Filter Lite MUST NOT ser usado em listas operacionais com dois ou mais eixos de filtro de domínio.

#### Scenario: Settings team
- **WHEN** o usuário filtra a equipe por texto e papel
- **THEN** a UI permanece Filter Lite (busca ± select) sem exigir `DataTableFilterRoot`

### Requirement: LFU-05 — Presets via surface e API existente
Presets nomeados SHALL usar `GET/POST/PATCH/DELETE /api/v1/list-filters` com `surface` estável. O payload permanece opaco ao backend. O CRUD de UI SHALL passar por um único composable/caminho compartilhado (`useSavedListPresets` ou wrapper), sem implementação duplicada inline por toolbar.

#### Scenario: Salvar preset monitoring
- **WHEN** o usuário salva o estado atual em uma surface `monitoring.*`
- **THEN** o preset é persistido via API e reaplicável, atualizando chips/URL

### Requirement: LFU-06 — Documentos e Work no padrão ouro
O catálogo de documentos SHALL usar chips no shell (sem painel colapsável de selects com botão “Aplicar”). A fila de trabalho SHALL expor chips (ou equivalente no shell) para os filtros que a API já aceita (`department_id`, assignee, `client_id`, scope), além de tabs de status e busca.

#### Scenario: Docs sem Aplicar
- **WHEN** o usuário altera um filtro estruturado no catálogo de documentos
- **THEN** o filtro aplica-se sem um passo explícito “Aplicar” de formulário legado

#### Scenario: Work queue com departamento
- **WHEN** o usuário adiciona filtro de departamento na fila
- **THEN** a lista e a URL refletem `department_id` e o preset da surface `work.queue` pode capturar esse estado

### Requirement: LFU-07 — Alias de ordenação no backend
Endpoints de lista que aceitam ordenação SHALL tratar `sort_direction` e `direction` como aliases equivalentes (mesmo efeito), preservando o parâmetro canônico documentado na surface sem quebrar clientes existentes.

#### Scenario: Guias com direction
- **WHEN** o cliente envia `direction=asc` ou `sort_direction=asc` em lista de guias (ou equivalente ad hoc)
- **THEN** a ordenação aplicada é a mesma
