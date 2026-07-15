# outbound-xml-deadline-scheduling Specification

## Purpose
TBD - created by archiving change schedule-gradual-outbound-xml-capture-by-deadline. Update Purpose after archive.
## Requirements
### Requirement: Prazo mensal operacional
Para NF-e 55 e NFC-e 65 de saída autorizadas no mês, o sistema SHALL definir `due_at` no fim do dia 1 do mês seguinte no timezone do escritório e `target_at` inicialmente 48 horas antes. O produto MUST identificar esse prazo como SLA operacional configurado, não como obrigação legal presumida.

#### Scenario: Nota autorizada em julho
- **WHEN** uma nota tem autorização válida em qualquer dia de julho
- **THEN** seu prazo operacional é 23:59:59 de 1º de agosto no fuso do escritório e a meta interna antecede esse instante em 48 horas

#### Scenario: Documento descoberto atrasado
- **WHEN** uma chave é descoberta depois de `due_at` sem XML canônico
- **THEN** a pendência nasce `OVERDUE`, abre contingência e não provoca rajada remota

### Requirement: Janela de acomodação de fontes preferenciais
O sistema SHALL aguardar inicialmente 24 horas após `XML_PENDING` antes da primeira tentativa SVRS para permitir vault, emissão, `autXML`, XML/ZIP ou pacote oficial. A janela SHALL ser encurtada até 6 horas quando faltarem menos de 7 dias para `target_at` e SHALL ser dispensada em contingência, sem dispensar os limites do governor.

#### Scenario: autXML chega durante acomodação
- **WHEN** um XML válido é ingerido por `autXML` antes do fim da janela
- **THEN** nenhuma tentativa SVRS é planejada para a chave

### Requirement: Faixas de urgência sem aumento de taxa
O sistema SHALL classificar pendências como `PLANNED`, `ATTENTION`, `CONTINGENCY`, `OVERDUE` ou `CAPTURED` conforme prazo e capacidade. Mudança de faixa MUST alterar alertas, prioridade de fontes e contingência, mas MUST NOT aumentar budgets, concorrência ou furar breaker.

#### Scenario: Faltam 72 horas
- **WHEN** uma pendência entra na janela final de 72 horas sem XML
- **THEN** o sistema abre contingência assistida e mantém qualquer tentativa SVRS somente no slot seguro já permitido

### Requirement: Cadência máxima por chave
O sistema MUST executar no máximo duas transações SVRS por chave. A segunda SHALL ocorrer no mínimo 24 horas depois, somente para resultado recuperável e sem prejudicar primeiras tentativas; retries de 15 minutos, 1 hora, 6 horas e 12 horas MUST NOT ser usados pelo canal SVRS.

#### Scenario: Primeira tentativa retorna 503
- **WHEN** uma transação recebe 503 e ainda há prazo/capacidade
- **THEN** uma segunda e última tentativa pode ser planejada para pelo menos 24 horas depois

#### Scenario: Primeira tentativa recebe bloqueio
- **WHEN** a primeira transação detecta bloqueio por múltiplas consultas
- **THEN** nenhuma segunda tentativa da chave é planejada e prevalece o breaker global

### Requirement: Planejamento separado do dispatch
O sistema SHALL recalcular agenda sem acessar PFX e SHALL enfileirar somente slots vencidos após revalidar fonte, tenant, flags, breaker e orçamento. Slot perdido MUST voltar ao planejamento sem fila de compensação.

#### Scenario: Canal bloqueado no horário planejado
- **WHEN** chega `next_attempt_at` mas o breaker está aberto
- **THEN** o dispatcher não materializa o A1, mantém a pendência e solicita novo planejamento/fallback

### Requirement: Timezone e tenancy derivados
Prazo, agenda, queries e jobs MUST usar timezone e `office_id` das relações persistidas; valores enviados pelo cliente MUST NOT alterar tenant, coorte ou prazo de outro escritório.

#### Scenario: Office forjado
- **WHEN** um request tenta planejar pendência de outro escritório por `office_id`
- **THEN** o recurso não é acessível e nenhum estado ou job é criado

