## ADDED Requirements

### Requirement: Manifesto inventaria o catálogo oficial completo
O sistema SHALL manter manifesto versionado e revisável com 119 entradas oficiais, sendo 98 em produção, 19 em prospecção, 1 em construção e 1 cancelada, acompanhado de fonte e data de verificação.

#### Scenario: Integridade do manifesto
- **WHEN** o catálogo é validado no build ou por comando operacional
- **THEN** as contagens, chaves únicas, estados e campos obrigatórios correspondem ao snapshot documental aprovado

### Requirement: Identidade de domínio é separada das coordenadas SERPRO
Cada operação MUST possuir uma `operation_key` estável e coordenadas oficiais versionadas que incluam rota, `idSistema`, `idServico`, `versaoSistema` e poder e-CAC quando aplicável.

#### Scenario: Código oficial muda em nova versão
- **WHEN** uma coordenada SERPRO é substituída em nova vigência
- **THEN** a chave interna permanece estável e o resolvedor escolhe a versão válida para o ambiente e instante da chamada

### Requirement: Inventário não implica suporte
O catálogo MUST separar estado oficial do serviço e estado de suporte da plataforma, e MUST bloquear operações sem adapter implementado mesmo que estejam em produção no SERPRO.

#### Scenario: Serviço oficial inventariado sem adapter
- **WHEN** um job tenta executar uma entrada com suporte `INVENTORIED`
- **THEN** a operação é recusada antes do transporte com motivo `CAPABILITY_NOT_IMPLEMENTED`

#### Scenario: SITFIS implementado sem smoke real
- **WHEN** contract tests e simulador passam mas nenhum smoke produtivo foi executado
- **THEN** SITFIS pode ficar `IMPLEMENTED` e não pode ficar `PRODUCTION_VALIDATED`

### Requirement: Catálogo técnico e catálogo financeiro permanecem distintos
O sistema SHALL relacionar classificação financeira e preços à `operation_key` sem misturar preço, vigência comercial ou consumo com coordenadas de transporte.

#### Scenario: Operação sem preço contratado
- **WHEN** uma operação técnica válida não possui versão de preço real
- **THEN** ela continua executável conforme gates técnicos e seu custo monetário permanece desconhecido

