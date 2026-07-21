## Purpose

Capability `simples-mei-consult-pending-row` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Linha solicitada entra em skeleton até resultado da consulta

Após o usuário confirmar consulta PGDAS-D ou PGMEI pela UI da carteira `/monitoring/simples-mei` (atalho de linha ou Consultar na seleção), cada `client_id` cujo enqueue foi aceito SHALL entrar em estado pendente. Enquanto pendente, a linha SHALL exibir skeleton (`USkeleton` ou equivalente do design system) nas células de resultado do submódulo ativo, sem colocar a tabela inteira em loading. A célula do cliente (nome/CNPJ) MUST permanecer legível. O atalho de nova consulta naquela linha MUST ficar desabilitado até o estado pendente encerrar. Enqueue rejeitado MUST NOT entrar em skeleton.

#### Scenario: Row consult shows skeleton until run settles

- **WHEN** o usuário confirma a consulta de um cliente na linha e a API aceita o enqueue
- **THEN** a linha desse `client_id` mostra skeleton nas células de resultado
- **AND** as demais linhas da página permanecem com dados atuais (não skeleton)
- **AND** ao atingir status terminal da run (sucesso ou falha), a carteira atualiza e o skeleton da linha é removido

#### Scenario: Bulk consult skeletons only accepted clients

- **WHEN** o usuário confirma Consultar para N selecionados e K enqueues são aceitos
- **THEN** apenas os K `client_id` aceitos entram em skeleton
- **AND** clientes cujo enqueue falhou não entram em estado pendente

#### Scenario: Failed enqueue keeps current row

- **WHEN** a solicitação de consulta falha antes de aceitar o enqueue
- **THEN** a linha permanece com os dados atuais
- **AND** o feedback de erro existente (toast/modal) é preservado
