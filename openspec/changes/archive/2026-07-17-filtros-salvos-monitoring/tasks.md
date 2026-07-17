## 1. Persistência e API de presets

- [x] 1.1 Criar migration `saved_list_filters` (office_id, user_id, surface, name, visibility, schema_version, payload JSON) com índices e model Eloquent.
- [x] 1.2 Implementar API tenant-scoped list/create/update/delete sob Sanctum + CurrentOffice, sem confiar `office_id` do client.
- [x] 1.3 Policies: personal só autor; office listável por membership; publicar office = ADMIN|OPERATOR; VIEWER não publica; ADMIN pode excluir office de terceiros.
- [x] 1.4 Testes PHP de isolamento entre Offices, ownership, VIEWER bloqueado em share e surface mismatch.

## 2. Núcleo de chips e payload

- [x] 2.1 Estender kinds (`text`, `boolean`, `date`/`date_range`) e validação no `data-table-filter` com testes Vitest puros.
- [x] 2.2 Definir `surface` ids, serialização schema_version e adapters to/from payload por família (monitoring, docs, work, clients).
- [x] 2.3 Client API frontend + tipos TypeScript para presets (CRUD e listagem por surface).

## 3. UI salvar / aplicar / compartilhar

- [x] 3.1 Modal Salvar (nome + Switch compartilhar) e menu Filtros salvos (Meus / Equipe) com Nuxt UI, alinhado à toolbar.
- [x] 3.2 Integrar em `ModuleToolbar`: aplicar hidrata q+chips, página 1, uma carga, limpa seleção; limpar cache na troca de Office.
- [x] 3.3 Gerenciar (renomear, excluir, alternar visibility) com confirmação e estados de loading/erro; testes Nuxt/Vitest de emissão única.

## 4. Colunas filtráveis no Monitoramento

- [x] 4.1 Estender `ModulePortfolioFilters` + query para eixos de coluna acordados (coverage, modality, etc.) com testes de filtro server-side.
- [x] 4.2 Atualizar `fields` das nove listas para cobrir colunas de negócio filtráveis; nenhum filtro só decorativo.
- [x] 4.3 Guias, vínculos e processos: client/status/q/source e demais eixos da API; Guias só competência se endpoint aplicar.
- [x] 4.4 Mailbox: busca/triage/client e eixos suportados + surface de presets.

## 5. Demais tabelas de dados

- [x] 5.1 Clientes: adapter + toolbar de presets + fields alinhados aos params de listagem.
- [x] 5.2 Documentos: serializar `NotesFilterState` como payload de surface `docs.catalog` com salvar/aplicar.
- [x] 5.3 Work queue e processos (+ closing se no escopo de filtros URL): adapters e UI de presets sem quebrar estado existente.

## 6. Verificação e encerramento

- [x] 6.1 Backend: `pint --test` e `php artisan test` filtrado (presets + portfolio/listagens tocadas).
- [x] 6.2 Frontend: `pnpm run test:gate`, `generate`, `test:fidelity`, `test:artifacts`.
- [x] 6.3 `openspec validate filtros-salvos-monitoring --strict`, archive/sync das duas capabilities e commit no mesmo dia.
