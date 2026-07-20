## MODIFIED Requirements

### Requirement: Avaliação local de Produção SERPRO
O stack local MAY operar com `FISCAL_PROFILE=production` para avaliação de consultas reais. Offices usados nessa avaliação MUST ter `serpro_segregation_class=PRODUCTION`. Com perfil `dev`, drivers MUST permanecer fixture. Egress faturável em PRODUCTION MUST continuar exigindo segregação explícita PRODUCTION (fail-closed para unset).

#### Scenario: Perfil production habilita drivers reais
- **WHEN** `FISCAL_PROFILE=production` no ambiente local
- **THEN** os drivers de capability SERPRO resolvem para `real` (não `fixture`)

#### Scenario: Office sem segregação PRODUCTION bloqueia egress
- **WHEN** um office tem `serpro_segregation_class` nulo ou distinto de PRODUCTION
- **AND** a rota é faturável em ambiente PRODUCTION
- **THEN** o egress MUST falhar com código de segregação

#### Scenario: Office PRODUCTION elegível
- **WHEN** o office tem `serpro_segregation_class=PRODUCTION`
- **AND** kill switch inativo e demais checks de egress passam
- **THEN** o gate de segregação MUST passar
