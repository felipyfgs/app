## 1. Base visual compartilhada

- [x] 1.1 Criar presets globais de tabela derivados de `customers.vue` e `HomeSales.vue` do template oficial.
- [x] 1.2 Migrar todas as instâncias de `UTable` do painel para os presets compartilhados, preservando apenas variações justificadas.
- [x] 1.3 Padronizar carregamento, erro, estado vazio e rodapé sem mensagens vazias duplicadas.
- [x] 1.4 Traduzir rótulos técnicos expostos nas tabelas para pt-BR.

## 2. Paginação e agregação no servidor

- [x] 2.1 Fazer a listagem de clientes consumir somente a página solicitada, com filtros e ordenação enviados à API.
- [x] 2.2 Paginar a listagem de exportações na API e no frontend.
- [x] 2.3 Substituir a agregação em memória de notas por cliente por consulta agregada e paginada no banco.
- [ ] 2.4 Cobrir paginação, filtros, metadados e isolamento por escritório com testes de backend.

## 3. Comportamento das telas

- [x] 3.1 Alinhar clientes ao arquétipo `customers.vue`, com ação principal na navbar e estado de página refletido na URL.
- [x] 3.2 Implementar paginação real nas tabelas de exportações e notas por cliente.
- [ ] 3.3 Alterar o catálogo de documentos para cursor incremental, acumulando resultados sem deslocamento aleatório.
- [ ] 3.4 Adaptar o detalhe de documentos ao arquétipo mestre–detalhe do Inbox no desktop e slideover no mobile.
- [ ] 3.5 Garantir paginação ou ação explícita de carregar mais nas tabelas operacionais restantes.

## 4. Qualidade

- [ ] 4.1 Atualizar e executar os testes unitários e de integração afetados.
- [ ] 4.2 Executar verificação de tipos e lint dos arquivos alterados, distinguindo falhas preexistentes.
- [ ] 4.3 Ampliar a cobertura visual das rotas com tabelas, incluindo viewport de 360 px.
- [ ] 4.4 Validar o build de produção do frontend em diretório de saída gravável.
