## ADDED Requirements

### Requirement: Consulta CCMEI limitada ao cliente do escritório atual
O sistema SHALL consultar `CCMEI/DADOSCCMEI122` exclusivamente para um cliente
vinculado ao `CurrentOffice`, usando a rota oficial `Consultar`, versão `1.0` e
`pedidoDados.dados` vazio. A requisição SHALL ignorar qualquer `office_id` ou
CNPJ fornecido pelo navegador como fonte de autoridade.

#### Scenario: Consulta autorizada de cliente do escritório
- **WHEN** uma pessoa autorizada solicita a consulta para um cliente do escritório atual
- **THEN** o sistema monta o envelope com a identidade fiscal desse cliente e registra uma evidência tenant-scoped

#### Scenario: Cliente de outro escritório
- **WHEN** uma pessoa solicita a consulta usando o identificador de cliente que não pertence ao `CurrentOffice`
- **THEN** o sistema rejeita a operação sem chamar o driver SERPRO e sem revelar dados do outro escritório

### Requirement: Dados CCMEI normalizados e sanitizados
O sistema SHALL decodificar o campo oficial `dados` somente quando ele contiver
JSON válido e SHALL persistir e devolver apenas os campos explicitamente
permitidos para a apresentação. QR code/Base64, CPF completo, credenciais,
tokens e payload bruto SHALL NOT aparecer em resposta HTTP, estado da UI ou logs.

#### Scenario: Retorno oficial válido com QR code
- **WHEN** o driver retorna dados CCMEI válidos contendo QR code e dados do empresário
- **THEN** a projeção apresenta somente o resumo permitido e descarta o QR code e identificadores sensíveis

#### Scenario: Retorno ambíguo ou inválido
- **WHEN** o campo `dados` não puder ser decodificado ou não satisfizer a estrutura mínima
- **THEN** o sistema registra falha sanitizada e apresenta estado de erro sem salvar ou expor o payload recebido

### Requirement: Execução fail-closed e homologação rastreável
O sistema SHALL usar a capability SERPRO centralizada e permanecer bloqueado
quando kill switch, allowlist ou capability não autorizarem a operação. Os testes
automatizados SHALL usar somente fake ou simulated; homologação externa SHALL
ser registrada como pendente enquanto não houver credencial e autorização
operacional explícitas.

#### Scenario: Capability desabilitada
- **WHEN** a capability de Simples/MEI estiver desabilitada ou fora da allowlist
- **THEN** o sistema não realiza chamada externa e retorna erro seguro e acionável

#### Scenario: Validação automatizada sem rede
- **WHEN** a suíte automatizada executa a consulta CCMEI
- **THEN** ela usa fixture fake/simulated e comprova ausência de chamada HTTP real

### Requirement: Interface de consulta e histórico por cliente
O sistema SHALL disponibilizar no detalhe do cliente uma ação de consulta e um
histórico sanitizado do CCMEI com estados de carregamento, vazio, erro e sucesso.
A interface SHALL identificar que os dados são uma consulta e não uma emissão ou
atestado de validade jurídica.

#### Scenario: Histórico vazio
- **WHEN** o cliente ainda não possui consulta CCMEI registrada
- **THEN** a interface explica a ausência e oferece somente a ação de consulta autorizada

#### Scenario: Consulta bem-sucedida
- **WHEN** uma consulta CCMEI termina com dados normalizados
- **THEN** a interface atualiza o histórico do mesmo cliente sem exibir material sensível
