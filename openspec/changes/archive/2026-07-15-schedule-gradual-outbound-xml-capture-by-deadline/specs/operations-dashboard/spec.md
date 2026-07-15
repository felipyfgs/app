## ADDED Requirements

### Requirement: Fechamento mensal sobre documentos conhecidos
O dashboard SHALL exibir por escritório, competência e modelo o total conhecido, capturado, pendente, em atenção, contingência, risco de capacidade e vencido. A UI/API MUST denominar a métrica “completude sobre documentos conhecidos” e MUST NOT alegar universo fiscal absoluto.

#### Scenario: Competência incompleta
- **WHEN** existem cem chaves conhecidas e noventa XMLs canônicos
- **THEN** o painel mostra 90% de completude conhecida e detalha as dez pendências por faixa/fonte

### Requirement: Capacidade e conclusão prevista
O resumo SHALL mostrar exchanges automáticos planejáveis, folga, conclusão estimada, fontes de resolução e quantidade que exige contingência, sem revelar dados de outro tenant ou material fiscal bruto.

#### Scenario: Capacidade insuficiente
- **WHEN** a previsão não atende `target_at`
- **THEN** a inbox alerta antes do prazo e oferece lote XML/ZIP, `autXML` ou pacote oficial conforme elegibilidade

### Requirement: Alertas sem retry urgente
Itens `CONTINGENCY` e `OVERDUE` SHALL oferecer ações assistidas e MUST NOT oferecer aumento de taxa, antecipação de cooldown ou retry remoto fora do slot.

#### Scenario: Operador abre item vencido
- **WHEN** um OPERATOR acessa pendência vencida com breaker aberto
- **THEN** vê prazo, motivo e importação assistida como ação, sem botão de forçar SVRS

