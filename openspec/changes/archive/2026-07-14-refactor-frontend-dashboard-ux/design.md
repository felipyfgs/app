## Contexto

O frontend é uma SPA Nuxt 4/Nuxt UI 4 autenticada por Fortify e Sanctum. Ele já adaptou o shell do template oficial Nuxt UI Dashboard fixado no commit `0f30c09`: `UApp`, `UDashboardGroup`, sidebar recolhível e redimensionável, command palette, menu do usuário, atalhos, tema e slideover de alertas. A lacuna não está no shell, mas na aplicação incompleta dos padrões internos do template às telas de domínio.

A leitura integral do template identificou cinco padrões de composição relevantes:

| Padrão do template | Elementos essenciais | Aplicação no produto |
|---|---|---|
| Shell do dashboard | sidebar recolhível/redimensionável, busca global, navegação vertical, rodapé do usuário e slideover global | Todas as rotas autenticadas |
| Dashboard analítico | navbar, ações compactas, toolbar para filtro global, grade contínua de indicadores e conteúdo analítico | Dashboard operacional |
| Lista administrativa | ação primária na navbar; faixa utilitária no topo do corpo; tabela estilizada; seleção/ações; paginação no rodapé | Clientes, Exportações e Sincronizações |
| Mestre–detalhe | painel de lista redimensionável no desktop; painel de detalhe adjacente; slideover no mobile; seleção por teclado | Catálogo e detalhe de Notas |
| Settings/seções | navbar, toolbar de subnavegação, conteúdo central com largura limitada, `UPageCard` naked como cabeçalho e subtle como seção | Detalhe de Cliente e Administração |

O frontend atual aplica o shell, mas mantém arquivos extensos — especialmente `clients/[id].vue` —, filtros de notas dentro de um card isolado, detalhes sempre em nova página e estilos de tabela diferentes do template. A mudança deve corrigir essas diferenças sem copiar mocks, regras comerciais ou dados demonstrativos.

## Objetivos / Não-objetivos

**Objetivos:**

- Reproduzir a gramática de layout e interação do template, adaptada ao domínio fiscal interno.
- Atribuir a cada rota um padrão de composição explícito, evitando decisões ad hoc durante a implementação.
- Manter uma hierarquia uniforme de ações, filtros, conteúdo, paginação e feedback.
- Preservar filtros e seleção relevantes na URL para retorno e compartilhamento de contexto.
- Reduzir páginas monolíticas em componentes de domínio coesos e testáveis.
- Garantir paridade funcional em desktop e mobile, acessibilidade por teclado e proteção de dados sensíveis.

**Não-objetivos:**

- Obter cópia pixel a pixel ou importar código demonstrativo sem adaptação.
- Alterar API, modelos persistidos, regras fiscais, tenancy ou autorização.
- Adicionar gráficos sem série temporal real fornecida pela API.
- Introduzir SSR, processo Node em produção, mocks de API ou dependências de runtime do template.
- Criar troca de escritório no cabeçalho: o escritório ativo vem da sessão e não pode ser escolhido como um “team” arbitrário.
- Exibir XML bruto, PFX, senha, chave privada, PEM ou resposta remota não sanitizada.

## Decisões

### 1. Preservar o shell já adaptado e corrigir seus contratos

O `default.vue` continuará usando `UDashboardGroup unit="rem"`, sidebar `collapsible` e `resizable`, navegação vertical com tooltip/popover quando recolhida, command palette e rodapé com usuário. O componente hoje derivado de `TeamsMenu` será tratado como identidade do escritório ativo, sem dropdown de troca. Toda entrada da sidebar deverá possuir equivalente na command palette; ações rápidas serão incluídas somente quando permitidas.

O slideover global será de alertas operacionais, carregado ao abrir, fechado ao mudar de rota e composto por itens acionáveis com título, resumo sanitizado, severidade, horário e destino. Falha ao carregar alertas não poderá ser representada como “nenhum alerta”.

Alternativa rejeitada: recopiá-lo do template. Isso eliminaria adaptações corretas de autenticação, escritório e permissões.

### 2. Usar uma hierarquia de ações derivada do template

1. A navbar contém título, sidebar collapse e no máximo uma ação primária textual no desktop.
2. Ações globais compactas, como atualizar e abrir alertas, usam botão `ghost` com tooltip e nome acessível.
3. A toolbar é reservada para subnavegação ou filtros que mudam todo o painel, como período do dashboard; não é obrigatória em toda lista.
4. Busca, filtros da tabela, seleção em massa e controle de colunas ficam numa faixa utilitária flexível no topo do corpo, como em `customers.vue`.
5. Ações secundárias por registro ficam em menu de reticências alinhado ao fim; a ação dominante pode permanecer como link ou botão direto.
6. Ações destrutivas usam modal com alvo, consequência, cancelar neutro e confirmar em `error`.

Isso substitui a decisão anterior, excessivamente genérica, de sempre colocar filtros na toolbar.

### 3. Padronizar tabelas no estilo visual do template sem importar paginação client-side

Todas as tabelas administrativas usarão o mesmo contrato visual do template: cabeçalho `bg-elevated/50`, bordas separadas, cantos no primeiro/último cabeçalho, divisores de linhas e ausência de borda redundante na última linha. Colunas terão prioridade explícita para mobile.

A interação será adaptada aos endpoints reais:

- Clientes mantém paginação numerada server-side.
- Notas e Sincronizações mantêm paginação por cursor.
- Exportações mantém atualização periódica somente enquanto houver itens pendentes.
- Seleção em massa e seletor de colunas só serão implementados quando houver ação funcional correspondente; não serão copiados apenas por aparência.

Alternativa rejeitada: usar `getPaginationRowModel` do exemplo sobre dados parciais. Isso produziria paginação enganosa sobre uma única página retornada pela API.

### 4. Dashboard operacional usa indicadores contínuos, não cards soltos nem dados artificiais

Os indicadores usarão `UPageGrid` e `UPageCard` com o tratamento visual de `HomeStats`: ícone em superfície primária, rótulo curto, valor em destaque e continuidade visual em telas largas. A ordem será operacional: bloqueios/falhas, sincronizações vencidas ou pendentes, certificados, documentos/clientes e exportações.

Alertas acionáveis aparecerão após os indicadores. Atualização manual e horário da informação serão visíveis. A toolbar de período só será adicionada se a API passar a fornecer métricas temporais. O gráfico `HomeChart` e suas dependências Unovis não serão incorporados enquanto existirem apenas totais pontuais.

### 5. Notas adotam mestre–detalhe responsivo com URL canônica

O catálogo será o painel mestre. Em `lg` ou maior, poderá ocupar aproximadamente 30–40% e ser redimensionável dentro de limites; o detalhe ocupará o painel restante. Sem seleção, o segundo painel mostrará um estado ilustrativo discreto. A seleção será navegável por teclado e permanecerá visível na lista.

Em viewport menor que `lg`, o detalhe abrirá em `USlideover`. Diferentemente da inbox demonstrativa, a seleção será refletida na rota canônica `/notes/:accessKey`, porque notas precisam de link compartilhável, retorno confiável e auditoria de download. Fechar o detalhe retornará a `/notes` preservando os filtros e o cursor representável.

O detalhe apresentará metadados e projeções, nunca o XML bruto. O download original continua sendo uma ação explícita, autorizada e auditada.

### 6. Detalhe de Cliente usa o padrão Settings, não mestre–detalhe

O detalhe de Cliente continuará em página dedicada porque representa um fluxo operacional extenso. A navbar exibirá contexto e retorno. Uma `UDashboardToolbar` conterá subnavegação horizontal para `Resumo`, `Estabelecimentos`, `Certificado A1` e `Sincronização`, com rota/query reproduzível e itens condicionados ao perfil.

Cada seção usará largura adequada ao conteúdo e composição do padrão Settings:

- `UPageCard variant="naked"` para título, descrição e ação da seção.
- `UPageCard variant="subtle"` para formulário, lista ou metadados relacionados.
- separadores entre campos ou blocos equivalentes.
- componentes separados para estabelecimento, credencial e disparo inicial.

O resumo manterá um onboarding de quatro etapas, mas não fingirá conclusão: para quem não pode gerir A1, a etapa indicará “gerenciado por ADMIN” sem ser marcada como incompleta por falta de permissão.

### 7. Clientes, Exportações e Sincronizações adotam lista administrativa

**Clientes:** busca por nome/raiz, total, estado e ação de abrir. O cadastro curto permanece em modal usando `UForm`, schema Zod para validação local e mapeamento de erros 422 da API.

**Exportações:** a ação primária abre modal; a lista evidencia status, escopo resumido, quantidade/tamanho, expiração e download. A criação desabilita submissão duplicada e o polling preserva dados atuais durante falha transitória.

**Sincronizações:** a lista mantém detalhe em slideover, pois o histórico é auxiliar e não precisa de rota pública individual no MVP. O item e o detalhe destacam resultado, origem, intervalo de NSU, páginas, documentos, horários e falha sanitizada. Bloqueio de cursor não oferecerá salto de NSU.

### 8. Administração usa página Settings com conteúdo restrito

A área administrativa usará navbar, toolbar de subnavegação quando houver mais de uma seção e corpo central de largura limitada. Conta/2FA, certificados e operação segura serão seções independentes. Um acesso sem papel ou confirmação adequada não renderizará o conteúdo protegido antes de apresentar o estado restrito ou redirecionar ao desafio.

### 9. Formulários seguem o contrato do template com adaptação à API

Formulários usarão `UForm`, schema Zod quando a regra também puder ser validada localmente, `UFormField name`, mensagens associadas ao campo e ação de submit com loading. Erros 422 continuarão mapeados aos campos; falhas gerais serão exibidas por toast ou alerta sanitizado. Ao fechar ou concluir formulários sensíveis, referências a arquivo PFX e senha serão limpas.

Modais são reservados a criação curta e confirmação. Fluxos multietapas permanecem em página. Botões de cancelamento devem ser `neutral` e `subtle` ou `ghost`; a ação final usa cor semântica.

### 10. Tema mantém estrutura do template com paleta controlada pelo produto

Modo claro/escuro, `theme-color`, fonte Public Sans e tokens semânticos do Nuxt UI serão preservados. A aplicação manterá cores primária e neutra definidas no produto. O seletor livre de todas as cores presente no template não é requisito da refatoração; caso permaneça, não pode comprometer contraste e deverá ser testado. A preferência claro/escuro deverá persistir pelo mecanismo do Nuxt Color Mode.

### 11. Acessibilidade e responsividade são critérios de aceite

- Controles apenas com ícone terão `aria-label`; tooltip complementa, mas não substitui o nome acessível.
- Modais, popovers e slideovers manterão foco contido e retorno ao acionador.
- Linha selecionável terá semântica e alternativa por teclado; não dependerá somente de clique ou cor.
- Estados semânticos combinarão texto/ícone com cor.
- Em mobile, identidade, status e ação principal permanecem visíveis; dados secundários migram para detalhe.
- Nenhum fluxo principal exigirá rolagem horizontal da página em 360 px; tabelas podem ter área interna rolável apenas quando não houver representação segura mais compacta.

## Matriz de telas resultante

| Rota/área | Padrão | Ação primária | Filtros/contexto | Detalhe |
|---|---|---|---|---|
| `/` | Dashboard analítico | Novo cliente, se permitido | Sem período até existir série temporal | Alertas levam ao módulo responsável |
| `/clients` | Lista administrativa | Novo cliente | Faixa utilitária no corpo | Navega para página dedicada |
| `/clients/:id` | Settings/seções | Depende da seção | Toolbar com subnavegação | Página dedicada reproduzível |
| `/notes` + `/notes/:accessKey` | Mestre–detalhe | Baixar XML no detalhe | Filtros do catálogo no painel mestre | Painel adjacente desktop; slideover mobile |
| `/exports` | Lista administrativa | Nova exportação | Escopo definido no modal | Estado na própria linha |
| `/syncs` | Lista administrativa | Atualizar | Paginação por cursor | Slideover |
| `/admin` | Settings/seções | Depende da seção | Toolbar se houver múltiplas seções | Conteúdo central restrito |

## Riscos / Trade-offs

- **Duplicação com tarefas 9.2–9.10 do change ativo** → Manter matriz de rastreabilidade e marcar tarefas somente após evidência compartilhada.
- **Mestre–detalhe de Notas aumentar complexidade de roteamento** → Tratar a rota como fonte de verdade e testar abertura direta, seleção, retorno e mobile.
- **Filtros por cursor não serem totalmente restauráveis** → Preservar filtros na URL; só persistir cursor se o contrato da API permitir retomada segura, sem inventar paginação offset.
- **Abstração excessiva** → Extrair componente compartilhado apenas com dois consumidores reais ou responsabilidade transversal inequívoca.
- **Regressão funcional durante reorganização** → Migrar módulo a módulo, manter composables tipados e executar testes antes de remover o código anterior.
- **Personalização livre de cores reduzir contraste** → Preferir paleta fixa do produto; se mantida, limitar opções a combinações validadas.
- **Mais componentes e arquivos** → Trade-off aceito por responsabilidades menores e testes focados.

## Plano de migração

1. Criar matriz de rastreabilidade entre rotas, padrões desta decisão e tarefas 9.2–9.10.
2. Consolidar shell, estados compartilhados e estilo visual de tabelas.
3. Refatorar o dashboard sem adicionar métricas ou gráficos artificiais.
4. Refatorar Clientes e seu detalhe Settings/seções.
5. Implementar mestre–detalhe roteado de Notas.
6. Refatorar Exportações, Sincronizações e Administração.
7. Validar lint, typecheck, componentes e Playwright em 1440×900 e 390×844, além de checagem manual a 360 px.
8. Remover código obsoleto somente após confirmar ausência de consumidores e paridade funcional.

Cada módulo forma uma unidade reversível. Não há migração de banco ou contrato de API a reverter.

## Questões em aberto

- Confirmar durante o apply se o backend de Notas oferece cursor serializável/reutilizável; caso não ofereça, a URL preservará filtros, mas o retorno recarregará a primeira página.
- Confirmar se haverá seções administrativas adicionais no MVP; sem elas, `/admin` não precisa de toolbar vazia.

