# NF-e Optional Manifestation

## Purpose

Capacidade opcional de manifestação conclusiva (e ciência manual) para OPERATOR/ADMIN, sem tornar MD-e o fluxo principal do produto.

## Requirements

### Requirement: Manifestação conclusiva opcional
O sistema SHALL disponibilizar, sem torná-la obrigatória, a capacidade de enviar confirmação (210200), desconhecimento (210220) e operação não realizada (210240 com xJust), além de ciência manual, para OPERATOR/ADMIN do office.

#### Scenario: Desconhecimento sob demanda
- **WHEN** o operador, de forma explícita, solicita DESCONHECIMENTO para uma NF-e do office
- **THEN** o sistema pode enviar 210220 (com confirmação de UI) e registrar auditoria — o download do XML não depende dessa ação

#### Scenario: Produto utilizável sem conclusiva
- **WHEN** o escritório apenas captura e baixa XML (com ou sem ciência de unlock)
- **THEN** nenhuma conclusiva é exigida para listar, filtrar ou exportar documentos

### Requirement: Sem automação de conclusiva no default
O sistema MUST NOT enviar automaticamente confirmação, desconhecimento ou operação não realizada.

#### Scenario: Job de unlock
- **WHEN** roda automação ou job de obtenção de XML
- **THEN** no máximo ciência (210210) é enviada; nunca conclusiva automática

### Requirement: Papéis
O sistema MUST impedir manifestação por VIEWER e SHALL permiti-la para OPERATOR e ADMIN somente no office autorizado.

#### Scenario: Viewer tenta manifestar
- **WHEN** um VIEWER solicita qualquer manifestação de NF-e
- **THEN** o sistema rejeita a ação sem acessar o certificado

### Requirement: Auditoria e segurança
O sistema MUST auditar toda manifestação opcional, manter o A1 do cliente somente em memória, não gravar PEM em disco e bloquear envios quando a feature flag estiver desligada.

#### Scenario: Manifestação auditada sem segredo
- **WHEN** uma manifestação opcional é processada
- **THEN** a auditoria registra ator, chave, tipo e resultado sem PFX, senha, chave privada ou PEM
