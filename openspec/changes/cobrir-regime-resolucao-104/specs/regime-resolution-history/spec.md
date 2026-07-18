## ADDED Requirements

### Requirement: Consulta de resolução de regime por ano

O sistema SHALL executar `regimeapuracao.consultarresolucao` na coordenada
oficial `CONSULTARRESOLUCAO104` somente após receber ano-calendário válido,
validar `textoResolucao` em Base64 e persistir o conteúdo exclusivamente como
evidência protegida do escritório e cliente atuais.

#### Scenario: Resolução válida em ambiente de teste

- **WHEN** o cliente fake/simulated devolve Base64 UTF-8 válida para um ano
- **THEN** o sistema persiste uma evidência autorizada e uma projeção local sem
  executar HTTP real nem revelar o conteúdo em log ou API

#### Scenario: Resposta Base64 inválida

- **WHEN** a resposta não contiver Base64 estrita, UTF-8 válida ou estiver fora
  do limite de tamanho
- **THEN** a consulta falha fechada e não cria projeção ou evidência

### Requirement: Histórico e download tenant-safe da resolução

O sistema SHALL listar e baixar somente descritores de resolução pertencentes
ao `CurrentOffice`; a UI SHALL manter coleta explícita e MUST NOT montar caminho
de cofre, Base64 ou URL de evidência por convenção.

#### Scenario: Cliente de outro escritório

- **WHEN** uma sessão solicita histórico ou download de cliente de outro office
- **THEN** a API responde ausência/negação sem revelar descritor ou abrir o
  cofre
