## ADDED Requirements

### Requirement: Timeline usa bolhas compactas e semanticamente distintas
A timeline do atendimento SHALL renderizar mensagens inbound à esquerda, outbound à direita e notas internas com tratamento próprio de texto e ícone. Cada bolha MUST ajustar sua largura ao conteúdo até um limite responsivo, MUST apresentar origem, conteúdo e metadados em hierarquia previsível e MUST preservar contraste nos modos light e dark sem depender somente de cor para comunicar direção ou tipo.

#### Scenario: Mensagem curta não ocupa largura excessiva
- **WHEN** uma mensagem de texto curta é exibida em desktop ou mobile
- **THEN** sua bolha ocupa apenas a largura necessária até os limites responsivos e mantém direção, origem, horário e status legíveis

#### Scenario: Conteúdo longo ou rico permanece responsivo
- **WHEN** uma mensagem contém texto longo, citação, anexo, enquete ou outra mídia suportada
- **THEN** a bolha respeita a largura disponível, quebra texto e mantém conteúdo e ações utilizáveis sem overflow horizontal

#### Scenario: Nota interna não depende de cor
- **WHEN** a timeline exibe uma mensagem `INTERNAL`
- **THEN** a bolha apresenta rótulo e ícone de nota interna além da superfície semântica de aviso

### Requirement: Metadados e ações da bolha permanecem acessíveis
A bolha SHALL manter horário, estado editado e receipt próximos ao conteúdo e SHALL expor ações permitidas com nomes acessíveis. Em dispositivos sem hover as ações MUST permanecer alcançáveis; em desktop elas MAY ser progressivas desde que reapareçam por hover e `focus-within` e continuem operáveis por teclado.

#### Scenario: Operador usa ações por teclado
- **WHEN** o foco entra em uma bolha com permissão para reagir ou abrir o menu de ações
- **THEN** os controles ficam visíveis, possuem nome acessível e podem ser acionados sem mouse

#### Scenario: Perfil somente leitura abre a timeline
- **WHEN** um membro sem `communication.reply` visualiza mensagens ricas
- **THEN** conteúdo, origem, horário e status permanecem legíveis e controles de mutação não são exibidos
