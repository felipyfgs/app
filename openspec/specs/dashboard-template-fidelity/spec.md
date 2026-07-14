# Dashboard Template Fidelity

## Purpose

Fidelidade literal e auditável ao template Nuxt UI Dashboard fixado em .reference/nuxt-dashboard-template (commit 0f30c09).

## Requirements

### Requirement: Derivação literal e rastreável do código de referência
O sistema MUST derivar as telas autenticadas por cópia direta do código fixado em `.reference/nuxt-dashboard-template` e SHALL manter uma matriz que vincule cada arquivo destino ao arquivo ou bloco exato usado como origem.

#### Scenario: Componente equivalente disponível
- **WHEN** uma área do produto possui composição equivalente no template
- **THEN** a implementação começa pelo código copiado e preserva estrutura, ordem, componentes Nuxt UI, slots, props visuais, classes, dimensões, hierarquia, densidade e interação da referência

#### Scenario: Divergência necessária
- **WHEN** uma regra funcional, de segurança, tenancy, autorização ou contrato server-side exige comportamento diferente
- **THEN** a matriz registra a linha ou bloco alterado, a justificativa e a evidência de que manter literalmente o código seria incorreto ou inseguro

#### Scenario: Divergência não registrada
- **WHEN** a revisão encontra diferença visual ou interacional sem justificativa registrada
- **THEN** a diferença é tratada como defeito e impede o aceite da rota

#### Scenario: Reimplementação equivalente
- **WHEN** o produto usa wrapper, markup, slots, classes ou composição apenas equivalentes ao template, mas não derivados diretamente dele
- **THEN** a rota é reprovada e deve ser refeita a partir da cópia do código de referência

### Requirement: Shell fiel sem troca arbitrária de escritório
O sistema MUST reproduzir o shell do template com `UDashboardGroup`, sidebar recolhível e redimensionável, busca global, navegação vertical, rodapé do usuário, command palette e slideover global, sem permitir seleção de escritório fora da associação autenticada.

#### Scenario: Identidade do escritório
- **WHEN** o usuário visualiza o cabeçalho da sidebar expandida ou recolhida
- **THEN** a identidade do escritório mantém dimensões, alinhamento e tratamento visual do seletor de equipe da referência, mas não oferece troca de tenant

#### Scenario: Sidebar móvel
- **WHEN** um destino é selecionado em viewport móvel
- **THEN** a sidebar fecha e o painel de destino ocupa a área principal como no template

#### Scenario: Navegação por perfil
- **WHEN** sidebar, command palette ou ações rápidas são abertas
- **THEN** todos os destinos são derivados das mesmas permissões tipadas e nenhuma ação proibida é apresentada

### Requirement: Paridade estrutural por arquétipo de tela
O sistema SHALL implementar cada rota autenticada copiando o arquétipo correspondente do template: dashboard, lista administrativa, mestre–detalhe ou settings, e alterando apenas conteúdo e integrações necessárias.

#### Scenario: Dashboard operacional
- **WHEN** o usuário abre o dashboard
- **THEN** navbar, ações compactas, toolbar, grade de indicadores e conteúdo subsequente seguem a composição de `pages/index.vue` e `HomeStats.vue`, usando somente métricas reais

#### Scenario: Lista administrativa
- **WHEN** o usuário abre Clientes, Exportações ou Sincronizações
- **THEN** ação primária, faixa utilitária, tabela, ações de linha, estados e paginação seguem a composição de `customers.vue` sem substituir paginação server-side por paginação local

#### Scenario: Catálogo de notas
- **WHEN** o usuário abre Notas em desktop ou mobile
- **THEN** a experiência segue o mestre–detalhe de Inbox, usando painéis adjacentes no desktop e slideover no mobile, com rota canônica e dados fiscais sanitizados

#### Scenario: Detalhe e administração
- **WHEN** o usuário abre o detalhe de Cliente ou Administração
- **THEN** navbar, toolbar de seções, largura do conteúdo e cards seguem o arquétipo Settings da referência

### Requirement: Fidelidade visual mensurável
O sistema MUST manter idênticos tipografia, escala, espaçamento, largura, densidade, bordas, cantos, superfícies, props visuais, breakpoints e cores da referência e MUST validar elementos críticos por comparação visual determinística.

#### Scenario: Comparação por zonas
- **WHEN** uma screenshot é comparada ao baseline
- **THEN** shell, header, toolbar, conteúdo e overlays são avaliados separadamente e nenhuma zona crítica excede a tolerância documentada

#### Scenario: Conteúdo dinâmico
- **WHEN** valores, datas ou textos variáveis impedem comparação estável
- **THEN** o teste usa dados sintéticos determinísticos ou mascara apenas a região dinâmica sem ignorar sua geometria

#### Scenario: Alteração visual intencional
- **WHEN** uma mudança aprovada altera o baseline
- **THEN** a atualização inclui justificativa, diff revisável e atualização da matriz de paridade

### Requirement: Interações e estados equivalentes
O sistema SHALL manter posição e prioridade de ações, abertura e fechamento de overlays, loading, feedback, foco, atalhos e navegação por teclado equivalentes ao padrão de referência.

#### Scenario: Ação primária e ações rápidas
- **WHEN** uma tela oferece criação ou outra ação primária
- **THEN** a ação ocupa o mesmo nível hierárquico do template e ações adicionais aparecem em dropdown ou faixa apropriada, condicionadas ao perfil

#### Scenario: Estado assíncrono
- **WHEN** uma leitura está carregando, vazia, falhou ou preserva dados anteriores após erro
- **THEN** a tela apresenta estado distinto e acessível, sem confundir erro com ausência de dados

#### Scenario: Modal ou slideover
- **WHEN** o usuário abre e fecha um overlay
- **THEN** o foco fica contido, retorna ao acionador e o comportamento de teclado segue o componente correspondente do template

### Requirement: Paridade responsiva e acessível
O sistema MUST manter a experiência utilizável e fiel em `1440×900`, `390×844` e largura de `360 px`, sem depender somente de cor ou mouse.

#### Scenario: Desktop
- **WHEN** a aplicação é exibida em `1440×900`
- **THEN** sidebar, painéis, tabelas, toolbars e overlays preservam proporções e alinhamentos da referência

#### Scenario: Mobile
- **WHEN** a aplicação é exibida em `390×844`
- **THEN** navegação, ações principais, identidade, estados e detalhes permanecem acessíveis na composição móvel correspondente

#### Scenario: Largura mínima
- **WHEN** um fluxo principal é executado em largura de `360 px`
- **THEN** o documento não apresenta rolagem horizontal e nenhuma ação obrigatória fica inacessível

#### Scenario: Operação por teclado
- **WHEN** o usuário navega sem mouse
- **THEN** foco visível, ordem de tabulação, menus, tabelas selecionáveis e overlays permitem concluir o fluxo coberto

### Requirement: Evidência visual sanitizada e reproduzível
O sistema MUST gerar evidências de fidelidade com fixtures sintéticas e MUST impedir que screenshots, traces, snapshots ou relatórios contenham material fiscal ou credencial sensível.

#### Scenario: Execução limpa
- **WHEN** a suíte visual é executada em ambiente limpo com versões fixadas
- **THEN** as mesmas rotas, estados, viewports, fontes e dados determinísticos produzem resultados reproduzíveis

#### Scenario: Varredura de artefatos
- **WHEN** screenshots, traces e relatórios são gerados
- **THEN** uma verificação rejeita PFX, senha, chave privada, PEM, XML fiscal, cookies, tokens, `vault_object_id` e resposta ADN bruta

#### Scenario: Dependência de produção
- **WHEN** o frontend é compilado para produção
- **THEN** fixtures, interceptadores e baselines não criam rota mock, dependência de runtime ou processo Node adicional

### Requirement: Aceite completo por rota
O sistema SHALL considerar uma rota fiel somente após cumprir a matriz estrutural, os testes funcionais e acessíveis, as comparações visuais aplicáveis e a revisão de divergências autorizadas.

#### Scenario: Critério incompleto
- **WHEN** uma rota passa no screenshot mas falha em comportamento, acessibilidade, segurança ou responsividade
- **THEN** a rota permanece não concluída

#### Scenario: Aceite final
- **WHEN** todas as rotas passam nas camadas estrutural, visual, interacional e responsiva
- **THEN** o relatório final lista evidências, exceções aprovadas e comandos reproduzíveis de validação
