## ADDED Requirements

### Requirement: Painel de contexto do atendimento é colapsável
A superfície `/communication` SHALL expor o painel Contexto do contato de forma colapsável. Em viewports `lg` e maiores, o painel MUST ocupar coluna desktop somente quando aberto; quando fechado, a timeline MUST expandir horizontalmente. Em viewports menores que `lg`, o mesmo estado MUST controlar um `USlideover`. O painel MUST iniciar fechado ao selecionar uma conversa.

#### Scenario: Operador fecha o contexto no desktop
- **WHEN** há conversa selecionada em viewport `lg+` e o painel Contexto está aberto
- **THEN** ao acionar o controle de fechar (ícone de usuário na timeline ou fechar no header do painel) o painel some da coluna direita e a timeline ganha o espaço

#### Scenario: Operador abre o contexto no desktop
- **WHEN** há conversa selecionada em viewport `lg+` e o painel Contexto está fechado
- **THEN** ao acionar o ícone de usuário na navbar da timeline o painel Contexto aparece à direita sem navegar para outra rota

#### Scenario: Mobile usa slideover com o mesmo estado
- **WHEN** o operador em viewport `<lg` aciona o ícone de usuário com conversa selecionada
- **THEN** o sistema abre o slideover de contexto e Escape ou o botão voltar/fechar o fecha

### Requirement: Controle de contexto usa ícone de usuário Nuxt UI
A navbar da timeline SHALL exibir um `UButton` com ícone de usuário (`i-lucide-user`) sempre visível (desktop e mobile) para alternar o contexto. O botão MUST indicar visualmente o estado aberto e MUST NOT ficar oculto em breakpoints `xl+`.

#### Scenario: Toggle permanente na conversa
- **WHEN** uma conversa está aberta na timeline
- **THEN** o controle de contexto com ícone de usuário está disponível na navbar independentemente da largura da tela
