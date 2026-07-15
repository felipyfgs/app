## ADDED Requirements

### Requirement: Documentos prioriza entrega de XML
O sistema SHALL, na UI de Documentos para NF-e, colocar como ação principal o **download** (e export quando couber). Ações de manifestação (ciência de unlock e conclusivas) MUST ser secundárias ou em seção “opcional/avançado”.

#### Scenario: Detalhe NF-e com full
- **WHEN** o full está no vault
- **THEN** o botão primário é baixar XML; não há bloqueio pedindo manifestação

#### Scenario: Detalhe só resumo com flag on
- **WHEN** só há resumo e o usuário pode desbloquear
- **THEN** existe ação secundária do tipo “Obter XML completo” com texto de que não confirma a operação

#### Scenario: Conclusivas
- **WHEN** o usuário abre ações opcionais de MD-e
- **THEN** confirmação/desconhecimento/não realizada exigem confirmação explícita e não são o fluxo default
