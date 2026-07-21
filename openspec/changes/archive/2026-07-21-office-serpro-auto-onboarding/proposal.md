## Why

O contador (escritório cliente) não deve passar por onboarding SERPRO explícito. Bastam certificado A1 e aceite no modal do certificado; o backend automatiza Termo, token e procurações. A UI atual ainda expõe stepper de onboarding e seção de consentimento separada — ruído que atrasa a ativação.

## What Changes

- Aceitar A1 canônico (+ vínculo `SERPRO_TERM_SIGNING`) + consentimento no **mesmo** upload do certificado como único gatilho humano do escritório.
- Automação pós-upload: Termo → token → procurações (e fixture token em `FISCAL_PROFILE=dev`).
- Renovação automática do token do office quando `REUSE_STORED_TERM` (TRIAL default).
- **UX do escritório:** `/conta/escritorio` com perfil · certificado · agendas — **sem** stepper/card de onboarding SERPRO e **sem** seção separada de consentimento (aceite só no modal do A1).
- Botão **Atualizar integração** no certificado: regenera token sem reenviar PFX.
- Testes cobrindo ponte canônica, renovação e simplificação da superfície do escritório.

## Capabilities

### New Capabilities

- `office-serpro-auto-onboarding`: gatilho automático pós A1+aceite no modal; renovação do token do office; superfície mínima do escritório (sem onboarding SERPRO visível).

### Modified Capabilities

- (nenhuma)

## Impact

- API: pré-requisitos canônicos, sync de autor, fixture Dev, lifecycle renew.
- Web: `OfficeSettingsPanel` enxuto; aceite permanece em `OfficeCredentialSection`.
- Sem mei no Compose; plataforma continua dona do contrato SERPRO.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs / archive
- Depende de: nenhuma
- Capability/contrato: `office-serpro-auto-onboarding`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: apply desta change
- Paralelismo: ok com admin SERPRO nav/console
