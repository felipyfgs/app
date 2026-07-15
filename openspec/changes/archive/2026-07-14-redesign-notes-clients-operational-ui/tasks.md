## 1. Notas — layout e hierarquia de informação

- [x] 1.1 Definir no código o layout escolhido (tabela full-width + detalhe **ou** inbox com painel mestre ≥ 36%) e ajustar `NotesWorkspace` / páginas
- [x] 1.2 Atualizar `NotesCatalog` para priorizar número, papel, contraparte (nome), competência, valor e status; CNPJ/chave como secundários
- [x] 1.3 Completar `NotesDetail` com partes nome+CNPJ, locais, cStat, chave copiável; manter download XML auditado
- [x] 1.4 Revisar filtros: busca/placeholder orientados a triagem; manter URL de filtros e seleção
- [x] 1.5 Empty state do painel direito útil (instrução clara; sem espaço “morto” enganoso)

## 2. Notas — API e tipos

- [x] 2.1 Confirmar payload de `GET /notes` e detalhe incluem campos enriquecidos; ajustar Resource/array se faltar
- [x] 2.2 Atualizar `NfseNote` em `frontend/app/types/api.ts` e formatadores/labels se necessário
- [x] 2.3 Garantir que listagem não vaze XML ou segredos

## 3. Clientes — API de listagem operacional

- [x] 3.1 Auditar `ClientController@index` / serialização atual (credential_summary, establishments)
- [x] 3.2 Enriquecer DTO de listagem com resumo A1 e, se viável sem N+1, status de captura/sync por cliente
- [x] 3.3 Expor contagens agregadas para KPIs (total, com A1, sem A1, a vencer; opcional bloqueados) sem inventar métricas
- [x] 3.4 Testes de feature: isolamento por office, ausência de segredos, contagens coerentes

## 4. Clientes — UI posto de captura

- [x] 4.1 Reestruturar lista de Clientes para tabela densa (padrão template) com colunas razão, CNPJ, A1, captura/sync, ações
- [x] 4.2 Implementar faixa de KPIs no topo da lista com dados reais da API
- [x] 4.3 Busca única (nome/CNPJ) alinhada ao backend
- [x] 4.4 Chips semânticos A1 (válido / ausente / a vencer / vencido) e captura; ações por papel
- [x] 4.5 Preservar detalhe Settings (cadastro, est., certificado, sync) via navegação da linha

## 5. Consistência, a11y e testes

- [x] 5.1 Verificar hierarquia navbar/toolbar (uma primária; filtros no corpo)
- [x] 5.2 Viewport mobile: colunas prioritárias; detalhe slideover/rota
- [x] 5.3 Atualizar testes unitários de filtros/labels e e2e smoke das rotas `/notes` e `/clients` se existirem
- [x] 5.4 Revisar contraste e foco teclado nos novos chips/tabelas

## 6. Fechamento

- [x] 6.1 Validar com dados do piloto (cliente 8 / notas reais) visualmente
- [x] 6.2 Marcar tarefas e deixar change pronta para archive após aceite
