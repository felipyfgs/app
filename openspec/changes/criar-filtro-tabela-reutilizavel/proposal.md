## Why

As listas do Monitoramento já compartilham tabela, paginação e estado aplicado, mas ainda apresentam filtros rápidos e um painel avançado específico do módulo. Um filtro estruturado, reutilizável e nativo em Nuxt UI torna a interação consistente sem alterar os contratos Laravel nem expor o contexto de Office.

## What Changes

- Introduzir um núcleo controlado de filtros de tabela com seleção de campo, chips editáveis e editores para opção, competência e cliente.
- Usar `UPopover` com `UCommandPalette` no desktop e `UDrawer` no mobile, com confirmação explícita e descarte de rascunho ao fechar.
- Migrar as nove listas padronizadas de Monitoramento para campos ordenados aceitos por cada API; busca permanece dedicada e situação passa a ser um chip sincronizado aos KPIs.
- Fazer confirmação, remoção e limpeza reiniciarem a paginação e produzirem uma única recarga server-side; limpar tudo inclui a busca na mesma transação.
- Limpar filtros, rascunhos e rótulos de cliente na troca de Office, sem enviar `office_id` e sem persistir filtros na URL.
- Remover competência apenas da UI de Guias, pois o endpoint atual não aplica esse parâmetro.
- Non-goals: alterar endpoints Laravel, dependências, permissões, ações em massa, filtros negativos/intervalos/múltiplos, faceting, filtros salvos, mailbox, detalhe fiscal, Clientes, Documentos, Trabalho ou Admin.

## Capabilities

### New Capabilities

- `filtro-tabela-reutilizavel`: contrato controlado e responsivo para selecionar, editar, confirmar, remover e limpar filtros estruturados em tabelas Nuxt UI.

### Modified Capabilities

- `tabela-filtros-monitoramento`: substitui filtros rápidos/avançados por campos estruturados ordenados nas nove listas, preservando busca, paginação, seleção e isolamento por Office.

## Impact

- `frontend/app/components/data-table-filter/**`, composables e tipos compartilhados: novo núcleo sem dependências adicionais.
- `frontend/app/components/monitoring/ModuleToolbar.vue`, utilitários e tipos fiscais: adaptação entre chips e o contrato backend-facing.
- `frontend/app/pages/monitoring/**`: configuração explícita por API e limpeza tenant-aware.
- `frontend/tests/unit/**`: testes puros, Nuxt de componente e contratos de superfície/fidelidade.
- Sem alterações em `backend/`, payloads Laravel, rotas canônicas ou regras de autorização.
