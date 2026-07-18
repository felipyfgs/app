## ADDED Requirements

### Requirement: Formulário mínimo de ativação produtiva
O sistema MUST oferecer a administradores autorizados um fluxo principal de ativação SERPRO `PRODUCTION` cuja entrada permanente seja limitada a Consumer Key, Consumer Secret, arquivo PFX, senha do PFX e concessão explícita, sem solicitar `office_id`, ambiente, versão de credencial, frase técnica de rollout ou parâmetros internos da SERPRO.

#### Scenario: Administrador abre a configuração
- **WHEN** um administrador autorizado acessa a configuração produtiva no contexto explícito de um tenant
- **THEN** o sistema apresenta os cinco elementos de entrada exigidos e não expõe as etapas técnicas como ações manuais obrigatórias

#### Scenario: Cliente tenta escolher o tenant ou ambiente
- **WHEN** o request inclui `office_id` ou tenta selecionar ambiente diferente de `PRODUCTION`
- **THEN** o sistema rejeita a entrada e deriva o tenant exclusivamente de `CurrentOffice` e o ambiente da rota de ativação

### Requirement: Concessão explícita e identidade recente
O sistema MUST exigir consentimento afirmativo e versionado para armazenar os materiais no cofre, testar OAuth produtivo, ativar a credencial e iniciar consultas somente leitura potencialmente bilhetáveis; também MUST exigir identidade recentemente confirmada para consumir a aprovação sensível via HTTP autenticado.

#### Scenario: Checkbox não marcado
- **WHEN** o administrador envia os materiais sem `consent_granted=true`
- **THEN** o sistema rejeita a ativação antes de persistir segredos ou executar egress

#### Scenario: Senha não foi confirmada recentemente
- **WHEN** o administrador concede o aceite, mas sua sessão não possui confirmação recente de identidade
- **THEN** o sistema solicita a reconfirmação padrão e não cria nem consome aprovação de cutover até ela ser concluída

#### Scenario: Consentimento aceito
- **WHEN** um administrador elegível e recentemente confirmado concede o aceite
- **THEN** o sistema registra ator, tenant, timestamp, versão/hash do texto e correlação de auditoria sem registrar os materiais secretos

### Requirement: Validação e armazenamento seguro dos materiais
O sistema MUST validar formato, senha, validade mínima e CNPJ titular do PFX antes de tornar uma versão elegível, e MUST armazenar Consumer Secret, PFX, senha e tokens exclusivamente no `SecureObjectStore` com metadados públicos sanitizados.

#### Scenario: Certificado incompatível
- **WHEN** o PFX está corrompido, expirado, usa senha inválida ou seu CNPJ diverge do contratante esperado
- **THEN** o sistema falha na etapa de certificado, não ativa a versão e retorna somente orientação sanitizada

#### Scenario: Material válido
- **WHEN** Consumer Key, Consumer Secret e PFX são válidos para o contrato produtivo
- **THEN** o sistema cria uma versão candidata no cofre e persiste apenas IDs opacos, fingerprint, datas, CNPJ mascarado e hint da chave

#### Scenario: Observabilidade e respostas
- **WHEN** qualquer etapa registra log, auditoria, métrica ou resposta HTTP
- **THEN** Consumer Secret, PFX, senha, bearer, JWT, XML e payload externo bruto não aparecem nesses destinos

### Requirement: Orquestração idempotente da ativação
O sistema MUST executar cadastro, verificação do cofre, teste OAuth mTLS no endpoint canônico, aprovação HTTP e cutover como etapas persistidas de um único onboarding idempotente.

#### Scenario: Ativação concluída
- **WHEN** todas as validações e gates técnicos passam
- **THEN** o sistema promove a versão candidata para ativa, conclui o onboarding e retorna seu estado sanitizado sem exigir chamadas técnicas manuais no frontend

#### Scenario: Reenvio do mesmo onboarding
- **WHEN** o cliente repete um comando com a mesma chave idempotente após timeout ou interrupção
- **THEN** o sistema retoma a etapa segura seguinte e não cria versão, aprovação ou sincronização duplicada

#### Scenario: Falha antes do cutover
- **WHEN** cofre, verificação ou OAuth falha antes da promoção
- **THEN** o sistema preserva a versão ativa anterior e marca a candidata/onboarding com falha recuperável sem vazar conteúdo sensível

### Requirement: Separação entre credencial da plataforma e autorização do tenant
O sistema MUST manter a credencial contratual produtiva no escopo global da plataforma e MUST criar ou atualizar somente a `OfficeSerproAuthorization` do tenant de `CurrentOffice`, sem copiar segredos globais ou ativar outros tenants implicitamente.

#### Scenario: Primeiro tenant ativado
- **WHEN** o onboarding promove uma credencial e existe um `CurrentOffice` elegível
- **THEN** o sistema vincula a autorização produtiva somente a esse tenant e registra a atribuição para auditoria/bilhetagem

#### Scenario: Credencial ativa equivalente já existe
- **WHEN** a plataforma já possui versão ativa com o mesmo material verificável e outro tenant inicia o onboarding
- **THEN** o sistema reutiliza a credencial global sem duplicá-la e processa separadamente a autorização do tenant atual

#### Scenario: Contexto tenant ausente
- **WHEN** o ator global não selecionou um tenant ativo
- **THEN** o sistema falha fechado antes de criar autorização ou executar operação de negócio

### Requirement: Procuração e poderes permanecem obrigatórios
O sistema MUST tratar a concessão do formulário como autorização operacional local e MUST NOT considerá-la procuração, Termo, assinatura ou poder e-CAC; operações representadas permanecem bloqueadas até a autoridade oficial válida ser comprovada.

#### Scenario: Procuração válida
- **WHEN** o tenant possui Termo, token de procurador e poderes válidos para a operação de leitura
- **THEN** o sistema considera a operação elegível, respeitados todos os demais gates

#### Scenario: Procuração ou poder ausente
- **WHEN** a credencial OAuth está ativa, mas falta autoridade oficial exigida para representar um cliente
- **THEN** o sistema mantém a credencial conectada, marca a sincronização como `ACTION_REQUIRED` e informa a pendência sem tentar contorná-la

### Requirement: Sincronização inicial somente leitura e controlada
O sistema MUST despachar a sincronização inicial da Caixa Postal de forma assíncrona e idempotente somente quando contrato, assinatura, capability, allowlist, orçamento, procuração/poder e kill switches permitirem; mutações fiscais e canais outbound MUST permanecer desligados.

#### Scenario: Todos os gates permitem leitura
- **WHEN** o cutover foi concluído e todos os gates da Caixa Postal estão aptos
- **THEN** o sistema agenda uma única sincronização tenant-scoped, registra ledger/bilhetagem e apresenta estado `ACTIVE_SYNC_PENDING`

#### Scenario: Gate bloqueia a execução
- **WHEN** kill switch, capability, allowlist, orçamento, assinatura ou autoridade oficial bloqueia a Caixa Postal
- **THEN** o sistema não realiza egress de negócio e apresenta `ACTION_REQUIRED` com o gate bloqueador sanitizado

#### Scenario: Usuário apenas consulta o estado
- **WHEN** o usuário abre ou atualiza uma página GET de configuração ou monitoramento
- **THEN** o sistema lê somente estado local e não executa OAuth nem chamada de negócio SERPRO

### Requirement: Estado operacional e recuperação seguros
O sistema MUST expor ID opaco, estado, etapa, timestamps, metadados mascarados e ação requerida do onboarding, e MUST manter meios autorizados de retry e rollback sem reexibir os segredos informados.

#### Scenario: Falha acionável
- **WHEN** uma etapa falha
- **THEN** a UI identifica a categoria e a etapa da falha, oferece somente a ação segura aplicável e nunca preenche novamente campos secretos

#### Scenario: Rollback após promoção
- **WHEN** uma versão recém-promovida apresenta falha operacional e existe versão anterior íntegra
- **THEN** um administrador autorizado pode restaurar a versão anterior pelo fluxo sensível existente, com confirmação recente e trilha de auditoria
