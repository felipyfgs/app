## ADDED Requirements

### Requirement: Visualização de calendário e urgência
A interface SHALL apresentar competência, `target_at`, `due_at`, faixa, conclusão estimada e fonte prevista com texto/ícone/cor acessíveis. Estados de prazo MUST permanecer distintos de falha técnica e bloqueio do canal.

#### Scenario: Prazo saudável com canal bloqueado
- **WHEN** uma pendência ainda está `PLANNED` mas o breaker SVRS está aberto
- **THEN** a UI mostra simultaneamente prazo saudável, canal bloqueado e fontes alternativas, sem fundir os estados

### Requirement: Operação calma como padrão
A UI MUST impedir ações que aumentem frequência, furam ordem justa ou criem retry imediato. ADMIN com 2FA recente poderá antecipar a meta interna dentro da política, mas MUST NOT postergar além do dia 1 nem alterar budget pela interface.

#### Scenario: ADMIN tenta postergar prazo
- **WHEN** um ADMIN configura `due_at` depois do fim do dia 1 seguinte
- **THEN** a interface/API recusa a alteração e preserva o SLA vigente

### Requirement: Contingência progressiva
Em `ATTENTION` a UI SHALL preparar ações/lotes; em `CONTINGENCY` SHALL destacar importação/pacote; em `OVERDUE` SHALL escalar a inbox. Nenhuma transição MUST iniciar rajada automática.

#### Scenario: Mudança para contingência
- **WHEN** a capacidade projetada deixa de atender a meta
- **THEN** a tela atualiza risco e ações assistidas sem alterar limites ou enfileirar retries extras

