## ADDED Requirements

### Requirement: Contratos oficiais PGDAS-D
O sistema SHALL executar as consultas PGDAS-D 13–16 com as coordenadas, rota e payload oficiais, SHALL rejeitar combinações inválidas de `anoCalendario` e `periodoApuracao` e MUST NOT executar operações de emissão de DAS nesta capacidade.

#### Cenário: Consulta anual produtiva
- **WHEN** o scheduler consulta o PGDAS-D de um cliente para um PA esperado
- **THEN** o sistema envia `CONSDECLARACAO13` com `anoCalendario` do PA congelado no run e interpreta `response.dados`

#### Cenário: Consulta por PA inválida
- **WHEN** uma requisição do serviço 13 contém simultaneamente ano e PA ou não contém nenhum deles
- **THEN** o sistema a rejeita antes do transporte SERPRO

### Requirement: Histórico fiel de declarações e DAS
O sistema SHALL preservar original, retificadoras e DAS observados como operações independentes, MUST NOT depender da ordem da resposta e MUST NOT inventar relação direta entre declaração e DAS.

#### Cenário: Retificadoras fora de ordem
- **WHEN** a SERPRO devolve original e retificadoras em ordem arbitrária
- **THEN** todas são preservadas e a última declaração é escolhida pela transmissão válida, com o número como desempate

#### Cenário: Pagamento não localizado
- **WHEN** `dasPago` é falso
- **THEN** a API informa somente que o pagamento não foi localizado até a consulta

### Requirement: Estado operacional seguro
O sistema SHALL calcular `CURRENT`, `DUE_WITHIN_DEADLINE`, `OVERDUE_NOT_FOUND` ou `UNVERIFIED` para o PA esperado e SHALL registrar a última consulta somente após resposta real, produtiva e corretamente interpretada do serviço 13.

#### Cenário: Declaração atual
- **WHEN** uma consulta produtiva contém declaração para o PA esperado
- **THEN** o estado é `CURRENT`

#### Cenário: Ausência dentro do prazo
- **WHEN** a consulta produtiva não contém o PA e o vencimento confiável ainda não passou
- **THEN** o estado é `DUE_WITHIN_DEADLINE`

#### Cenário: Ausência vencida verificável
- **WHEN** uma consulta produtiva posterior ao vencimento confirma ausência e a versão do calendário é verificada
- **THEN** o estado é `OVERDUE_NOT_FOUND`

#### Cenário: Evidência insuficiente
- **WHEN** a consulta falha, é simulada, contém operação incompleta ou o calendário não é verificado
- **THEN** o estado é `UNVERIFIED`

### Requirement: Documentos protegidos
O sistema SHALL armazenar PDFs dos serviços 14–16 somente no cofre seguro, SHALL persistir apenas metadados sanitizados e MUST NOT expor Base64, caminho ou identificador interno do cofre em banco operacional, logs ou APIs.

#### Cenário: PDF válido
- **WHEN** uma resposta documental contém Base64 válido com assinatura `%PDF` e tamanho permitido
- **THEN** os bytes são gravados no cofre e a resposta persistida contém apenas um descritor autorizado

#### Cenário: PDF inválido
- **WHEN** o conteúdo não é Base64 estrito, excede 10 MiB ou não começa com `%PDF`
- **THEN** nenhum artefato é promovido e a falha sanitizada fica observável

#### Cenário: Abertura do histórico
- **WHEN** o usuário abre o modal ou baixa um artefato existente
- **THEN** nenhuma nova chamada SERPRO é realizada

### Requirement: Projeção idempotente de RBT12
O sistema SHALL extrair RBT12 do extrato 16 no máximo uma vez por referência fiscal única, SHALL diferenciar RBT12 de RBT12 proporcionalizado e SHALL mostrar indisponibilidade em vez de estimar valores.

#### Cenário: Novo DAS
- **WHEN** uma consulta 13 produtiva observa um DAS cuja source key ainda não existe
- **THEN** uma única projeção `PENDING` é reservada e uma consulta 16 é agendada

#### Cenário: Mesma referência
- **WHEN** consultas posteriores observam a mesma referência fiscal
- **THEN** nenhuma nova consulta 16 é agendada

#### Cenário: Retificadora
- **WHEN** muda a última declaração ou sua transmissão para o mesmo PA
- **THEN** a source key muda e permite exatamente uma nova consulta do extrato

#### Cenário: Extrato ambíguo
- **WHEN** o PDF é escaneado, não contém o rótulo exato ou apresenta valores conflitantes
- **THEN** a projeção não contém valor e assume `NOT_FOUND`, `AMBIGUOUS` ou `FAILED`

### Requirement: Superfície PGDAS-D especializada
O sistema SHALL mostrar, nesta ordem, seleção autorizada, Razão social, Última declaração, RBT12, Enviar, Automático, Rastreio, Última consulta e Detalhes, sem alterar as colunas dos demais submódulos.

#### Cenário: Linha da tabela
- **WHEN** a carteira PGDAS-D é carregada
- **THEN** o CNPJ não aparece como coluna, continua pesquisável e os estados usam cor, ícone, tooltip e texto acessível

#### Cenário: Detalhes locais
- **WHEN** o usuário seleciona a lupa de detalhes
- **THEN** o modal apresenta histórico, documentos e observações locais em ordem decrescente sem chamada faturável

### Requirement: Isolamento e permissões
O sistema SHALL obter o escritório exclusivamente de `CurrentOffice`, SHALL rejeitar escopo fornecido pelo cliente e SHALL exigir permissão de sincronização para consultas/documentos faturáveis.

#### Cenário: Cliente de outro escritório
- **WHEN** um recurso PGDAS-D não pertence ao escritório atual
- **THEN** a API não o retorna nem revela sua existência
