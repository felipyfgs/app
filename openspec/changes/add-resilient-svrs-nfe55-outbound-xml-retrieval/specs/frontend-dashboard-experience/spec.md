## ADDED Requirements

### Requirement: Estados de recuperação e limite preventivo
A interface SHALL distinguir documento já disponível, aguardando fonte preferencial, aguardando orçamento SVRS, em recuperação, capturado, breaker/cooldown e fallback assistido. Ela SHALL explicar que os budgets são preventivos e que a SVRS não publicou o limite do formulário `NFESSL`.

#### Scenario: Chave aguarda orçamento diário
- **WHEN** uma NF-e elegível permanece na fila após esgotar o budget diário
- **THEN** a UI preserva o estado pendente, mostra a próxima janela e destaca importação XML/ZIP como alternativa

### Requirement: Ações por papel sem bypass
A UI MUST ocultar e bloquear flags, allowlist, extensão de cooldown e seleção de canário para quem não é ADMIN com 2FA recente. Nenhum papel MUST receber controle para antecipar `next_probe_at`, aumentar limites ou trocar coorte/IP.

#### Scenario: ADMIN durante cooldown
- **WHEN** um ADMIN com 2FA recente abre um item bloqueado antes de `next_probe_at`
- **THEN** pode desligar o canal, estender o cooldown ou preparar fallback, mas não disparar prova antecipada

#### Scenario: OPERATOR usa contingência
- **WHEN** o canal está bloqueado e um OPERATOR acessa uma NF-e pendente
- **THEN** a ação primária disponível é importar XML/ZIP ou pacote oficial conforme elegibilidade, sem retry remoto

