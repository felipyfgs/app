# serpro-api-usage-ledger Specification

## Purpose

Sincronizado a partir de `build-complete-fiscal-monitoring-hub` (2026-07-15).

## Requirements

### Requirement: Ledger imutável por chamada externa
O sistema MUST registrar cada tentativa potencialmente faturável com `office_id`, contribuinte, sistema, serviço, operação, classe de consumo, quantidade, correlação, resultado e horários, sem permitir edição destrutiva após finalização.

#### Scenario: Consulta concluída
- **WHEN** uma consulta SERPRO retorna resultado
- **THEN** o ledger finaliza a entrada idempotente com tenant e serviço corretos, inclusive quando a resposta não contém pendências

#### Scenario: Retry da mesma operação lógica
- **WHEN** o mesmo identificador idempotente é reenviado sem nova unidade faturável
- **THEN** o sistema reutiliza a execução lógica e não duplica o consumo atribuído

### Requirement: Classificação e preço versionados
O sistema SHALL classificar operações como `CONSULTA`, `EMISSAO`, `DECLARACAO`, `NAO_FATURAVEL` ou `DESCONHECIDA` e SHALL calcular custo estimado com tabela de preços versionada e vigente, sem hardcode no adapter HTTP.

#### Scenario: Preço muda no mês seguinte
- **WHEN** entra em vigor nova tabela de preços
- **THEN** novas entradas usam a nova versão e entradas históricas preservam a versão e estimativa originais

#### Scenario: Faturabilidade desconhecida
- **WHEN** uma operação nova não possui regra contratual confirmada
- **THEN** o sistema registra `DESCONHECIDA`, alerta a operação e não inventa custo zero

### Requirement: Limites globais e franquias por tenant
O sistema SHALL controlar orçamento global e franquia/limite por escritório antes de reservar uma chamada e MUST impedir que um tenant ruidoso consuma toda a capacidade compartilhada.

#### Scenario: Tenant aproxima-se da franquia
- **WHEN** o consumo atribuído alcança o limiar configurado
- **THEN** o dashboard e a inbox exibem alerta com período, uso e saldo do próprio escritório

#### Scenario: Operação não essencial excede limite
- **WHEN** o tenant excedeu limite bloqueante e tenta iniciar consulta não essencial
- **THEN** a operação não chama o SERPRO e registra bloqueio de plano sem afetar outros escritórios

### Requirement: Conciliação preserva o ledger original
O sistema SHALL importar ou registrar totais oficiais de faturamento, comparar com agregações internas e manter ajustes/diferenças em registros próprios, sem reescrever entradas de consumo finalizadas.

#### Scenario: Divergência mensal
- **WHEN** o valor oficial difere do custo interno estimado
- **THEN** a conciliação registra diferença, causa conhecida ou pendente e mantém rastreabilidade até os agregados por serviço

### Requirement: Consumo exposto somente ao tenant correspondente
O sistema SHALL permitir que usuários autorizados vejam uso, franquia, estimativa e excedente apenas do escritório ativo; valores globais, custo de outros tenants e credenciais comerciais MUST permanecer restritos à administração da plataforma.

#### Scenario: Escritório consulta consumo
- **WHEN** um `ADMIN` do tenant abre o painel de uso
- **THEN** recebe apenas agregados e entradas permitidas do seu `office_id`, sem preço de custo global não contratado

### Requirement: Otimização por eventos, cache e deduplicação
O sistema SHALL preferir eventos oficiais, respostas ainda válidas em cache e deduplicação de pedidos para evitar chamadas faturáveis sem nova informação, preservando indicação de origem e idade do dado.

#### Scenario: Evento não indica alteração
- **WHEN** não existe evento novo e o snapshot permanece dentro do TTL de reconciliação
- **THEN** o scheduler não repete a consulta de rotina e o dashboard informa a data da evidência vigente
