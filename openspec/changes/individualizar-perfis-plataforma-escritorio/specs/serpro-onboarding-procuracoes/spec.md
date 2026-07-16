## MODIFIED Requirements

### Requirement: Autorização isolada por Office
Termos, A1 gerenciado, tokens, ETags, consentimentos, procurações e estados de prontidão MUST ser escopados pelo `CurrentOffice`. Endpoints tenant-scoped MUST ignorar/remover `office_id` recebido e MUST NOT permitir leitura ou mutação cruzada. Um `PLATFORM_ADMIN` sem membership somente SHALL acessar esses dados quando o servidor tiver resolvido explicitamente um contexto `platform_privileged`, preservando o administrador real como ator e todos os gates aplicáveis.

#### Scenario: Injeção de office_id
- **WHEN** uma requisição autenticada envia `office_id` de outro escritório no body, query ou rota não autorizada
- **THEN** o contexto selecionado prevalece e nenhum dado do outro escritório é lido ou alterado

#### Scenario: Administrador da plataforma sem contexto
- **WHEN** um `PLATFORM_ADMIN` tenta acessar dados fiscais sem Office global válido resolvido no servidor
- **THEN** o acesso é negado sem escolher um Office a partir do request

#### Scenario: Administrador da plataforma com contexto global
- **WHEN** um `PLATFORM_ADMIN` acessa dados fiscais após o servidor resolver seu Office selecionado ou padrão
- **THEN** a operação SHALL ficar restrita àquele Office e atribuída ao ator real sem criar membership

### Requirement: Papéis e consentimento reforçados
Upload/remoção de certificado, geração/assinatura de Termo, envio ao SERPRO, renovação e revogação MUST exigir `OfficeRole::ADMIN` ou `PLATFORM_ADMIN` autorizado no contexto global, reconfirmação da senha do próprio ator válida por no máximo quinze minutos e confirmação de finalidade. `OPERATOR` SHALL poder consultar estados e executar somente sincronizações previamente autorizadas; `VIEWER` SHALL ter somente leitura sanitizada.

#### Scenario: Operador tenta assinar novo Termo
- **WHEN** um usuário `OPERATOR` solicita geração ou assinatura
- **THEN** a API retorna autorização negada e nada é enfileirado

#### Scenario: Confirmação expirada
- **WHEN** a reconfirmação de senha estiver ausente ou tiver mais de quinze minutos
- **THEN** a ação exige nova confirmação antes de acessar o material A1

#### Scenario: TOTP legado não é exigido
- **WHEN** um administrador autorizado possui senha recente mas não possui TOTP configurado
- **THEN** o gate de identidade SHALL aprovar e a ação SHALL seguir para os demais controles

