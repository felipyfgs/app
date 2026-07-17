## 1. Contratos e núcleo compartilhado

- [ ] 1.1 Introduzir tipos discriminados, normalização, unicidade, ordenação e validação de competência com testes Vitest puros.
- [ ] 1.2 Implementar `useDataTableFilters` com rascunho, rótulo de cliente e operações add/edit/remove/clear sem efeitos de consulta.
- [ ] 1.3 Criar os componentes `data-table-filter` com chips acessíveis, editores option/month/client e confirmação explícita.
- [ ] 1.4 Compor seletor responsivo com `UPopover` + `UCommandPalette` no desktop e `UDrawer` no mobile sem overflow.

## 2. Integração do Monitoramento

- [ ] 2.1 Evoluir `MonitoringFilterConfig` para `fields` ordenados e implementar conversores entre chips e `MonitoringFilterValue`, omitindo defaults vazios.
- [ ] 2.2 Adaptar `MonitoringModuleToolbar` para busca com debounce/Enter, situação em chip, KPI sincronizado e limpeza atômica.
- [ ] 2.3 Garantir que confirmar, remover ou limpar reinicie página, execute uma única carga e invalide a seleção sem alterar a URL.

## 3. Migração das listas e tenancy

- [ ] 3.1 Migrar Simples/MEI, DCTFWeb/MIT, Parcelamentos, SITFIS, Declarações e FGTS para os campos aceitos por cada API.
- [ ] 3.2 Migrar Guias apenas com cliente e status de pagamento, removendo competência da UI.
- [ ] 3.3 Migrar Cadastro/Vínculos e Processos apenas com status.
- [ ] 3.4 Limpar filtros, rascunhos e rótulos de cliente na troca de Office antes da nova carga e cobrir ausência de `office_id`.

## 4. Testes e fidelidade

- [ ] 4.1 Cobrir add/edit/confirm/remove, descarte de rascunho, emissão única, competência inválida e variantes desktop/mobile em testes Nuxt.
- [ ] 4.2 Cobrir debounce/Enter, KPI, limpeza transacional, seleção/paginação e superfície dos filtros das nove páginas.
- [ ] 4.3 Confirmar toolbar imediatamente antes de `UTable`, ordem das ações e fidelidade ao arquétipo `customers.vue`.

## 5. Verificação e encerramento

- [ ] 5.1 Executar `pnpm run test:gate`, `pnpm run generate`, `pnpm run test:fidelity`, `pnpm run test:artifacts` e `openspec validate criar-filtro-tabela-reutilizavel --strict`.
- [ ] 5.2 Sincronizar/arquivar a change e commitar no mesmo dia código, specs principais e archive.
