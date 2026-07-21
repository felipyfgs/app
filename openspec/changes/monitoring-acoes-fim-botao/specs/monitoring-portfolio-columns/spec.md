## MODIFIED Requirements

### Requirement: Spine canônica das carteiras por cliente

As carteiras por cliente (DCTFWeb, MIT, PGDAS-D, PGMEI, SITFIS, FGTS, hub Declarações) SHALL exibir a spine compartilhada com Situação primeiro e **Ações por último**. Histórico de busca MUST NOT aparecer na grade; SHALL permanecer apenas no menu da coluna Ações.

Carteiras **com** Declaração / Últ. Declaração (PGDAS-D, DCTFWeb, hub Declarações PGDAS) SHALL usar:
`Situação · [Declaração|Últ. Declaração] · [valores/domínio] · Cliente · Comunicação · Consulta · Ações`.

Carteiras **sem** coluna de declaração (PGMEI, SITFIS, FGTS, MIT) SHALL usar:
`Situação · Cliente · [domínio] · Comunicação · Consulta · Ações`.

Na carteira PGDAS-D, o header da coluna de período SHALL ser **Declaração** (não “Últ. Declaração”), e MUST NOT existir coluna Pagamento separada (pagamento vive em Situação).

#### Scenario: Ordem DCTFWeb com declaração

- **WHEN** o operador abre a carteira DCTFWeb
- **THEN** a ordem começa Situação · Últ. Declaração · Cliente
- **AND** termina em Comunicação · Consulta · Ações
- **AND** não existe coluna Histórico na grade
- **AND** não existe coluna isolada Hist. comunicação na grade

#### Scenario: Exceção PGDAS-D

- **WHEN** o operador abre a carteira PGDAS-D
- **THEN** a ordem é Situação · Declaração · RBT12 · Cliente · Comunicação · Consulta · Ações
- **AND** não existe coluna Pagamento na grade

### Requirement: Coluna Comunicação casada

A coluna **Comunicação** SHALL conter Send (prévia + confirmação de envio manual), Switch que persiste `automatic_requested` para envio automático após consulta agendada, e o ícone de rastreio local — nesta ordem, cada controle com tooltip ou `aria-label` próprio. A grade MUST NOT expor coluna isolada **Envio** nem **Hist. comunicação**. A coluna **Ações** SHALL ser a última da spine e SHALL exibir um botão com rótulo **Ações** que abre o menu de linha (sem ícones de preview/info de comunicação na grade). O envio efetivo via provider MUST permanecer fail-closed (desligado por default). Preferências de canal MUST ser editáveis via item do menu Ações (não somente leitura). O eixo de filtro popover nomeado Envio / `send_status` MAY permanecer com esse rótulo (não é o header da coluna da grade).

#### Scenario: Switch persiste intenção automática

- **WHEN** o operador liga o Switch de envio automático em uma linha elegível
- **THEN** a API persiste `automatic_requested=true` para o módulo/submodule do cliente
- **AND** nenhum provider externo é acionado só pelo toggle

#### Scenario: Send manual com elegibilidade

- **WHEN** o operador confirma Send e há canal elegível e documento local
- **THEN** o sistema enfileira um dispatch de comunicação
- **AND** se o kill-switch do provider estiver off, o envio externo não ocorre

#### Scenario: Automático após consulta agendada

- **WHEN** uma consulta agendada do módulo conclui com sucesso para um cliente com `automatic_requested` e elegibilidade
- **THEN** o sistema enfileira o mesmo tipo de dispatch automático
- **AND** respeita o kill-switch fail-closed

#### Scenario: Ações botão no fim

- **WHEN** o operador vê a coluna Ações de uma carteira padronizada
- **THEN** a coluna é a última da grade
- **AND** a célula contém um `UButton` Nuxt UI com rótulo «Ações» (mesmo padrão das Ações em massa: label + icon, variant subtle) que abre o `UDropdownMenu` de linha
- **AND** não há ícones de mensagem/info de comunicação fora da coluna Comunicação

#### Scenario: Célula Comunicação casada

- **WHEN** o operador vê uma linha de carteira com pipeline de comunicação
- **THEN** a coluna Comunicação exibe Send, Switch e ícone de rastreio na mesma célula
- **AND** o cabeçalho da coluna é Comunicação
