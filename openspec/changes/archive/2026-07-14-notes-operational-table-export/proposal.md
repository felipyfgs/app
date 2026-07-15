## Why

O escritório já captura NFS-e reais e a lista de **Clientes** virou posto denso (tabela, chips, KPIs). **Notas** ainda é mestre–detalhe estilo inbox: o operador não consegue escanear como em Clientes, alternar visão por empresa, selecionar várias notas nem disparar export a partir do catálogo. Exportações existem em `/exports` com ZIP assíncrono, mas sem multi-chave, sem paridade de filtros com o catálogo e sem ponte a partir de Notas. Unificar a UX e fechar o ciclo “filtrar → selecionar → exportar” transforma o painel em posto de trabalho diário, sem inventar analytics.

## What Changes

- Trocar o catálogo de Notas de lista inbox para **tabela administrativa densa** (mesmo arquétipo visual de Clientes / template `customers`), mantendo detalhe em painel/drawer e rota `/notes/:accessKey`.
- Introduzir **tabs de visualização** no shell de Notas: **Por documento** (linhas de NFS-e) e **Por empresa** (agregação por cliente do escritório com drill-down para o filtro).
- Completar filtros de triagem na UI (incl. intervalo de emissão) e manter **URL de filtros + seleção**.
- **Multi-select** de linhas carregadas com ações reais: exportar seleção (lista limitada de chaves) e/ou exportar filtro atual.
- **Alinhar o job de export ZIP** aos filtros do catálogo (`client_id`, `establishment_id`, opcionalmente `access_keys[]` com teto) e oferecer atalho “Exportar” a partir de Notas (ADMIN/OPERATOR).
- Atualizar specs de experiência frontend, catálogo e entrega XML para refletir posto operacional (não só inbox).
- **Não** introduzir relatórios PDF/CSV gerenciais, séries inventadas nem redesenhar Exportações além do necessário para filtros/seleção.

## Capabilities

### New Capabilities

Nenhuma capability de domínio fiscal nova. Reuso de catálogo + ZIP existente.

### Modified Capabilities

- `frontend-dashboard-experience`: Notas como posto operacional com tabela densa, tabs por documento/empresa, multi-select com ação real, ponte de export; detalhe permanece acessível (painel/drawer/rota).
- `fiscal-document-catalog`: listagem e agregação leve por cliente do escritório quando necessário à tab “por empresa”; projeção legível na linha da tabela.
- `xml-delivery`: export ZIP MUST aceitar o mesmo conjunto de filtros do catálogo (incl. cliente/estabelecimento) e MUST permitir escopo por lista limitada de chaves de acesso para seleção em lote.

## Impact

| Área | Efeito |
|------|--------|
| `frontend/` Notas | Recomposição `NotesWorkspace` / catalog → `UTable`; tabs; selection; atalho export |
| `frontend/` Exportações | Consumo de filtros/chaves novos; UX mínima se necessário |
| API notes | Possível endpoint/meta de agregação por cliente; listagem continua cursor |
| API exports + `BuildExportZipJob` | Filtros `client_id`/`establishment_id`/`access_keys`; limites e auditoria |
| Specs frontend + catálogo + xml-delivery | Deltas de requisitos |
| Template Nuxt | Arquétipos customers + settings tabs; sem novo starter |

## Não-objetivos

- Relatórios gerenciais (PDF, planilha analítica, série temporal, mapas).
- Select-all de milhares de notas sem job assíncrono limitado.
- Analytics / KPIs inventados de faturamento.
- Emitir/cancelar NFS-e, DANFSe, portal do cliente final.
- Clonar multi-hub HubStrom ou redesenhar Admin/Syncs além de consistência de shell.
- Trocar stack ou abandonar fidelidade ao template Nuxt UI Dashboard.
- Archive automático da change anterior (pode ser feito em paralelo se desejado).
