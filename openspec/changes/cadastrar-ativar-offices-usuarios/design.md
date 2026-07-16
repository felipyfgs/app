## Context

O backend possui `Office`, perfil institucional, `OfficeSubscription`, `PlatformMembership` e `OfficeMembership`, mas o bootstrap é o único fluxo completo de criação. Offices existentes nascem ativos, períodos comerciais começam imediatamente, e não há convite, ativação, senha provisória, troca obrigatória ou gestão de equipe. O plano já define `max_users` (Starter 5, Professional 25 e Enterprise 200), porém esse limite ainda não governa um fluxo administrativo de membros.

Esta change depende de `individualizar-perfis-plataforma-escritorio`: usa Office padrão para administradores globais, login sem TOTP, reconfirmação de senha e a separação `/admin/*` versus `/settings/*`. A primeira conta dual continua sendo responsabilidade exclusiva do bootstrap; todos os usuários criados por estas APIs seguem a regra nova de um perfil operacional e, para usuários de escritório, um único Office.

## Goals / Non-Goals

**Goals:**

- criar Office, assinatura pendente, perfil e primeiro ADMIN atomicamente;
- entregar primeiro acesso por link manual ou senha provisória, ambos por sete dias;
- iniciar assinatura, período e franquias somente na ativação do primeiro ADMIN;
- criar administradores globais sem membership de Office;
- permitir gestão de ADMIN, OPERATOR e VIEWER dentro do limite do plano;
- preservar memberships legadas sem permitir novos vínculos multi-Office;
- garantir que token, link e senha nunca sejam recuperáveis, cacheados ou logados;
- oferecer wizard e telas de ativação/equipe concisos, sem envio de e-mail.

**Non-Goals:**

- e-mail, SMS, WhatsApp ou ativação agendada;
- checkout, faturamento, edição de catálogo ou integração com provedor de pagamento;
- migração destrutiva de memberships legadas;
- associação nova de um usuário a múltiplos Offices;
- habilitação de operações fiscais ou chamadas externas;
- recuperação de segredo já exibido.

## Decisions

### 1. Criação de Office será um agregado pendente e transacional

`POST /api/v1/platform/offices`, protegido por `EnsurePlatformAdmin` e senha recente, receberá nome de exibição do Office, perfil institucional, `SubscriptionPlan`, primeiro ADMIN e método de entrega. O perfil manterá exatamente CNPJ, razão social, e-mail institucional e telefone institucional; o slug será gerado no servidor a partir do nome, com sufixo determinístico em colisões, e não será campo do wizard. Um serviço de aplicação criará em uma transação:

- `Office` com estado `PENDING_ACTIVATION` e `is_active=false` durante a compatibilidade;
- perfil institucional com CNPJ, razão social, nome, e-mail e contato validados;
- `OfficeSubscription` `PENDING_ACTIVATION`, limites copiados do plano e todas as datas comerciais nulas;
- `User` inativo com `password_change_required=true` e um hash-sentinela aleatório, individual e não autenticável em `users.password`, cujo valor em claro é descartado;
- `OfficeMembership` ADMIN inativa;
- uma ativação vigente para o usuário/e-mail/método.

Falha em qualquer etapa fará rollback integral. O endpoint exigirá uma chave de idempotência para retries do wizard. A primeira resposta poderá mostrar o segredo; qualquer replay da mesma chave retornará o recurso sanitizado com `credential_delivery=regeneration_required`, nunca o segredo, e payload divergente será rejeitado. A UI abrirá o detalhe e oferecerá regeneração. Nenhum job fiscal será enfileirado enquanto Office e assinatura estiverem pendentes.

Alternativa considerada: criar Office ativo e suspender chamadas até o primeiro login. Rejeitada porque iniciaria datas/limites cedo e permitiria estados parcialmente operacionais.

### 2. Uma tabela de ativações representará todos os primeiros acessos

`account_activations` conterá propósito (`OFFICE_FIRST_ADMIN`, `OFFICE_MEMBER` ou `PLATFORM_ADMIN`), método (`MANUAL_LINK` ou `TEMPORARY_PASSWORD`), usuário, Office/membership quando aplicável, hash do segredo, `expires_at`, `consumed_at`, `revoked_at`, geração e criador. O e-mail de destino será uma cópia normalizada e imutável para impedir que o segredo seja transferido a outro endereço.

O segredo em claro existirá apenas em memória durante criação/regeneração e na resposta única. Tokens de link terão alta entropia e serão comparados por hash; senhas provisórias serão comparadas por hash de senha apropriado. O hash-sentinela de `users.password` nunca será aceito por nenhum fluxo e será substituído somente pelo hash da senha permanente na conclusão; `password_change_required` mudará para `false` na mesma transação. Assim, a senha provisória não abre uma sessão comum e a coluna obrigatória de senha continua válida.

Alternativas consideradas: `password_reset_tokens` e guardar o link cifrado para reexibição. Rejeitadas porque ativação tem efeitos comerciais e auditoria próprios, e reexibição violaria a entrega única.

### 3. Link manual usa fragmento e senha provisória usa primeiro acesso dedicado

No modo link, a resposta única terá uma URL como `/activate#token=<segredo>`. O fragmento não chega ao nginx/PHP; a SPA o lê, remove da barra de endereço e envia o token somente no body para inspeção/conclusão. A tela mostra e-mail mascarado e identidade do convite, exige a definição da senha permanente e não permite trocar o e-mail.

No modo senha provisória, a plataforma ou Office ADMIN copia uma senha gerada pelo sistema. O usuário informa e-mail, senha provisória e nova senha em `/first-access`; o endpoint dedicado valida a ativação e substitui o segredo antes de criar uma sessão. Login comum não aceita a senha provisória e o painel permanece inacessível enquanto `password_change_required=true`.

Ambos expiram após sete dias e são de uso único. Após o commit, ambos criarão uma nova sessão Sanctum com rotação contra session fixation e seguirão para o painel apropriado; se a criação da sessão falhar, a senha permanente já definida permitirá login comum. Respostas de criação, regeneração, inspeção e conclusão usarão `Cache-Control: no-store`, e os endpoints públicos terão rate limit e erros neutros contra enumeração. O middleware global do Nuxt liberará explicitamente `/activate` e `/first-access` sem sessão e continuará protegendo as demais rotas.

Alternativa considerada: autenticar com a senha provisória e redirecionar depois. Rejeitada porque criaria uma sessão com credencial descartável e aumentaria o risco de acesso antes da troca obrigatória.

### 4. Ativação do primeiro ADMIN inicia o ciclo comercial sob lock

A conclusão bloqueará a ativação, o usuário, o Office e a assinatura em uma transação. Depois de validar hash, propósito, e-mail, expiração, revogação e não consumo, o serviço gravará a senha permanente, ativará usuário e membership e consumirá o segredo.

Para `OFFICE_FIRST_ADMIN`, o mesmo instante de banco ativará Office e assinatura, preencherá `starts_at`, `current_period_starts_at` e `current_period_ends_at` conforme o ciclo do plano e inicializará os contadores/franquias do primeiro período. Nenhuma consulta inaugural, agenda ou uso será criado antes desse commit. Para um membro adicional, somente usuário/membership serão ativados; o período existente não muda. Para `PLATFORM_ADMIN`, ativa-se somente o vínculo global e o usuário.

Locks e condição `consumed_at IS NULL AND revoked_at IS NULL` tornam a operação de uso único sob concorrência. Qualquer falha fará rollback completo, inclusive da senha. Um replay receberá resposta inválida/consumida sem reiniciar período.

Alternativa considerada: ativar cada registro em passos independentes. Rejeitada porque poderia liberar usuário sem assinatura ou iniciar cobrança sem acesso.

### 5. Regeneração revoga antes de emitir e nunca muda o e-mail

`PLATFORM_ADMIN` regenerará o acesso do primeiro Office ADMIN ou de outro administrador global; um Office ADMIN com membership ADMIN real regenerará membros pendentes do próprio Office. A ação exige senha recente, lock e autorização. Toda ativação ainda válida do mesmo propósito será revogada antes de criar a próxima geração, que poderá usar qualquer um dos dois métodos, mas permanecerá ligada ao e-mail original.

A resposta exibirá novo link ou senha uma vez. Fechar a tela perde o segredo; a única recuperação possível é regenerar, invalidando o anterior. O histórico guarda somente hashes e metadados sanitizados.

Enquanto o destinatário estiver pendente, o ator que o criou poderá corrigir nome/e-mail por uma ação específica com senha recente: plataforma para primeiro ADMIN e administrador global; Office ADMIN com membership real para membro da equipe. A correção revogará todas as ativações anteriores, registrará evento sanitizado, removerá a conta/membership exclusiva que nunca foi ativada e criará novo usuário e grants pendentes para o endereço corrigido. Não é uma regeneração: cada link continua fixo ao e-mail de sua geração. Depois da ativação, essa correção deixa de existir e a troca de usuário segue a gestão normal.

### 6. Novos usuários de escritório terão um único Office sem apagar legado

O serviço central de membership bloqueará a linha do usuário por e-mail antes de criar vínculo. Qualquer e-mail já existente em `users` será rejeitado pelos endpoints de criação, independentemente de grants; membros desativados serão tratados somente pelo fluxo explícito de reativação. Se o e-mail já possuir qualquer OfficeMembership ou `PlatformMembership`, a resposta continuará neutra para evitar vazamento e nova conta dual. A exceção dual existe somente no comando de bootstrap. Da mesma forma, um novo administrador global não poderá reutilizar usuário existente.

Não será criado índice único global em `office_user.user_id`, pois quebraria dados legados. Usuários legados com múltiplas memberships continuam alternando entre Offices e podem ser listados/geridos nos vínculos existentes, mas endpoints de criação não adicionam um novo. E-mail continua único em `users`.

Alternativa considerada: migrar ou fundir usuários multi-Office agora. Rejeitada por risco de perda de acesso e por estar fora do objetivo de onboarding.

### 7. Limite de equipe conta vagas ativas e pendentes

Somente um usuário com `OfficeMembership` ativa `OfficeRole::ADMIN` no `CurrentOffice` poderá listar, criar, alterar papel, desativar e reativar membros em `/api/v1/office/members`. O papel ADMIN efetivo de um contexto global sem membership real não satisfará esse gate. Criação, mudança para/de ADMIN, desativação, reativação e regeneração exigem senha recente. `office_id` do cliente será descartado.

O teto vem de `OfficeSubscription.max_users`: Starter 5, Professional 25 e Enterprise 200 nos defaults atuais. Memberships ativas e pendentes ocupam vaga; desativadas não. Administradores globais sem membership não contam. Locks no Office/assinatura impedem duas criações simultâneas de ultrapassar o limite. O último ADMIN ativo não pode ser desativado nem rebaixado.

Papel `OPERATOR` e `VIEWER` nunca administram equipe. Alterar papel não reinicia ativação de usuário já ativo. Desativar revoga sessões e ativações pendentes daquele vínculo e retira acesso imediatamente. Se o usuário não possuir outro grant ativo legado, ele também fica inativo; se ainda possuir outra membership legada ou global ativa, apenas o vínculo alvo é desativado.

Reativar exige vaga disponível e mantém o mesmo e-mail. Se o usuário não possuir outro grant ativo, o fluxo cria nova ativação por link ou senha provisória e usuário/membership permanecem inativos até a conclusão. Se um usuário legado ainda possuir outro grant ativo, a membership existente poderá ser reativada imediatamente, sem trocar a senha global ou criar novo usuário. O fluxo de reativação, e não uma nova criação com o mesmo e-mail, é a única forma de devolver esse vínculo.

### 8. APIs seguem três fronteiras e respostas sanitizadas

Rotas globais autenticadas:

- `GET /api/v1/platform/offices` e `GET /api/v1/platform/offices/{office}` para lista/detalhe com status e ativação sanitizada;
- `POST /api/v1/platform/offices`;
- `POST /api/v1/platform/offices/{office}/activation/regenerate`;
- `PATCH /api/v1/platform/offices/{office}/first-admin` somente enquanto pendente;
- `GET /api/v1/platform/admins` e `GET /api/v1/platform/admins/{user}`;
- `POST /api/v1/platform/admins`;
- `PATCH /api/v1/platform/admins/{user}` somente enquanto pendente;
- `POST /api/v1/platform/admins/{user}/activation/regenerate`.

Rotas tenant-scoped autenticadas:

- `GET|POST /api/v1/office/members`;
- `PATCH /api/v1/office/members/{membership}`;
- `PATCH /api/v1/office/members/{membership}/recipient` somente enquanto pendente;
- `POST /api/v1/office/members/{membership}/deactivate`;
- `POST /api/v1/office/members/{membership}/reactivate`;
- `POST /api/v1/office/members/{membership}/activation/regenerate`.

Rotas públicas limitadas:

- `POST /api/v1/activations/inspect` e `POST /api/v1/activations/complete` para link;
- `POST /api/v1/first-access/complete` para senha provisória.

Objetos de rota serão sempre reescopados pelo Office corrente ou pela autorização global. Listas e detalhes globais retornarão propósito, método, status e expiração da ativação, mas nunca hash ou segredo, permitindo localizar pendências e regenerar depois de fechar a resposta única. Middleware de sanitização/redação impedirá que campos `token`, `activation_url`, `temporary_password`, `password` e seus equivalentes entrem em logs, exceções, telemetria ou auditoria.

### 9. UI reutiliza arquétipos com conteúdo mínimo

O Admin terá lista/detalhe de Offices, incluindo pendentes, e `/admin/offices/new` com `UStepper` direto: Escritório, Plano, Primeiro administrador, Entrega e Revisão. Não haverá cards explicativos entre etapas; cada etapa usa rótulos, ajuda curta apenas quando necessária e validação inline. A tela final mostra o segredo uma vez, com ação de copiar e aviso curto de que fechar exige regeneração. Replay/timeout idempotente abrirá o detalhe com ação `Regenerar acesso`, sem alegar que o segredo antigo ainda está disponível.

Ativação pública reutilizará o layout de autenticação. Equipe ficará em `/settings/team`, baseada no arquétipo Settings/Members, com busca, papel/status compacto e ações no menu. Estados normais usam badge/toast; `UAlert` segue a política da change anterior.

## Risks / Trade-offs

- [Segredo aparece em access log ou monitoramento] → token no fragmento, segredo somente no body, redaction central, `no-store` e testes de logs/respostas.
- [Duas ativações iniciam dois períodos] → locks, condição de consumo atômica e teste concorrente.
- [Criação parcial deixa Office órfão] → uma transação e teste de falha em cada etapa.
- [Duas inclusões ultrapassam `max_users`] → lock da assinatura/Office e contagem ativa+pendente dentro da transação.
- [Usuário legado perde acesso] → nenhuma constraint destrutiva; enforcement somente no serviço de novos vínculos.
- [Senha provisória ou hash-sentinela permite login normal] → hashes não reutilizáveis, usuário inativo, `password_change_required` e endpoint dedicado que troca antes da sessão.
- [Segredo fechado acidentalmente não é recuperável] → mensagem curta antes de fechar e regeneração segura; não guardar plaintext é prioridade.
- [E-mail incorreto prende o Office pendente] → correção exclusiva antes da ativação, com revogação/cancelamento auditável e nova credencial fixa ao e-mail corrigido.
- [Office pendente entra em jobs fiscais] → `is_active=false`, assinatura sem permissão e testes de scheduler/queues.

## Migration Plan

1. Aplicar e validar `individualizar-perfis-plataforma-escritorio`.
2. Adicionar enums/colunas/tabela de ativação de forma aditiva, incluindo lifecycle de `password_change_required`; marcar todos os Offices, usuários, memberships e assinaturas existentes conforme seus estados atuais, sem criar ativações retroativas.
3. Implementar serviços e endpoints públicos com testes de hash, `no-store`, redaction, expiração e concorrência.
4. Implementar criação global e gestão tenant-scoped atrás de autorização e senha recente; manter o bootstrap como caminho de recuperação inicial.
5. Adicionar wizard, ativação, primeiro acesso e equipe usando os arquétipos fixados.
6. Verificar que jobs, franquias e período ignoram pendentes; executar suites completas e e2e dos dois métodos.
7. Habilitar os novos botões somente depois de backend e frontend compatíveis; não enviar comunicações externas.

Rollback: ocultar/desabilitar endpoints de criação e regeneração, revogar ativações ainda pendentes e preservar Offices pendentes para reconciliação. Offices já ativados continuam ativos; não retroceder datas comerciais nem recuperar segredos consumidos.

## Open Questions

Não há decisão funcional bloqueante. Textos finais de ajuda podem ser refinados durante a aplicação, desde que respeitem a política concisa e não mudem os dois métodos, a validade de sete dias ou a ausência de e-mail.
