## Purpose

Capability `office-serpro-auto-onboarding` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Canonical A1 unlocks office SERPRO onboarding

O sistema SHALL tratar como pré-requisitos suficientes para o onboarding SERPRO do office: perfil institucional completo, consentimento técnico vigente e certificado A1 canônico ativo do escritório (credencial canônica ou vínculo de finalidade `SERPRO_TERM_SIGNING`). O sistema SHALL NÃO exigir `author_pfx` legado nem UI técnica de Termo/token em `/conta/escritorio` para iniciar a automação. Quando os pré-requisitos estiverem completos e Termo/token/procurações ainda não estiverem prontos, o sistema SHALL enfileirar o processamento automatizado do onboarding no contexto do office da sessão.

#### Scenario: Prerequisites complete after escritorio setup

- **WHEN** o office tem perfil completo, consentimento técnico ativo e A1 canônico ativo (com vínculo `SERPRO_TERM_SIGNING` ou canônica)
- **THEN** `evaluatePrerequisites` retorna `complete=true`
- **AND** o onboarding é enfileirado (ou já está em andamento/pronto) sem exigir upload separado de `author_pfx`

#### Scenario: Missing canonical A1 keeps configuring

- **WHEN** perfil e consentimento estão ok mas não há A1 canônico ativo nem vínculo `SERPRO_TERM_SIGNING`
- **THEN** o onboarding permanece em estado de configuração
- **AND** o código de ação indica necessidade de A1
- **AND** nenhum job de autorização SERPRO é enfileirado como pronto

#### Scenario: Author sync from institutional profile

- **WHEN** os pré-requisitos canônicos estão completos e a identidade do autor ainda está ausente ou placeholder
- **THEN** o sistema deriva o autor a partir do CNPJ/razão social do perfil institucional
- **AND** configura modo `ManagedA1` com consentimento alinhado ao consentimento técnico vigente

### Requirement: Term signing uses canonical office credential

Ao assinar o Termo com A1 gerenciado, o sistema SHALL materializar o PFX preferencialmente via credencial canônica / finalidade `SERPRO_TERM_SIGNING`. Se `author_pfx` legado existir, MAY continuar sendo usado; se estiver ausente, o sistema SHALL usar o resolvedor canônico e NÃO falhar apenas por ausência de `author_pfx`.

#### Scenario: Sign termo with canonical A1 only

- **WHEN** o job de assinatura do Termo executa para um office com A1 canônico ativo e sem `author_pfx`
- **THEN** o PFX é materializado via resolvedor de assinatura SERPRO do escritório
- **AND** o Termo assinado é persistido no vault do office/ambiente

### Requirement: Escritorio surface is certificate-only for SERPRO activation

A superfície `/conta/escritorio` SHALL NÃO exibir stepper, card ou checklist de onboarding SERPRO (Termo, token, estágios técnicos). O único aceite técnico do certificado SHALL ocorrer no modal de envio/substituição do A1 (`consent_accepted`). O sistema SHALL NÃO exigir seção separada de consentimento para ativar o escritório. Seções permitidas na configuração do escritório: perfil institucional, certificado A1 e agendas (e demais cards não-SERPRO já existentes).

#### Scenario: Contador activates office with certificate modal only

- **WHEN** o administrador do escritório envia o A1 no modal com aceite marcado
- **THEN** o backend registra consentimento + credencial e dispara a automação SERPRO
- **AND** a UI do escritório NÃO mostra stepper/card de onboarding SERPRO

#### Scenario: Separate consent section is not required

- **WHEN** o usuário abre `/conta/escritorio`
- **THEN** não há accordion/seção dedicada de consentimento técnico
- **AND** o aceite permanece disponível apenas no modal do certificado

#### Scenario: Contador regenerates integration without reuploading A1

- **WHEN** o escritório já tem A1 canônico ativo e o administrador clica em "Atualizar integração"
- **THEN** o sistema regenera/renova o token do procurador usando o certificado já armazenado
- **AND** NÃO exige novo upload de PFX nem reenvio de senha do certificado

### Requirement: Automatic procurador token renewal for office

O sistema SHALL renovar automaticamente o token do procurador do **office** (por ambiente) quando o token estiver na margem de renovação (skew) ou expirado, o Termo assinado ainda for utilizável, a credencial A1 do escritório estiver disponível, e a estratégia de reapresentação do ambiente for `REUSE_STORED_TERM`. O sistema SHALL NÃO renovar silenciosamente sob `REQUIRE_NEW_SIGNATURE`, certificado A3 interativo, ou quando a estratégia for `PENDING_VALIDATION`. O default de TRIAL SHALL ser `REUSE_STORED_TERM`; o default de PRODUCTION SHALL permanecer `PENDING_VALIDATION`.

#### Scenario: Auto renew within skew on TRIAL

- **WHEN** o ambiente é TRIAL com estratégia `REUSE_STORED_TERM` e o token do office está dentro do skew de renovação
- **THEN** o sistema tenta `refreshProcuradorToken` sem ação do contador
- **AND** em sucesso o status deixa de exigir `ACTION_REQUIRED` por token expirado

#### Scenario: Expired token blocked when strategy forbids reuse

- **WHEN** o token do office está expirado e a estratégia do ambiente é `PENDING_VALIDATION` ou `REQUIRE_NEW_SIGNATURE`
- **THEN** o sistema marca ou mantém `ACTION_REQUIRED`
- **AND** NÃO renova o token silenciosamente

#### Scenario: Renewal is office-scoped not per client

- **WHEN** a renovação automática executa
- **THEN** ela atualiza a autorização SERPRO do office/ambiente
- **AND** NÃO cria token por cliente da carteira

### Requirement: Assinatura síncrona do Termo no onboarding do escritório
Quando o onboarding do office assina o Termo com A1 gerenciado de forma síncrona, a invocação SHALL resolver todas as dependências de `SignTermoWithManagedA1Job::handle` (incluindo `OfficeCredentialResolver`) via container Laravel — MUST NOT passar argumentos manuais incompletos ou fora de ordem.

#### Scenario: Sync sign resolve OfficeCredentialResolver
- **WHEN** o onboarding do office executa a assinatura síncrona do Termo com Managed A1
- **THEN** o job recebe `OfficeCredentialResolver` na posição tipada
- **AND** NÃO ocorre TypeError por receber `AuditLogger` no lugar do resolver

### Requirement: Persistência de skip_reason de runs fiscais
Ao finalizar um `fiscal_monitoring_run`, `skip_reason` MUST caber em 80 caracteres (truncate seguro).

#### Scenario: skip_reason longo não quebra o UPDATE
- **WHEN** o payload de bloqueio tem `skip_reason` com mais de 80 caracteres
- **THEN** a persistência trunca para 80 e conclui o UPDATE sem erro SQLSTATE 22001
