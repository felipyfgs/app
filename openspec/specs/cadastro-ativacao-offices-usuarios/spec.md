# cadastro-ativacao-offices-usuarios

## Purpose

Cadastro e ativação de Offices e usuários: criação pendente pela plataforma, entrega de credencial única (link ou senha provisória), ativação transacional e gestão de equipe pelo Office ADMIN.

## Requirements

### Requirement: Plataforma cria Office completo em estado pendente
Um `PLATFORM_ADMIN` com senha recentemente confirmada SHALL poder criar, em uma única operação, um Office com nome de exibição, perfil institucional contendo exatamente CNPJ, razão social, e-mail institucional e telefone institucional, plano obrigatório e primeiro `OfficeRole::ADMIN`. O slug MUST ser gerado no servidor com resolução determinística de colisão. Office, perfil, assinatura, usuário, membership e ativação MUST ser gravados atomicamente e nenhum `office_id` do cliente SHALL definir escopo tenant. O usuário pendente SHALL receber hash-sentinela individual não autenticável enquanto a senha permanente não existe.

#### Scenario: Criação válida
- **WHEN** o administrador informa perfil, plano, primeiro ADMIN e método de entrega válidos
- **THEN** o sistema SHALL criar todo o agregado em `PENDING_ACTIVATION` e retornar somente dados sanitizados mais o segredo de entrega única

#### Scenario: Plano ausente
- **WHEN** a requisição omite o plano
- **THEN** a API SHALL rejeitar a criação sem usar um plano padrão e sem gravar registros parciais

#### Scenario: Falha intermediária
- **WHEN** qualquer etapa da criação falha
- **THEN** a transação MUST reverter Office, assinatura, usuário, membership e ativação

#### Scenario: Retry perde a primeira credencial
- **WHEN** a mesma chave de idempotência e payload são repetidos depois de a primeira resposta ter criado o agregado
- **THEN** a API SHALL retornar o recurso existente com `credential_delivery=regeneration_required`, MUST NOT repetir o segredo e MUST NOT criar outro Office

### Requirement: Pendência não inicia operação nem período comercial
Office e assinatura novos MUST permanecer `PENDING_ACTIVATION`; usuário e membership iniciais MUST permanecer inativos. `starts_at`, `current_period_starts_at` e `current_period_ends_at` SHALL ser nulos, e franquias, agendas, consultas inaugurais, mutações e chamadas externas MUST NOT iniciar antes da ativação.

#### Scenario: Office ainda não ativado
- **WHEN** jobs ou APIs operacionais encontram o Office pendente
- **THEN** eles SHALL ignorar ou bloquear o Office sem consumir franquia, orçamento ou chamada externa

#### Scenario: Consulta administrativa do pendente
- **WHEN** a plataforma lista ou abre o detalhe do Office criado
- **THEN** o estado `PENDING_ACTIVATION`, método e expiração SHALL ser visíveis sem segredo nem datas comerciais iniciadas, mesmo que o Office não possa ser selecionado como contexto tenant

### Requirement: Link manual é fixo ao e-mail, único e válido por sete dias
O modo `MANUAL_LINK` SHALL gerar URL manual vinculada ao e-mail normalizado do usuário, com segredo de alta entropia, uso único e expiração exata em sete dias. O e-mail MUST NOT ser alterável no aceite, e o fluxo MUST exigir a definição de uma senha permanente antes de ativar a conta.

#### Scenario: Link válido
- **WHEN** o destinatário abre um link vigente e define uma senha válida
- **THEN** o sistema SHALL ativar exclusivamente a conta e o vínculo associados ao e-mail fixado, marcar `password_change_required=false` e criar uma nova sessão somente após o commit

#### Scenario: Link expirado
- **WHEN** o token é apresentado depois de `expires_at`
- **THEN** o sistema SHALL rejeitá-lo sem ativar ou alterar qualquer registro

#### Scenario: Link reutilizado
- **WHEN** um token já consumido é apresentado novamente
- **THEN** a API SHALL responder como ativação inválida ou consumida e MUST NOT repetir efeitos

### Requirement: Senha provisória exige troca antes de qualquer acesso
O modo `TEMPORARY_PASSWORD` SHALL gerar uma senha pelo sistema, válida por sete dias e exibida uma única vez. A senha provisória MUST ser validada apenas no fluxo dedicado de primeiro acesso e MUST ser substituída por senha permanente antes de criar sessão ou permitir acesso ao painel.

#### Scenario: Primeiro acesso válido
- **WHEN** o usuário informa e-mail, senha provisória vigente e nova senha válida
- **THEN** o sistema SHALL consumir a provisória, substituir o hash-sentinela pelo hash da nova senha, marcar `password_change_required=false` e concluir a ativação antes de criar uma nova sessão

#### Scenario: Login comum com senha provisória
- **WHEN** o usuário tenta autenticar no login comum com a senha provisória
- **THEN** o sistema SHALL negar acesso ao painel e orientar o fluxo de primeiro acesso sem revelar existência de outra conta

#### Scenario: Senha provisória expirada
- **WHEN** a provisória é usada depois de sete dias
- **THEN** nenhum acesso ou troca SHALL ocorrer

### Requirement: Ativação do primeiro ADMIN é transacional e inicia a assinatura
A conclusão válida de `OFFICE_FIRST_ADMIN` MUST, sob lock e em uma transação, consumir a ativação, salvar a senha permanente, marcar `password_change_required=false`, ativar usuário, membership, Office e assinatura e iniciar o período comercial no mesmo instante. Os limites e franquias SHALL corresponder ao plano escolhido. Falha ou concorrência MUST produzir no máximo uma ativação completa.

#### Scenario: Ativação concluída
- **WHEN** o primeiro ADMIN conclui um dos dois métodos
- **THEN** `starts_at` e `current_period_starts_at` SHALL usar o instante da ativação, `current_period_ends_at` SHALL seguir o ciclo do plano e o Office SHALL tornar-se operacional após o commit

#### Scenario: Duas conclusões concorrentes
- **WHEN** duas requisições válidas tentam consumir o mesmo segredo simultaneamente
- **THEN** somente uma SHALL ativar e iniciar o período; a outra SHALL falhar sem efeitos adicionais

#### Scenario: Erro ao iniciar assinatura
- **WHEN** a ativação falha ao atualizar a assinatura ou franquia
- **THEN** senha, usuário, membership, Office, assinatura e token MUST permanecer no estado anterior à tentativa

### Requirement: Ativação de membro não reinicia o período do Office
A conclusão de `OFFICE_MEMBER` SHALL ativar somente o usuário e sua membership pendente. Ela MUST NOT alterar `starts_at`, período, plano ou franquias da assinatura já ativa.

#### Scenario: Operador aceita acesso
- **WHEN** um OPERATOR pendente conclui sua ativação
- **THEN** seu vínculo SHALL tornar-se ativo e as datas comerciais do Office SHALL permanecer inalteradas

### Requirement: Regeneração revoga todo segredo anterior
Um ator autorizado e com senha recente SHALL poder regenerar uma ativação pendente. O sistema MUST revogar atomicamente toda geração anterior ainda válida antes de emitir um novo link ou senha, MUST preservar o e-mail vinculado e MUST mostrar o novo segredo apenas na resposta de regeneração.

#### Scenario: Link é regenerado como senha provisória
- **WHEN** o administrador escolhe outro método para uma ativação pendente
- **THEN** o link anterior SHALL deixar de funcionar imediatamente e somente a nova senha SHALL poder concluir o fluxo

#### Scenario: Tela do segredo é reaberta
- **WHEN** o administrador volta ao detalhe depois de fechar a resposta
- **THEN** a API MUST NOT recuperar o link ou senha anterior e SHALL oferecer apenas regeneração

### Requirement: Cadastro global permanece restrito ao onboarding inicial
Somente o onboarding de uma instalação estruturalmente vazia SHALL criar a primeira `PlatformMembership` `PLATFORM_ADMIN`. Rotas autenticadas da plataforma MUST NOT oferecer criação, convite, ativação ou regeneração de outro administrador global; o painel SHALL apresentar o Proprietário em uma superfície singular de consulta e atualização da identidade existente.

#### Scenario: Primeiro proprietário conclui onboarding
- **WHEN** o token inicial válido é consumido em uma base estruturalmente vazia
- **THEN** o sistema SHALL criar e autenticar o único Proprietário sem depender de convite de outro administrador

#### Scenario: Cliente antigo tenta criar administrador global
- **WHEN** um cliente chama a antiga operação de criação em `/api/v1/platform/admins`
- **THEN** a API MUST rejeitar a operação sem criar usuário, `PlatformMembership` ou ativação

#### Scenario: Proprietário abre a administração
- **WHEN** o Proprietário acessa a opção singular “Proprietário”
- **THEN** o painel SHALL exibir sua identidade existente sem tabela, botão de novo administrador ou linguagem de equipe global

#### Scenario: Identidade existente é atualizada
- **WHEN** o Proprietário com senha recente altera dados permitidos ou seu Office padrão pela rota singular
- **THEN** o sistema SHALL atualizar o mesmo usuário e vínculo sem criar outro `PLATFORM_ADMIN` ou `OfficeMembership`

### Requirement: Destinatário pendente pode ter o e-mail corrigido
Enquanto o destinatário nunca tiver sido ativado, o ator autorizado e com senha recente SHALL poder substituir seu nome e e-mail por uma ação distinta da regeneração. A plataforma SHALL corrigir o primeiro ADMIN de um Office; somente OfficeMembership ADMIN real SHALL corrigir membro do próprio Office. O sistema MUST revogar todos os segredos anteriores, registrar auditoria sanitizada, remover a conta e membership pendentes exclusivas e criar nova conta, grants e ativação para o e-mail corrigido. Depois da ativação, essa ação MUST ser negada. A identidade global inicial MUST ser corrigida pelo fluxo singular de recuperação do Proprietário e não por convite pendente.

#### Scenario: E-mail foi digitado incorretamente
- **WHEN** a plataforma corrige o primeiro ADMIN antes da ativação
- **THEN** todos os links e senhas anteriores SHALL falhar e a nova credencial SHALL ficar fixa ao novo e-mail

#### Scenario: Office já foi ativado
- **WHEN** a plataforma tenta usar a correção especial depois da ativação
- **THEN** a API SHALL negar a ação e a equipe SHALL ser alterada somente pelos fluxos normais do Office

#### Scenario: Correção global usa endpoint legado
- **WHEN** alguém tenta corrigir um suposto administrador global pendente pelas rotas plurais
- **THEN** a API MUST rejeitar a ação e orientar a manutenção ou recuperação do Proprietário existente

### Requirement: Office ADMIN gerencia equipe dentro do limite do plano
Somente um ator com OfficeMembership ativa `OfficeRole::ADMIN` no `CurrentOffice` SHALL poder listar, criar, alterar papel entre `ADMIN`, `OPERATOR` e `VIEWER`, regenerar ativação, desativar e reativar memberships desse Office. O papel ADMIN efetivo de `PLATFORM_ADMIN` sem membership real MUST NOT autorizar gestão da equipe. Criação, alteração de ADMIN, desativação, reativação e regeneração MUST exigir senha recente. Memberships ativas e pendentes SHALL contar contra `max_users`; desativadas e administradores globais sem membership MUST NOT contar.

#### Scenario: Vaga disponível
- **WHEN** o ADMIN cria um membro e a soma de ativos e pendentes fica dentro de `max_users`
- **THEN** o usuário e a membership SHALL nascer pendentes com o papel escolhido e um dos dois métodos de ativação

#### Scenario: Limite atingido
- **WHEN** duas inclusões concorrentes fariam a equipe ultrapassar `max_users`
- **THEN** o sistema SHALL aceitar somente as vagas disponíveis e rejeitar o excedente sem registros parciais

#### Scenario: Papel sem permissão administra equipe
- **WHEN** OPERATOR ou VIEWER tenta criar, alterar, regenerar ou desativar membro
- **THEN** a API SHALL responder `403` sem mutação

#### Scenario: Plataforma sem membership abre equipe
- **WHEN** um `PLATFORM_ADMIN` em contexto global, mas sem OfficeMembership no Office corrente, tenta listar ou alterar a equipe
- **THEN** a API SHALL responder `403` e MUST NOT tratar o papel global como membership

#### Scenario: Último ADMIN
- **WHEN** uma ação tenta rebaixar ou desativar o último ADMIN ativo do Office
- **THEN** o sistema SHALL bloquear a ação

#### Scenario: Membro é desativado e reativado
- **WHEN** o ADMIN desativa um membro sem outro grant ativo e depois solicita sua reativação com vaga disponível
- **THEN** sessões e ativações antigas SHALL ser revogadas, o usuário SHALL ficar inativo e uma nova ativação SHALL ser exigida antes de devolver o acesso

#### Scenario: Usuário legado conserva outro vínculo
- **WHEN** a membership alvo é desativada mas o usuário possui outra membership legada ativa
- **THEN** somente o vínculo alvo SHALL perder acesso e o usuário global MUST NOT ser desativado; uma reativação posterior SHALL restaurar essa membership sem trocar sua senha global

### Requirement: Novos fluxos permitem somente um Office por usuário
Antes de criar usuário ou OfficeMembership, o sistema MUST verificar sob lock o e-mail e todos os grants existentes. Qualquer e-mail já presente em `users` MUST ser rejeitado pelos endpoints de criação; membro desativado somente SHALL retornar pelo endpoint de reativação. Um usuário que já pertence a qualquer Office ou possui PlatformMembership MUST NOT receber novo vínculo por estas APIs. Memberships legadas múltiplas MUST ser preservadas e continuar operacionais, mas não poderão ser ampliadas.

#### Scenario: E-mail pertence a outro Office
- **WHEN** um ADMIN tenta convidar e-mail já vinculado a outro Office
- **THEN** o sistema SHALL rejeitar sem revelar detalhes do outro Office e sem criar membership

#### Scenario: E-mail pertence a usuário sem grant
- **WHEN** um endpoint de criação recebe e-mail já existente, ainda que o usuário não possua membership ativa
- **THEN** a API SHALL rejeitar de forma neutra e MUST NOT reutilizar a conta automaticamente

#### Scenario: Usuário legado alterna Offices
- **WHEN** um usuário já possuía múltiplas memberships antes da change
- **THEN** os vínculos existentes SHALL permanecer disponíveis e nenhum deles SHALL ser removido pela migration

#### Scenario: Conta dual do bootstrap
- **WHEN** o comando de bootstrap cria a primeira conta
- **THEN** a exceção dual SHALL ser permitida somente nesse comando e MUST NOT ficar disponível nas APIs novas

### Requirement: Segredos são irrecuperáveis e respostas não são armazenadas
Tokens SHALL ser persistidos somente como hash não reversível e senhas somente por hash de senha. Link, token, senha provisória e senha permanente MUST NOT entrar em banco em claro, cache, log, auditoria, fila, exceção ou telemetria. Toda resposta que recebe ou exibe material de ativação MUST usar `Cache-Control: no-store`.

#### Scenario: Varredura após criação e ativação
- **WHEN** a suíte inspeciona persistência, logs, jobs, eventos e respostas posteriores
- **THEN** nenhum segredo em claro SHALL ser encontrado e o segredo já exibido MUST NOT ser recuperável

#### Scenario: Token na URL pública
- **WHEN** o link manual é aberto no navegador
- **THEN** o token SHALL permanecer no fragmento até ser removido pela SPA e MUST ser enviado à API somente no body

#### Scenario: Rotas públicas sem sessão
- **WHEN** um usuário não autenticado abre `/activate` ou `/first-access`
- **THEN** o middleware frontend SHALL permitir o fluxo sem redirecionar para login, mantendo todas as demais páginas privadas protegidas

### Requirement: Não existe envio nem ativação agendada
O sistema MUST apenas exibir link ou senha para entrega manual e MUST NOT enviar e-mail, SMS, WhatsApp ou criar agenda de ativação. Expiração e consumo SHALL ocorrer por validação de tempo no request, sem job que ative conta automaticamente.

#### Scenario: Credencial é gerada
- **WHEN** uma criação ou regeneração termina
- **THEN** a resposta SHALL disponibilizar a credencial para cópia manual e nenhuma mensagem externa SHALL ser enfileirada

### Requirement: Wizard e telas de acesso são concisos
O wizard de Office SHALL usar etapas diretas para Escritório, Plano, Primeiro administrador, Entrega e Revisão, sem cards explicativos entre etapas. Lista/detalhe SHALL permitir localizar pendências, corrigir o primeiro ADMIN e regenerar acesso sem recuperar segredo. Ativação, primeiro acesso e equipe SHALL usar rótulos diretos, validação inline, badges/toasts para estados normais e `UAlert` somente para erro real, bloqueio acionável ou risco imediato.

#### Scenario: Plataforma conclui o wizard
- **WHEN** todos os campos estão válidos
- **THEN** a interface SHALL avançar por rótulos e formulários diretos e mostrar o segredo uma única vez sem avisos persistentes redundantes

#### Scenario: Resposta única foi perdida
- **WHEN** o wizard recebe replay idempotente com `credential_delivery=regeneration_required`
- **THEN** a interface SHALL abrir o detalhe pendente e oferecer `Regenerar acesso` sem afirmar que o segredo pode ser recuperado

#### Scenario: Office ADMIN abre equipe
- **WHEN** a lista é carregada normalmente
- **THEN** papéis, estados e vagas SHALL aparecer de forma compacta sem parágrafos sobre arquitetura ou implementação
