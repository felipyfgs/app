## ADDED Requirements

### Requirement: Experiência SITFIS apresenta estado e próxima ação
A interface SHALL preservar os arquétipos Settings e lista/detalhe do template oficial e SHALL apresentar processamento, idade, próxima atualização, bloqueio e conclusão em linguagem operacional para o contador.

#### Scenario: Snapshot recente
- **WHEN** o usuário abre SITFIS com resultado verificável dentro do TTL
- **THEN** a tela mostra quando foi atualizado, informa que os dados ainda são recentes e não oferece uma chamada externa redundante

#### Scenario: Relatório em processamento
- **WHEN** existe run SITFIS aguardando a fonte
- **THEN** a tela mostra progresso não bloqueante e uma notificação interna leva ao resultado quando concluir

### Requirement: Erros usam revelação progressiva e sanitizada
A interface MUST mostrar primeiro causa operacional e próxima ação, podendo expor correlação, código e horário em detalhes, mas MUST NOT mostrar token, Termo XML, payload bruto ou material de certificado.

#### Scenario: Autorização indisponível
- **WHEN** SITFIS é bloqueado por contrato, Termo ou procuração
- **THEN** a tela orienta a ação necessária sem revelar credenciais globais ou valores protegidos

### Requirement: Origem não pode ser confundida com validação produtiva
A interface SHALL representar proveniência e verificação retornadas pela API e MUST NOT rotular dados simulados ou não verificados como situação fiscal oficial.

#### Scenario: Ambiente interno simulado
- **WHEN** desenvolvedores acessam SITFIS com driver simulado
- **THEN** a interface identifica o contexto de desenvolvimento e não exibe selo de integração produtiva

