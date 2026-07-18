## ADDED Requirements

### Requirement: Consulta paginada de pagamentos por período
O monitor SHALL permitir que um usuário autorizado consulte PAGTOWEB 7.1 para o cliente do escritório corrente com intervalo de arrecadação obrigatório, paginação limitada e somente filtros oficiais permitidos.

#### Scenario: Consulta válida com período
- **WHEN** um usuário com permissão de sincronização solicita uma página para um intervalo de arrecadação válido do cliente pertencente ao escritório corrente
- **THEN** o sistema SHALL enfileirar a operação `PAGTOWEB/PAGAMENTOS71`, com o poder `00004` aplicável e sem confiar em `office_id` informado pelo cliente

#### Scenario: Filtro sensível não permitido
- **WHEN** a requisição inclui número de documento ou campo fora da allowlist do monitor
- **THEN** o sistema SHALL rejeitar a requisição sem chamar o SERPRO e sem registrar o valor sensível

### Requirement: Projeção segura de documentos de pagamento
O monitor SHALL armazenar e retornar somente a projeção sanitizada de cada pagamento, com documento mascarado, digest não reversível e metadados fiscais estritamente necessários.

#### Scenario: Pagamentos retornados pela operação
- **WHEN** a operação PAGTOWEB 7.1 conclui com itens válidos
- **THEN** o sistema SHALL disponibilizar a lista paginada com período e proveniência, sem número de documento completo, CPF, CNPJ, token, certificado ou payload externo bruto

#### Scenario: Resposta externa inválida
- **WHEN** o SERPRO retorna estrutura que não pode ser decodificada com segurança
- **THEN** o sistema SHALL registrar uma falha segura e não persistir projeção parcial de documentos

### Requirement: Visibilidade e proveniência no painel
O painel do cliente SHALL exibir o período consultado, o estado da execução e documentos de pagamento mascarados, distinguindo claramente execução simulada de execução real.

#### Scenario: Execução simulada
- **WHEN** a capacidade de guias está configurada como simulada
- **THEN** o painel SHALL identificar a proveniência como simulada e não apresentar o resultado como consulta real

#### Scenario: Acesso fora do escritório
- **WHEN** um usuário tenta consultar ou ler pagamentos de cliente de outro escritório
- **THEN** o sistema SHALL negar o acesso sem revelar informações de pagamento
