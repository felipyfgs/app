## ADDED Requirements

### Requirement: Saúde compartilhada do portal SVRS
O resumo operacional SHALL exibir estado da coorte, breaker, causa sanitizada, `next_probe_at`, exchanges consumidos/restantes, última tentativa e backlog separado por NF-e/NFC-e. Métricas globais de coorte MUST NOT revelar dados fiscais ou contagens de outros escritórios além do necessário para explicar indisponibilidade compartilhada.

#### Scenario: NFC-e bloqueia o host
- **WHEN** uma tentativa NFC-e abre o breaker global e um escritório acompanha uma NF-e pendente
- **THEN** o painel informa indisponibilidade compartilhada e próxima prova sem expor a chave ou o tenant que originou o bloqueio

### Requirement: Inbox para bloqueio e contingência
O sistema SHALL criar alertas distintos para múltiplas consultas, orçamento esgotado, contrato alterado, A1 inelegível, XML/assinatura divergente e canário bloqueado. Cada alerta SHALL oferecer ação permitida por papel e fallback, sem corpo remoto bruto.

#### Scenario: Cooldown ativo
- **WHEN** o breaker está em cooldown por múltiplas consultas
- **THEN** a inbox mostra prazo e contingência, mas não oferece retry imediato

