## MODIFIED Requirements

### Requirement: Dual visualização módulo e empresa

O produto SHALL manter as carteiras de monitoramento por módulo e SHALL oferecer o eixo por empresa, em que o resumo do cliente lista os processos monitorados daquele CNPJ com os mesmos labels canônicos do rail (Simples Nacional, MEI condicional, DCTFWeb, FGTS Digital, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias, Cadastro e Vínculos, Processos Fiscais). O resumo SHALL também apresentar, em bloco semanticamente separado, os processos operacionais ativos do Work vinculados à empresa. Abrir uma carteira por módulo MUST NOT remover o acesso ao detalhe por empresa, abrir um processo operacional MUST NOT alterar evidência fiscal e vice-versa.

#### Scenario: Carteira por módulo permanece

- **WHEN** o usuário navega para uma carteira por módulo (ex.: Simples Nacional)
- **THEN** a lista de empresas daquele módulo continua disponível

#### Scenario: Resumo da empresa agrupa processos

- **WHEN** o usuário abre `/monitoring/clients/:id` (overview)
- **THEN** a superfície principal lista processos monitorados (não uma tabela genérica de snapshots como peça única)
- **AND** cada item com detalhe disponível navega para a seção correspondente do mesmo cliente

#### Scenario: Resumo da empresa agrupa processos canônicos

- **WHEN** o usuário abre `/monitoring/clients/:id` (Dashboard/overview)
- **THEN** a superfície principal lista processos monitorados com labels canônicos do Monitoramento
- **AND** cada item com detalhe disponível navega para a seção correspondente do mesmo cliente
- **AND** o overview NÃO lista Pendências, Execuções, Achados nem Renúncias

#### Scenario: Sem inventar evidência

- **WHEN** um processo monitorado não tem projeção/evidência local para o cliente
- **THEN** o resumo MUST NOT inventar status “Em dia”
- **AND** MAY omitir o processo ou marcá-lo como sem evidência local

#### Scenario: Processos operacionais ativos da empresa

- **WHEN** a empresa possui processos Work a fazer, em progresso ou impedidos
- **THEN** o overview apresenta um bloco separado “Trabalho operacional” com processo, competência, progresso e prazo
- **AND** cada item navega para o detalhe operacional correspondente
- **AND** o bloco oferece acesso à visão Processos filtrada pela empresa

#### Scenario: Falha parcial do Work

- **WHEN** a leitura local dos processos operacionais falha
- **THEN** o overview mantém as evidências e os módulos fiscais carregados
- **AND** informa indisponibilidade apenas no bloco Trabalho operacional

#### Scenario: Overview não dispara integração externa

- **WHEN** o usuário abre o overview da empresa
- **THEN** a leitura dos processos operacionais usa somente a API local Work tenant-scoped
- **AND** MUST NOT disparar consulta SERPRO, SEFAZ ou provider MEI
