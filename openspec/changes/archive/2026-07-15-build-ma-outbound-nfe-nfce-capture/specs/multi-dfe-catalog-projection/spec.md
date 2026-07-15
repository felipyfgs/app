## MODIFIED Requirements

### Requirement: NFC-e no catálogo
O sistema SHALL manter NFC-e (`kind=NFCE`, modelo 65) fora do pipeline DistDFe de entrada B2B e SHALL habilitar projeção de NFC-e de saída quando houver XML completo capturado por import ou canal MA elegível. `capture_available` MUST refletir feature flag, UF, configuração e modo real do canal; a API/UI MUST distinguir `ASSISTED` de `AUTOMATIC` e manter empty state honesto quando indisponível.

#### Scenario: NFC-e MA capturada
- **WHEN** XML original modelo 65 do emitente MA é validado e persistido pelo canal oficial
- **THEN** a projeção fica disponível com `kind=NFCE`, `fiscal_role=ISSUER`, `direction=OUT` e proveniência MA

#### Scenario: Canal MA assistido
- **WHEN** perfil MA está habilitado, mas não existe contrato M2M aprovado
- **THEN** `capture_available` pode refletir ingestão oficial disponível, enquanto `capture_mode=ASSISTED` impede promessa de sincronização automática

#### Scenario: Gap NFC-e documentado
- **WHEN** a captura NFC-e não está habilitada para o estabelecimento ou a UF não é MA
- **THEN** `kind=NFCE` retorna lista vazia sem erro quando não há import, e a UI indica indisponibilidade sem alegar cobertura nacional

#### Scenario: Chave sem XML
- **WHEN** o motor de sequência descobriu uma chave modelo 65, mas ainda não obteve o XML original
- **THEN** nenhum documento baixável é projetado e a pendência permanece na superfície operacional

