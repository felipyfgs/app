## ADDED Requirements

### Requirement: Consultas PNR de renúncia devem possuir adaptação de domínio isolada por escritório

O sistema MUST executar `pnr_contador.consultar_renuncias`,
`pnr_contador.situacao_renuncia` e `pnr_contador.emitir_comprovante` por meio
de um adaptador de domínio que receba o `Office` resolvido no servidor e um
cliente pertencente a esse escritório. A operação
`pnr_contador.solicitar_renuncia` NÃO DEVE ser exposta por esse adaptador nem
por rotas de negócio desta capability.

#### Scenario: Cliente de outro escritório é rejeitado

- **WHEN** uma consulta é solicitada para um cliente que não pertence ao
  `Office` atual
- **THEN** o sistema rejeita a solicitação antes de chamar o executor SERPRO
- **AND** nenhuma evidência ou projeção é persistida

#### Scenario: Solicitação de renúncia não é disponibilizada

- **WHEN** um usuário abre o monitoramento PNR
- **THEN** não existe ação para solicitar renúncia
- **AND** nenhuma chamada com a chave `pnr_contador.solicitar_renuncia` é
  realizada

### Requirement: Respostas PNR devem ser validadas e ter proveniência verificável

O sistema MUST validar a estrutura oficial das respostas antes de criar ou
atualizar projeções. Respostas sintéticas, legadas ou com layout inválido NÃO
DEVEM gerar dados consultáveis.

#### Scenario: Fonte sintética é recebida

- **WHEN** o executor informa proveniência sintética ou legada
- **THEN** o adaptador retorna erro de fonte rejeitada
- **AND** não cria nem atualiza projeções de renúncia

#### Scenario: Resposta válida de TRIAL é recebida

- **WHEN** uma operação manual retorna estrutura oficial válida com
  proveniência `SERPRO_TRIAL`
- **THEN** o sistema persiste apenas os campos e resumo sanitizado permitidos
- **AND** marca a projeção como proveniente de TRIAL, sem classificá-la como
  produção

### Requirement: O painel deve permitir consultas manuais sem expor segredos ou escopo de tenant

O painel MUST oferecer, no detalhe do cliente, consultas manuais para histórico
de renúncias, situação por identificador e comprovante por identificador. A
requisição não DEVE aceitar `office_id`, nem apresentar PFX, token, segredo ou
conteúdo bruto de comprovante.

#### Scenario: Usuário consulta a situação de uma renúncia existente

- **WHEN** um usuário autorizado informa um identificador válido e aciona a
  consulta de situação
- **THEN** a UI mostra o estado de carregamento e o resultado sanitizado ou um
  erro compreensível
- **AND** a requisição é associada ao escritório corrente no servidor

#### Scenario: Nenhuma renúncia está disponível para o cliente piloto

- **WHEN** a consulta de histórico retorna uma lista vazia válida
- **THEN** a UI informa que não há renúncias encontradas
- **AND** não classifica a ausência de eventos como falha técnica

### Requirement: Comprovantes devem ter armazenamento e entrega protegidos

Quando a resposta oficial contiver conteúdo documental, o sistema MUST guardar
os bytes no cofre seguro e disponibilizar somente um descritor auditável pela
API de monitoramento. O conteúdo bruto NÃO DEVE ser incluído em logs, respostas
JSON comuns ou resumos de projeção.

#### Scenario: A emissão retorna um comprovante binário

- **WHEN** `pnr_contador.emitir_comprovante` retorna um documento válido
- **THEN** o conteúdo é encaminhado ao `SecureObjectStore`
- **AND** a projeção armazena somente metadados sanitizados e a referência
  segura ao objeto
