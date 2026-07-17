## Context

O frontend Nuxt 4 apresenta os módulos em uma barra horizontal rolável e na sidebar. Simples/MEI e DCTFWeb/MIT ainda usam `submodule`/`tab` na query string para identificar uma visão que deve ser compartilhável como página. As APIs Laravel já recebem `submodule` como filtro e não precisam mudar.

A implementação deve preservar o arquétipo Settings do dashboard fixado: `UDashboardPanel`, `UDashboardNavbar`, `UDashboardToolbar`, `UNavigationMenu highlight` e conteúdo interno. O painel permanece SPA estática, autenticada por Sanctum e tenant-aware pela sessão; nenhuma rota recebe `office_id`.

## Goals / Non-Goals

**Goals:**

- Tornar módulo e submódulo identidade de rota por caminho.
- Preservar a barra rolável, seus rótulos e ordem, a sidebar e o acesso direto já conhecido pelos usuários; resumir somente os itens internos de `Monitoramento` na sidebar.
- Preservar links antigos por redirecionamento, descartando filtros antigos da URL.
- Cobrir a transformação de rotas com testes unitários.

**Non-Goals:**

- Alterar endpoints, DTOs, read models, filas ou persistência do backend.
- Alterar papéis, tenancy, feature flags, bilhetagem ou mutações fiscais.
- Executar chamadas SERPRO reais, habilitar canais ou tratar atividades externas/jurídicas.

## Decisions

### Navegação visual permanece inalterada

`MonitoringModuleNav` continuará usando `UNavigationMenu highlight` com todos os módulos visíveis e comportamento rolável, no mesmo local do arquétipo Settings. A faixa horizontal preserva os rótulos e a ordem anteriores, omite ícones e reduz somente gap/padding via `ui` prop nos slots oficiais `root`, `link` e `linkLabel`. A tipografia não recebe override local e permanece no tamanho padrão `text-sm` definido pelo tema do `UNavigationMenu`, como nas demais navegações do sistema.

No shell, os grupos principais da sidebar preservam ícones para continuarem identificáveis no estado recolhido, enquanto todos os itens filhos omitem ícones, reproduzindo a hierarquia do grupo Settings no template oficial. Os filhos de `Monitoramento` recebem um `sidebarLabel` próprio e resumido (`Resumo`, `Simples/MEI`, `Caixas`, `Vínculos`, `Processos` etc.), sem alterar o rótulo usado na barra horizontal. A transformação de ícones ocorre centralmente em `toNavigationItems`; o catálogo bruto mantém os ícones para command palette e outros contextos. Agrupamentos e overflow “Mais” foram avaliados e rejeitados por alterarem demais o fluxo conhecido.

A lista principal da sidebar é entregue ao `UNavigationMenu` como dois arrays: operação (`Início`, `Trabalho`, `Monitoramento`, `Documentos`, `Operações`) e gestão (`Clientes`, `Configurações`, `Admin`). O separador é produzido pelo próprio componente, como nos grupos do template; grupos vazios são removidos. A command palette continua achatando todos os destinos.

### Ações somente no contexto da tabela

`MonitoringModuleTable` não renderiza mais o slot de ações no `UDashboardNavbar`. As ações de carteira já existentes no corpo seguem o padrão de seleção do arquétipo `customers.vue`: aparecem apenas quando `selectedCount > 0`, respeitam capacidades e exibem a contagem em `UKbd`. O cadastro de cliente permanece na superfície Clientes/ação rápida, fora dos módulos fiscais.

Quando o módulo possui submódulos, o slot `submodules` vem no início do body, antes da toolbar de busca/filtros. Assim o `UNavigationMenu` horizontal forma o primeiro nível, `UTabs` forma o segundo e os controles da carteira iniciam apenas depois dessa hierarquia.

### Submódulo como segmento de rota

As páginas canônicas serão:

- `/monitoring/simples-mei/pgdasd`
- `/monitoring/simples-mei/pgmei`
- `/monitoring/simples-mei/dasn-simei`
- `/monitoring/simples-mei/regime`
- `/monitoring/dctfweb/dctfweb`
- `/monitoring/dctfweb/mit`

O valor enviado à API permanece nos códigos atuais (`PGDASD`, `PGMEI`, `DASN_SIMEI`, `REGIME`, `DCTFWEB`, `MIT`). Um utilitário puro fará a conversão código ↔ slug para evitar regras divergentes entre página, composable e testes.

As rotas legadas `/monitoring/simples-mei` e `/monitoring/dctfweb`, com ou sem `submodule`/`tab`, redirecionarão para o caminho canônico sem query string. Filtros de busca, situação, competência, cliente, status e ordenação ficam em `ref`s locais da instância e continuam sendo enviados somente à API. A alternativa de manter aliases silenciosos foi rejeitada porque permitiria duas URLs públicas para o mesmo estado.

### Arquivos de página canônicos

As implementações atuais de Simples/MEI e DCTFWeb/MIT serão movidas para páginas dinâmicas `[submodule].vue`. Os arquivos de índice antigos ficarão pequenos e terão apenas middleware de redirecionamento. Esse desenho usa o roteamento por arquivos do Nuxt sem introduzir configuração manual de router ou dependência nova.

## Risks / Trade-offs

- [Favoritos antigos passam por uma navegação extra] → redirecionamento client-side imediato e preservação dos filtros úteis.
- [Slug inválido poderia abrir estado ambíguo] → normalização fail-closed para o submódulo padrão e substituição pela URL canônica.
- [Mudança de submódulo pode disparar duas cargas] → sincronização centralizada no composable e teste do contrato de rota antes dos gates.
- [Mudanças concorrentes já presentes nas páginas] → mover o conteúdo atual sem reverter ordenação, tabela ou ações existentes; editar apenas a inicialização/sincronização da rota.

## Migration Plan

1. Introduzir conversão de submódulos e destinos canônicos sem trocar links existentes.
2. Atualizar somente os destinos da navegação existente para os paths canônicos.
3. Mover as duas páginas com submódulos para rotas dinâmicas e criar redirecionamentos legados.
4. Atualizar links internos para os destinos canônicos.
5. Validar unit tests, lint, typecheck, generate e OpenSpec.

Rollback: restaurar os arquivos de página antigos e os destinos anteriores; APIs e dados não sofrem migração.

## Open Questions

Nenhuma para esta change.
