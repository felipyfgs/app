## ADDED Requirements

### Requirement: Reserva de capacidade para auto-queue
O planner SHALL usar no máximo 60% da capacidade nominal futura do governor para auto-queue e MUST calcular demanda/capacidade em exchanges HTTP. Capacidade não utilizada em uma janela MUST NOT ser acumulada como rajada posterior.

#### Scenario: Budget nominal de cinquenta exchanges
- **WHEN** a coorte possui limite nominal de 50 exchanges no dia
- **THEN** o auto-queue planeja no máximo 30 exchanges para o dia, sujeito aos demais limites

### Requirement: Previsão até a meta interna
O sistema SHALL projetar demanda, capacidade segura, folga, conclusão estimada e quantidade sem atendimento até `target_at`, considerando breaker, cooldown, intervalos globais/por raiz, tentativas e reservas.

#### Scenario: Backlog não cabe
- **WHEN** os exchanges exigidos superam a capacidade segura restante
- **THEN** os itens excedentes são marcados `CAPACITY_AT_RISK` e encaminhados cedo à contingência sem elevar taxa

### Requirement: Distribuição justa e determinística
O sistema SHALL ordenar por prazo/faixa e alternar escritórios, raízes e modelos, selecionando no máximo uma chave por raiz por rodada. Reinício do planner MUST produzir ordem estável para o mesmo estado.

#### Scenario: Uma raiz possui grande volume
- **WHEN** uma raiz tem cem pendências e outras raízes têm uma cada
- **THEN** a raiz volumosa não monopoliza todos os slots e as pendências mais antigas das demais recebem oportunidade

### Requirement: Primeiras tentativas prevalecem
A capacidade segura SHALL atender primeiras tentativas antes de segundas tentativas da mesma ou de outra chave. Canário e reservas em voo MUST ser descontados antes do auto-queue.

#### Scenario: Só resta uma transação segura
- **WHEN** concorrem uma primeira tentativa e uma segunda tentativa recuperável
- **THEN** o slot é atribuído à primeira tentativa e a segunda segue para replanejamento/fallback

### Requirement: Urgência não modifica governor
O planner MUST tratar budgets e breaker como limites externos imutáveis. Nenhuma condição de prazo, backlog ou papel MUST gerar configuração de taxa maior, nova coorte ou bypass.

#### Scenario: Documento vencido
- **WHEN** uma pendência está `OVERDUE`
- **THEN** o sistema escala a contingência, mas não antecipa cooldown nem ultrapassa capacidade segura

