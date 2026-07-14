## 1. Baseline e rastreabilidade literal

- [x] 1.1 Registrar commit, árvore de arquivos e versões de Nuxt/Nuxt UI da referência `.reference/nuxt-dashboard-template`
- [x] 1.2 Criar matriz origem→destino para todas as rotas e componentes, apontando o arquivo ou bloco exato que será copiado
- [x] 1.3 Classificar cada diferença atual como texto/dado/integração permitida, exceção técnica obrigatória ou deriva a remover
- [x] 1.4 Documentar a regra de execução “copiar o código da referência → adaptar conteúdo e integração → conferir diff”, proibindo reimplementação equivalente
- [x] 1.5 Definir checklist por arquivo para estrutura, ordem, slots, props visuais, classes, dimensões, breakpoints e interação idênticos

## 2. Harness visual determinístico

- [x] 2.1 Configurar Playwright com projetos `1440×900`, `390×844` e `360×800`, versões fixadas e execução reproduzível
- [x] 2.2 Criar fixtures de rede sintéticas, tipadas e sanitizadas para perfis `ADMIN`, `OPERATOR` e `VIEWER`, sem `server/api`
- [x] 2.3 Estabilizar fontes, animações, datas, relógios, color mode e dados antes de capturar screenshots
- [x] 2.4 Implementar captura por zonas de shell, navbar, toolbar, conteúdo, tabela/detalhe e overlays
- [x] 2.5 Definir tolerâncias de comparação que não permitam mascarar geometria, espaçamento, largura ou hierarquia
- [x] 2.6 Adicionar varredura que rejeite PFX, senha, chave privada, PEM, XML, cookie, token, `vault_object_id` e resposta ADN nos artefatos
- [x] 2.7 Capturar o frontend atual apenas como diagnóstico de deriva, sem promovê-lo a baseline aprovada

## 3. Fundação visual e shell copiados

- [x] 3.1 Recriar `app.vue`, CSS, `app.config.ts` e configuração visual a partir da cópia dos arquivos correspondentes da referência
- [x] 3.2 Recriar `layouts/default.vue` a partir da cópia literal do template e adaptar somente rotas, textos e permissões
- [x] 3.3 Recriar a identidade do escritório a partir de `TeamsMenu.vue`, preservando markup, dimensões e classes e removendo apenas a troca de tenant
- [x] 3.4 Recriar `UserMenu.vue` a partir do código de referência, adaptando usuário real, textos e logout sem alterar o modelo visual
- [x] 3.5 Recriar `NotificationsSlideover.vue` a partir do código de referência e ligar alertas sanitizados sem alterar shell, slots ou proporções
- [x] 3.6 Recriar command palette, atalhos e ações rápidas a partir do layout da referência, filtrando conteúdo pelas permissões tipadas
- [x] 3.7 Comparar o diff estrutural do shell com a origem e registrar cada exceção não textual
- [x] 3.8 Validar shell expandido, recolhido e móvel em claro/escuro, teclado e três perfis

## 4. Dashboard copiado

- [x] 4.1 Recriar `pages/index.vue` a partir da cópia do dashboard da referência, mantendo navbar, ações, toolbar e ordem de blocos
- [x] 4.2 Recriar a grade de indicadores a partir de `HomeStats.vue`, substituindo apenas títulos, ícones semanticamente necessários, links e valores reais
- [x] 4.3 Preservar classes, variantes, cantos, gaps, superfícies e proporções dos cards da referência
- [x] 4.4 Adaptar o conteúdo posterior aos indicadores usando os mesmos contêineres e ritmo, sem inventar gráficos ou métricas
- [x] 4.5 Validar loading, dados, vazio, erro com dados anteriores e alertas sem alterar a geometria base
- [x] 4.6 Aprovar comparação visual e estrutural do dashboard em desktop, mobile e 360 px

## 5. Listas administrativas copiadas de Customers

- [x] 5.1 Recriar Clientes a partir de `pages/customers.vue`, preservando navbar, faixa utilitária, tabela, controles, footer e classes
- [x] 5.2 Adaptar a tabela de Clientes à paginação server-side sem alterar seu modelo visual
- [x] 5.3 Recriar modal de Cliente a partir de `customers/AddModal.vue`, adaptando campos, Zod, erros 422 e textos
- [x] 5.4 Recriar Exportações a partir do mesmo arquétipo de Customers, mantendo estrutura e trocando apenas colunas, estados e ações funcionais
- [x] 5.5 Recriar Sincronizações a partir do mesmo arquétipo de Customers, mantendo paginação por cursor e erro sanitizado
- [x] 5.6 Manter seleção, controle de colunas ou ações em massa somente quando houver função real, preservando espaço/modelo visual ou registrando exceção
- [x] 5.7 Validar tabelas, modais, paginações e ações de linha nos estados loading, vazio, erro, pronto, falho, expirado e bloqueado
- [x] 5.8 Aprovar comparação visual e estrutural das três listas em desktop, mobile e 360 px

## 6. Settings copiado para Cliente e Administração

- [x] 6.1 Recriar o detalhe de Cliente a partir de `pages/settings.vue` e suas subpáginas, preservando navbar, toolbar, container e cards
- [x] 6.2 Adaptar seções para Resumo, Estabelecimentos, Certificado A1 e Sincronização sem alterar o modelo de navegação Settings
- [x] 6.3 Recriar formulários de estabelecimento e A1 a partir dos padrões de formulário da referência, preservando composição e limpando segredos
- [x] 6.4 Recriar Administração a partir do arquétipo Settings, mantendo o gate `ADMIN`+2FA antes de renderizar conteúdo
- [x] 6.5 Comparar diffs estruturais de Cliente/Administração e registrar somente exceções obrigatórias de autorização ou segurança
- [x] 6.6 Aprovar comparação visual, abertura direta de seções, teclado e responsividade das telas Settings

## 7. Notas copiadas de Inbox

- [x] 7.1 Recriar o catálogo de Notas a partir de `pages/inbox.vue`, preservando composição mestre–detalhe e painéis redimensionáveis
- [x] 7.2 Recriar a lista a partir de `InboxList.vue`, adaptando campos fiscais e cursor sem alterar markup, seleção e densidade
- [x] 7.3 Recriar o detalhe a partir de `InboxMail.vue`, adaptando metadados e download auditado sem renderizar XML bruto
- [x] 7.4 Manter a rota canônica `/notes/:accessKey`, filtros e retorno sem alterar o comportamento visual de seleção
- [x] 7.5 Preservar painel adjacente em desktop e slideover correspondente em mobile, com foco e fechamento equivalentes
- [x] 7.6 Validar seleção anterior/próxima, teclado, loading, vazio, erro, não encontrado e acesso de outro escritório
- [x] 7.7 Aprovar comparação visual e estrutural de Notas em desktop, mobile e 360 px

## 8. Revisão literal e exceções

- [x] 8.1 Executar diff assistido origem→destino de todos os arquivos copiados e revisar cada alteração não textual
- [x] 8.2 Remover wrappers, presets ou componentes equivalentes que impeçam verificar identidade com o código da referência
- [x] 8.3 Restaurar classes, slots, props visuais, ordem e breakpoints divergentes que não tenham exceção obrigatória
- [x] 8.4 Consolidar o registro final de exceções com arquivo, bloco, regra que exige a mudança e evidência
- [x] 8.5 Confirmar que `.reference/` permanece somente leitura e fora do runtime/build de produção

## 9. Aceite e evidências

- [x] 9.1 Executar ESLint, typecheck, Vitest, testes de componentes e build Nuxt
- [x] 9.2 Executar Playwright autenticado para Dashboard, Clientes, Cliente, Notas, Exportações, Sincronizações e Administração nos três perfis
- [x] 9.3 Executar regressão visual em `1440×900` e `390×844` para todas as rotas e overlays cobertos
- [x] 9.4 Executar inspeção de overflow, ações obrigatórias e teclado em `360×800`
- [x] 9.5 Executar varredura de segredos em screenshots, traces, snapshots, relatórios e bundle de produção
- [x] 9.6 Produzir relatório final com matriz origem→destino, diffs, screenshots, exceções e comandos reproduzíveis
- [x] 9.7 Reconciliar as evidências com `refactor-frontend-dashboard-ux` e `build-nfse-adn-capture-system` sem marcar critérios não comprovados
