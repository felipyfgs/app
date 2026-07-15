## ADDED Requirements

### Requirement: SITFIS executado como fluxo assíncrono correlacionado
O sistema SHALL separar solicitação e emissão do relatório de situação fiscal, persistir protocolo/correlação e respeitar tempo de espera oficial antes da consulta do resultado.

#### Scenario: Relatório ainda processando
- **WHEN** a solicitação foi aceita e o prazo mínimo ainda não transcorreu
- **THEN** o sistema mantém `PROCESSING` e não faz polling agressivo ou faturável

#### Scenario: Resultado disponível
- **WHEN** o serviço de emissão retorna o relatório correspondente
- **THEN** o sistema finaliza a execução e vincula a evidência ao protocolo original

### Requirement: Relatório oficial preservado integralmente
O sistema MUST preservar o artefato oficial com hash, origem e horário antes de normalizar pendências e MUST permitir rastrear cada finding até a evidência.

#### Scenario: Parser encontra pendências
- **WHEN** o relatório bem formado contém itens reconhecidos
- **THEN** o sistema cria findings normalizados sem alterar ou substituir o relatório original

### Requirement: Falha de parsing não descarta evidência
O sistema MUST manter relatório legível recebido mesmo quando versão, layout ou item for desconhecido, marcando análise `ATTENTION` e bloqueando conclusões não sustentadas.

#### Scenario: Layout novo
- **WHEN** o relatório oficial muda e o parser não reconhece uma seção
- **THEN** o artefato permanece armazenado, a execução sinaliza contrato alterado e nenhuma pendência é silenciosamente omitida como regular

### Requirement: Cache e validade são visíveis
O sistema SHALL respeitar cache oficial e TTL interno, mostrando data da consulta e validade operacional do snapshot em vez de solicitar novo relatório a cada abertura da tela.

#### Scenario: Snapshot ainda válido
- **WHEN** usuário abre Situação Fiscal dentro do TTL sem evento de alteração
- **THEN** o sistema retorna o snapshot existente com sua idade e não cria nova chamada

### Requirement: Ausência de pendência não equivale a certidão
O sistema MUST NOT apresentar relatório sem findings reconhecidos como certidão negativa ou garantia absoluta de regularidade, salvo quando a própria fonte oficial fornecer esse significado.

#### Scenario: Relatório sem item reconhecido
- **WHEN** o parser não encontra pendências no relatório consultado
- **THEN** a UI informa o resultado e a data da fonte sem emitir afirmação jurídica adicional

