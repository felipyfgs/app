## ADDED Requirements

### Requirement: Credencial segue ciclo versionado com teste explícito
Cada ambiente SHALL manter versões no ciclo `PENDING → VERIFIED → ACTIVE → RETIRED|COMPROMISED`. O cadastro MUST validar e custodiar PFX, senha, Consumer Key e Consumer Secret no vault; a verificação local MUST ocorrer sem rede; o teste de conexão MUST ser ação explícita que executa somente OAuth mTLS oficial e MUST NOT realizar chamada fiscal de negócio.

Cutover de `PRODUCTION` MUST exigir evidência OAuth bem-sucedida e recente vinculada à mesma versão, ambiente e fingerprint, além da confirmação reforçada do Proprietário. Uma versão `COMPROMISED`, teste expirado ou teste de outro ambiente MUST NOT satisfazer o gate.

#### Scenario: Upload válido cria versão pendente
- **WHEN** o Proprietário envia material íntegro para um ambiente
- **THEN** o sistema SHALL criar versão `PENDING`, guardar segredos no vault e retornar somente metadados sanitizados

#### Scenario: Verificação local conclui
- **WHEN** a versão pendente possui chave privada, titular e validade aceitáveis
- **THEN** ela SHALL passar a `VERIFIED` sem chamada remota e sem se tornar ativa

#### Scenario: Teste de conexão é solicitado
- **WHEN** o Proprietário aciona teste para uma versão verificada
- **THEN** o sistema SHALL executar somente OAuth mTLS no endpoint oficial e registrar evidência sanitizada sem promover a versão

#### Scenario: Cutover usa teste de outra versão
- **WHEN** a evidência OAuth não corresponde ao id, ambiente ou fingerprint da versão alvo
- **THEN** o cutover MUST falhar e a versão ativa atual MUST permanecer inalterada

#### Scenario: Versão ativa é marcada comprometida
- **WHEN** o Proprietário registra comprometimento
- **THEN** a versão MUST deixar de satisfazer prontidão, tokens derivados MUST ser invalidados e uma nova versão SHALL ser exigida

