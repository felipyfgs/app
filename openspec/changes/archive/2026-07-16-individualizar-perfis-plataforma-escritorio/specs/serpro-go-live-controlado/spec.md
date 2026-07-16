## MODIFIED Requirements

### Requirement: Canário faturável requer autorização separada
Uma primeira chamada potencialmente faturável MUST ser opcional, read-only, delimitada por ambiente, `Office`, cliente, operação, custo máximo, quantidade máxima, janela curta e chave de idempotência. Ela SHALL exigir aprovação registrada por dois usuários distintos: um `PLATFORM_ADMIN` e um `Office ADMIN`, cada um com reconfirmação da própria senha válida por no máximo quinze minutos. TOTP/2FA MUST NOT ser exigido, e o canário MUST NOT fazer parte de CI, setup, deploy, health check ou preflight.

#### Scenario: Usuário deseja testar sem pagar
- **WHEN** não existe aprovação de canário faturável ativa
- **THEN** o processo encerra em `FREE_SMOKE_OK` sem executar Consultar, Emitir ou Declarar

#### Scenario: Aprovação incompleta
- **WHEN** falta um dos aprovadores, sua reconfirmação recente, teto ou escopo exato
- **THEN** a chamada permanece bloqueada

#### Scenario: Conta dual tenta aprovar pelos dois papéis
- **WHEN** a mesma conta dual tenta registrar as aprovações global e do Office
- **THEN** o sistema SHALL aceitar no máximo uma delas e continuar exigindo um segundo usuário autorizado
