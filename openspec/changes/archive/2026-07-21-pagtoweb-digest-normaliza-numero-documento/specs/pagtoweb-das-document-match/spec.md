## ADDED Requirements

### Requirement: Forma canônica do número de documento no digest

O sistema SHALL calcular o `document_digest` HMAC de documentos PAGTOWEB/`das_number` sobre a forma canônica do número: apenas dígitos `[0-9]`, sem zeros à esquerda (exceto o número `0`). A mesma função MUST ser usada ao (a) montar digests da lista consultada, (b) decodificar `numeroDocumento` da resposta `PAGAMENTOS71`, e (c) casar operações DAS locais na aplicação de evidência.

#### Scenario: DAS PGDAS com zero à esquerda casa com PAGTOWEB sem zero
- **WHEN** o DAS local é `07202604328595614` e a resposta PAGTOWEB traz `numeroDocumento` `7202604328595614` (ou equivalente mascarado com o mesmo valor canônico)
- **THEN** os digests MUST coincidir
- **AND** a evidência aplicada MUST marcar a operação como `pagtoweb_payment_status=PAID`

#### Scenario: Documento consultado ausente na resposta permanece NOT_FOUND
- **WHEN** um `das_number` foi incluído na consulta
- **AND** nenhum item retornado tem o mesmo número canônico
- **THEN** a operação MUST receber `pagtoweb_payment_status=NOT_FOUND` (respeitando a regra de não rebaixar `PAID` permanente)

### Requirement: Reaplicar evidência local após correção de digest

O sistema SHALL permitir reaplicar a evidência PAGTOWEB já persistida (itens/observação office-scoped) sobre os DAS locais usando a forma canônica, sem nova chamada SERPRO, para corrigir falsos `NOT_FOUND` gerados antes da normalização.

#### Scenario: Reapply corrige PA pago
- **WHEN** existe observação PAGTOWEB com item pago canonicamente igual a um DAS local ainda `NOT_FOUND`
- **AND** o reapply é executado para o office/cliente
- **THEN** o DAS MUST passar a `PAID` com `pagtoweb_paid_at` / valor da evidência
- **AND** MUST NOT disparar live Integra Contador
