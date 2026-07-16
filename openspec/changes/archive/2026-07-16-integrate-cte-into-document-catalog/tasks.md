## 1. Reconciliar a fonte de verdade OpenSpec

- [x] 1.1 Corrigir em `complete-cte-capture-with-distdfe-autxml-and-import` o proposal, design, delta `frontend-dashboard-experience` e tarefas que colocam onboarding CT-e em Configurações, registrando Documentos `/docs/catalog` como superfície canônica sem alterar captura, NSU ou backend.
- [x] 1.2 Validar as changes `complete-cte-capture-with-distdfe-autxml-and-import` e `integrate-cte-into-document-catalog`, confirmando que não resta requisito concorrente que trate CT-e como módulo ou página de Configurações.

## 2. Integrar o contexto CT-e ao catálogo

- [x] 2.1 Extrair de `frontend/app/pages/settings/cte.vue` a orientação `autXML`, CNPJ copiável, metadados sanitizados e estados de loading/erro/vazio para componente reutilizável do catálogo, sem carregar ou duplicar saúde de cursor.
- [x] 2.2 Migrar pendências/quarentena e ações CT-e para o contexto de `/docs/catalog`, preservando permissões ADMIN/OPERATOR/VIEWER, feedback sanitizado e atualização após resolução ou importação.
- [x] 2.3 Integrar o contexto CT-e ao `NotesWorkspace` quando `kind=CTE` ou deep-link equivalente estiver ativo, mantendo a tabela de documentos como foco e filtros reproduzíveis na URL.
- [x] 2.4 Garantir que troca explícita de escritório invalide dados e respostas CT-e em andamento pelo `sessionEpoch`, sem repopular CNPJ, documentos ou pendências do tenant anterior.

## 3. Remover a superfície CT-e separada

- [x] 3.1 Remover CT-e das seções de Configurações, sidebar, command palette e destinos rápidos, mantendo apenas Documentos/Catálogo como navegação documental.
- [x] 3.2 Converter `/settings/cte` em redirect compatível com `replace` para `/docs/catalog?kind=CTE`, preservando somente query params aceitos e sem renderizar conteúdo Settings.
- [x] 3.3 Remover do middleware de autenticação a exceção específica de `/settings/cte` e confirmar os gates normais de `/settings`, `/admin` e `/docs/catalog`.
- [x] 3.4 Atualizar deep-links de Sincronizações, Exportações e demais superfícies para `/docs/catalog?kind=CTE`, mantendo cursor/`maxNSU`/quiet/`656` exclusivamente em Sincronizações.
- [x] 3.5 Atualizar README e inventário de rotas do frontend para descrever CT-e como tipo de Documentos, não como item de Configurações.

## 4. Verificação da migração

- [x] 4.1 Atualizar testes unitários de navegação, rotas e middleware para provar ausência de destino CT-e separado e redirect legado correto.
- [x] 4.2 Migrar os E2E de CT-e para `/docs/catalog`, cobrindo catálogo misto NF-e/NFC-e/CT-e, orientação `autXML`, pendências, permissões por papel e troca de escritório.
- [x] 4.3 Adicionar asserções de que `/settings/cte` termina no catálogo filtrado, nenhuma navegação aponta para a rota antiga e nenhum material PFX/senha/PEM aparece no contexto CT-e.
- [x] 4.4 Executar formatter/lint, typecheck, testes unitários e E2E direcionados e build do frontend, corrigindo regressões antes de marcar a migração concluída.
- [x] 4.5 Executar `openspec validate integrate-cte-into-document-catalog --json` e registrar a change como pronta para apply/archive somente após todos os artefatos e testes passarem.
