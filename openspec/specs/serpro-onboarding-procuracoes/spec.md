# serpro-onboarding-procuracoes Specification

## Purpose

Onboarding tenant-safe de procurações e-CAC, cadeia de representação, papéis/consentimento e estados acionáveis por Office sem confiar office_id do client.

## Requirements


### Requirement: Cadeia de representação explícita
O sistema MUST modelar separadamente o contratante da API, o autor do pedido de dados e o contribuinte consultado. O Termo autoriza o contratante a atuar em nome do autor; a procuração/poder e-CAC autoriza o autor a atuar pelo contribuinte. Nenhuma coincidência entre essas identidades SHALL ser presumida.

#### Scenario: Contador atende um cliente
- **WHEN** o contratante, o escritório contador e o cliente contribuinte são pessoas jurídicas distintas
- **THEN** a elegibilidade exige contrato ativo, Termo aceito do autor e poderes válidos do autor para aquele contribuinte e operação

#### Scenario: Elo ausente
- **WHEN** qualquer elo da cadeia não está comprovado
- **THEN** a operação é bloqueada com motivo acionável e sem tentativa remota faturável

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

### Requirement: Escolha deliberada do escritório real
O onboarding produtivo MUST exigir confirmação explícita do `Office`, nome/identidade do autor e ambiente antes de armazenar certificado, gerar Termo ou aceitar token. Escritórios demo/seed e clientes sintéticos MUST ser inelegíveis para produção.

#### Scenario: Escritório demo selecionado
- **WHEN** o operador tenta promover uma autorização vinculada a entidade marcada como demo
- **THEN** o fluxo interrompe antes de usar qualquer certificado ou endpoint real

#### Scenario: Confirmação de identidade
- **WHEN** um `ADMIN` confirma o escritório real e o titular extraído do certificado coincide com o autor
- **THEN** o sistema registra consentimento, ator, data e finalidade sem registrar a senha

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

### Requirement: Matriz versionada de serviços e poderes e-CAC
O sistema SHALL manter, a partir da documentação oficial, uma matriz versionada que relacione cada operação executável aos poderes/procurações necessários. A matriz MUST ter URL, data, hash e estado de revisão, e uma versão desconhecida ou operação sem mapeamento MUST falhar fechada em produção.

#### Scenario: Poder suficiente e vigente
- **WHEN** o cliente possui procuração vigente que cobre todos os códigos exigidos pela operação
- **THEN** o gate de poderes é aprovado somente para esse cliente, autor e operação

#### Scenario: Documentação mudou
- **WHEN** o hash da página oficial ou o catálogo de serviço diverge da versão aprovada
- **THEN** operações afetadas entram em revisão e não são automaticamente promovidas

### Requirement: Aceite e frescor da autorização RFB
Uma procuração MUST ser considerada elegível somente quando vigente, aceita pelo autorizado quando o fluxo RFB exigir aceite, vinculada ao autor/contribuinte/ambiente corretos e suficientemente fresca para a operação. Eventos em lote SHALL respeitar a exigência documental de autorização vigente em D-1 enquanto ela estiver publicada.

#### Scenario: Procuração aguardando aceite
- **WHEN** a autorização foi concedida pelo contribuinte mas ainda não foi aceita pelo contador
- **THEN** o estado permanece inelegível e nenhuma operação real usa seus poderes

#### Scenario: Evento sem evidência D-1
- **WHEN** um lote de eventos requer vigência em D-1 e não existe snapshot confiável desse período
- **THEN** o lote é bloqueado ou reduzido aos contribuintes comprovadamente elegíveis

### Requirement: Identidades preparadas para CNPJ alfanumérico
Campos de CNPJ em banco, DTOs, XML, JSON, cache, filtros e UI MUST ser strings, preservar maiúsculas, aceitar o formato alfanumérico e validar dígitos verificadores conforme a regra vigente, sem coerção numérica. Pontos em que a documentação do Termo ou Eventos ainda exige somente dígitos MUST permanecer sob gate específico até teste contratual e confirmação oficial.

#### Scenario: Novo CNPJ alfanumérico
- **WHEN** um CNPJ válido contém letras nas posições permitidas
- **THEN** o sistema o preserva integralmente em toda a cadeia, sem truncar, converter ou rejeitar por regex exclusivamente numérica

#### Scenario: Conflito documental não resolvido
- **WHEN** o CNPJ alfanumérico precisa entrar em campo cuja página oficial ainda limita a 14 dígitos numéricos
- **THEN** a operação produtiva afetada falha fechada com `OFFICIAL_CLARIFICATION_REQUIRED`

### Requirement: Estado acionável de onboarding
O sistema MUST expor, sem segredos, um estado composto no mínimo por contrato, credencial, Termo local, aceite SERPRO, token, procurações, poderes, preços/orçamento e rollout. Cada bloqueio MUST trazer código, responsável sugerido e próxima ação, distinguindo vencido, ausente, rejeitado e pendente.

#### Scenario: Termo pendente
- **WHEN** o A1 existe mas nenhum Termo foi aceito
- **THEN** a UI mostra `PENDING_TERM`, não mostra “pronto” e oferece apenas ações permitidas ao papel atual

#### Scenario: Poder faltante de um cliente
- **WHEN** o escritório está pronto mas um cliente não possui poder para a operação escolhida
- **THEN** apenas aquele cliente/operação fica inelegível, sem bloquear nem vazar os demais clientes

### Requirement: Expiração, renovação e revogação automatizadas com controle
Jobs Horizon e scheduler SHALL verificar certificados, Termos, tokens e procurações em janelas configuradas, enfileirar somente verificações não mutantes e alertar antes da expiração. O sistema MUST NOT assinar novo Termo, renovar procuração ou executar chamada fiscal mutante sem consentimento/ação explícita.

#### Scenario: Token próximo da meia-noite limite
- **WHEN** o token do procurador entra na margem de renovação
- **THEN** o sistema marca a autorização para renovação e bloqueia jobs que ultrapassariam a validade

#### Scenario: Procuração revogada
- **WHEN** uma sincronização oficial identifica revogação ou redução de poderes
- **THEN** a elegibilidade afetada é retirada imediatamente e jobs pendentes são cancelados ou bloqueados

#### Scenario: Sync completo não retorna poder anterior
- **WHEN** uma resposta completa e autenticada deixa de listar um poder previamente ativo
- **THEN** esse poder é encerrado deterministicamente, sem continuar ativo por ausência de atualização

### Requirement: UI sanitizada e aderente ao painel
A interface autenticada SHALL reutilizar `panel-ui` e `ui-archetype`, mostrando checklist, validade, fingerprints abreviadas e ações por papel. Ela MUST NOT renderizar ou permitir copiar Consumer Secret, senha, token, XML integral, PFX ou identificador de vault.

#### Scenario: Visualização por Viewer
- **WHEN** um `VIEWER` abre a tela de integração
- **THEN** vê estados e prazos do próprio escritório, sem ações sensíveis ou dados de outro tenant
