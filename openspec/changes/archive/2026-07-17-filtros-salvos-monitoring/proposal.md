## Why

As listas de dados do painel já filtram no servidor, mas o recorte some ao navegar e os operadores repetem os mesmos combos. Falta (1) gravar o estado aplicado com nome e reutilizá-lo, (2) compartilhar com a equipe do Office quando o autor permitir, e (3) filtrar pelas colunas de negócio da tabela — não só um ou dois campos. O núcleo de chips do Monitoramento torna isso possível sem redesenhar a casca.

## What Changes

- Persistência tenant-aware de **filtros salvos** (nome + payload versionado + visibilidade `personal` | `office`) por superfície de lista.
- API CRUD sob `CurrentOffice` / Sanctum; **nunca** confiar `office_id` do client; ownership e share por papel.
- Toolbar: **Salvar**, menu **Filtros salvos** (meus + compartilhados), aplicar / renomear / excluir / alternar compartilhamento, com Nuxt UI já usado no painel.
- Aplicar preset hidrata busca + chips e reutiliza a transação de carga (página 1, uma recarga, limpa seleção).
- Expandir o núcleo de filtros e as configs por lista para expor **todas as colunas de negócio filtráveis** (não colunas de ação/chrome); estender APIs de listagem onde o eixo ainda não existe.
- Disponibilizar o mesmo padrão nas **tabelas de dados relacionadas** que já têm (ou devem ter) filtros: nove listas de Monitoramento, mailbox, clientes, documentos, filas de trabalho e listas work/closing quando usarem toolbar de filtros.
- Troca de Office descarta UI de presets do tenant anterior e não mistura payloads.
- Non-goals: PLATFORM_ADMIN vendo presets de tenant; ranges/multi-select avançados além do necessário por coluna; faceting; URL state de filtros no monitoring; deps npm novas; feature flags novas ON por default; mutações fiscais; live smoke SERPRO.

## Capabilities

### New Capabilities

- `filtros-salvos-lista`: armazenamento, API e regras de ownership/compartilhamento de presets nomeados por superfície e Office.

### Modified Capabilities

- `tabela-filtros-monitoramento`: campos = colunas filtráveis por lista; salvar/aplicar/compartilhar na toolbar; extensão do padrão às demais tabelas de dados do painel que usam filtros server-side.

## Impact

- `backend/`: migration `saved_list_filters` (ou equivalente); model/policy/controller; extensão de DTOs/queries de listagem (portfolio, guides, registrations, tax-processes, mailbox, clients, notes, work) para eixos de coluna; testes de isolamento Office e ownership.
- `frontend/`: `data-table-filter` (kinds se necessário), adapters por superfície, `ModuleToolbar` + menus/modais salvos, configs `fields` por página; migração de docs/work/clients para o contrato de chips onde couber.
- Reuso: chips atuais, `MonitoringFilterValue`/normalizers, Nuxt UI (Modal, DropdownMenu, Switch, Form).
- Sem vault/segredos nos payloads; sem `office_id` em query/body de autoridade.
