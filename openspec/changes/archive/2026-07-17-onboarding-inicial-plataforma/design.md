## Context

O produto já separa `PlatformMembership` de `OfficeMembership` e possui o wizard autenticado `/admin/offices/new`, mas uma instalação produtiva vazia só pode obter acesso pelo comando `app:bootstrap-office`, que cria Office, assinatura e uma conta dual. O frontend também envia o login comum para `/` e oferece navegação tenant mesmo quando um `PLATFORM_ADMIN` não possui Office resolvido.

O onboarding é uma fronteira pública sensível: a primeira conclusão concede o maior papel global. Ele deve ser operável pela SPA estática atrás do nginx/Sanctum, permanecer default OFF, resistir a corrida e nunca expor o segredo de deploy em URL enviada ao servidor, persistência ou logs.

## Goals / Non-Goals

**Goals:**

- criar exclusivamente o primeiro `PLATFORM_ADMIN` e a configuração nominal da organização;
- encerrar o onboarding permanentemente e atomicamente, inclusive sob concorrência;
- aceitar exatamente quatro campos visíveis e transportar o segredo de deploy fora do formulário;
- autenticar após o commit e suportar `/admin` sem Office até o cadastro/ativação do primeiro tenant;
- fechar instalações legadas automaticamente durante a migration.

**Non-Goals:**

- criar Office, assinatura, perfil tenant, OfficeMembership ou AccountActivation;
- editar posteriormente o nome da organização nesta versão;
- alterar o seeder demo ou o comando `app:bootstrap-office`;
- enviar credenciais, habilitar integrações ou executar chamadas externas.

## Decisions

### 1. `platform_settings` será configuração e trava singleton

Uma tabela `platform_settings` terá uma única linha de chave fixa, `organization_name`, `onboarding_completed_at`, `onboarded_by_user_id` nullable com `nullOnDelete` e timestamps. A migration inserirá uma linha concluída com `APP_NAME` quando encontrar qualquer `User`, `Office` ou `PlatformMembership`, impedindo que upgrades de instalações existentes exponham o onboarding.

Na instalação vazia, a conclusão tentará inserir a linha singleton dentro da mesma transação que cria usuário e membership. A constraint única arbitra requisições concorrentes; ao final a linha recebe instante e autor. Rollback remove a reivindicação, mas exclusão posterior do usuário apenas torna o FK nulo e não reabre o fluxo.

Alternativa considerada: derivar disponibilidade apenas da ausência de usuário. Rejeitada porque apagar a conta reabriria uma elevação pública e não protegeria instalações legadas inconsistentes.

### 2. Disponibilidade é fail-closed e exige base realmente vazia

`INITIAL_ONBOARDING_ENABLED` terá default `false`; `INITIAL_ONBOARDING_TOKEN` deverá ter ao menos 32 caracteres quando habilitado. O status será verdadeiro somente com flag e token válidos, sem `platform_settings`, usuários, Offices ou memberships. O POST repetirá todas as verificações dentro da transação e rejeitará HTTP quando `APP_ENV=production`.

`GET /api/v1/onboarding/status` retornará apenas `available`. `POST /api/v1/onboarding`, sob CSRF stateful e throttle dedicado de 5/min por IP, receberá organização, e-mail, senha confirmada e `onboarding_token`. Erros de autorização serão neutros e todas as respostas terão `Cache-Control: no-store`.

### 3. Token fica no fragmento e somente em memória

O operador abrirá `/onboarding#token=<segredo>`. O fragmento não chega a nginx/Laravel; a página o copia para uma `ref`, executa `history.replaceState` imediatamente e nunca usa local/session storage. O token não será um campo visível e seguirá apenas no body do POST. Link sem token mostra bloqueio acionável sem revelar configuração interna.

Alternativa considerada: query string. Rejeitada por aparecer em access logs, histórico e referer. Expor o token em runtime config também foi rejeitado porque o bundle público o revelaria.

### 4. A criação global não depende de Office e converge no primeiro cadastro

O serviço normalizará e-mail, validará senha com `Password::default()` e criará `User` com nome fixo `Administrador da plataforma`, ativo, verificado, sem troca obrigatória e `selected_office_id=null`. Criará uma `PlatformMembership` ativa `PLATFORM_ADMIN` com `default_office_id=null` e nenhum outro agregado. Após o commit, fará login no guard web e regenerará a sessão; a resposta indicará `/admin/offices/new`.

O valor nulo é permitido somente porque ainda não existe Office referenciável. Quando esse administrador cadastrar o primeiro Office pelo fluxo autenticado, a mesma transação preencherá seu `PlatformMembership.default_office_id` caso ele ainda esteja nulo. O Office pode permanecer `PENDING_ACTIVATION`: o vínculo global já fica determinado, mas `CurrentOffice` continuará fail-closed até o Office se tornar ativo. A ativação posterior fará o contexto resolver sem seleção manual. Essa convergência não cria `OfficeMembership`, não altera `users.selected_office_id` e não inclui o administrador global na equipe ou no limite de vagas.

O nome da organização será somente leitura e exposto no payload autenticado `/me` para superfícies globais, sem substituir `APP_NAME` operacional nem alterar cache/session prefixes.

### 5. O frontend terá um estado global sem Office explícito

`/onboarding` reutilizará o layout `auth`, `UPageCard` e `UAuthForm` já usados pelo login. O middleware o tratará como rota pública especial: usuário autenticado vai a `/admin` quando global, e visitante segue apenas enquanto o status permite.

Redirect pós-login priorizará `is_platform_admin → /admin`. Quando `context_status=office_context_required`, redirects pendentes e navegação direta para rotas fora de `/admin` serão normalizados para `/admin`; o shell esconderá Home, clientes, fiscal, documentos, operações e quick actions, mantendo apenas Admin e destinos secundários. `/admin` reutilizará o arquétipo Home/`UDashboardPanel` e mostrará empty state com ação para `/admin/offices/new`; não chamará resumo tenant sem Office.

## Risks / Trade-offs

- [Primeiro visitante toma a plataforma] → flag default OFF, token forte fora do bundle, HTTPS, CSRF, throttle e base vazia.
- [Token ou senha vazam] → fragmento removido, body apenas, redaction existente, `no-store`, campos limpos e testes sobre URL/log/resposta.
- [Duas requisições criam dois admins] → singleton único e revalidação transacional; somente uma inserção pode vencer.
- [Upgrade existente abre onboarding] → backfill concluído na migration se qualquer dado estrutural existir.
- [Admin global sem Office chama API tenant] → navegação/redirect preventivos e gates backend existentes continuam fail-closed.
- [Migration usa `APP_NAME` mutável] → o valor serve apenas como fallback legado e permanece editável somente por migração/ops nesta versão.

## Migration Plan

1. Aplicar a migration aditiva e confirmar backfill em instalações existentes.
2. Publicar backend e frontend com onboarding desabilitado por padrão.
3. Em instalação produtiva vazia, configurar flag e token forte em `.env.prod`, acessar a URL HTTPS com fragmento e concluir.
4. Remover/desabilitar as variáveis no próximo deploy; a trava de banco já mantém o fluxo fechado.
5. Cadastrar o primeiro Office em `/admin/offices/new` e ativar seu primeiro ADMIN pelo fluxo existente.

Rollback de código mantém `platform_settings` e o primeiro usuário; remover a rota/UI não remove grants nem reabre onboarding. Recuperação de acesso usa reset de senha ou operação CLI, nunca exclusão da trava.

## Open Questions

Nenhuma decisão bloqueante.
