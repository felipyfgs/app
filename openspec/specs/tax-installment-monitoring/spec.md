# tax-installment-monitoring Specification

## Purpose

Sincronizado a partir de `build-complete-fiscal-monitoring-hub` (2026-07-15).

## Requirements

### Requirement: Modalidades de parcelamento catalogadas
O sistema SHALL tratar separadamente parcelamentos ordinários, especiais e programas oficialmente disponíveis para Simples Nacional e MEI, preservando modalidade e serviço de origem.

#### Scenario: Consulta de pedidos
- **WHEN** contribuinte elegível possui pedidos em mais de uma modalidade
- **THEN** o sistema lista cada pedido na modalidade correta sem fundir identificadores ou parcelas

### Requirement: Acompanhamento de parcelas e pagamentos
O sistema SHALL monitorar situação do pedido, parcelas, vencimentos, detalhes de pagamento e disponibilidade de documento de arrecadação conforme resposta oficial.

#### Scenario: Parcela vencida sem pagamento confirmado
- **WHEN** a data de vencimento passa e não há confirmação oficial de pagamento
- **THEN** a parcela fica `ATTENTION` ou `PENDING` com evidência, sem afirmar inadimplência definitiva além da fonte

#### Scenario: Pagamento confirmado
- **WHEN** serviço oficial retorna detalhe de pagamento da parcela
- **THEN** o sistema vincula a confirmação ao pedido e preserva a evidência consultada

### Requirement: Emissão de parcela é assistida e idempotente
O sistema SHALL emitir documento de arrecadação apenas para parcela oficialmente disponível, com autorização, orçamento e idempotência, preservando o artefato retornado.

#### Scenario: Repetição da emissão
- **WHEN** usuário repete a mesma emissão dentro da validade do documento existente
- **THEN** o sistema reutiliza o artefato válido ou exige confirmação explícita conforme contrato do serviço

### Requirement: Adesão e negociação ficam fora do piloto
O sistema MUST NOT aderir, reparar, desistir ou renegociar parcelamento enquanto a operação não possuir spec e coorte mutante aprovadas.

#### Scenario: Tentativa de adesão
- **WHEN** usuário solicita adesão no piloto de monitoramento
- **THEN** o sistema retorna operação não habilitada e não acessa endpoint mutante

### Requirement: Procuração é validada por modalidade e serviço
O sistema MUST avaliar os poderes exigidos pela matriz oficial para cada modalidade antes de consultar ou emitir documento.

#### Scenario: Procuração cobre SN mas não MEI
- **WHEN** o Autor possui poder para serviço SN e solicita operação MEI não coberta
- **THEN** a operação MEI é bloqueada sem afetar consultas SN elegíveis
