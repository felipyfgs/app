## Context

Hoje `platform_memberships` possui unicidade apenas por `(user_id, role)`, portanto vários usuários diferentes podem receber `PLATFORM_ADMIN`. O backend oferece criação/ativação plural em `PlatformAdminUserController` e `CreatePendingPlatformAdminService`; o frontend replica esse modelo em `/admin/admins`. Onboarding inicial, bootstrap e `PlatformAdminDemoSeeder` já tendem a criar uma única conta, mas não existe uma invariável estrutural que impeça concorrência ou outro caminho de cadastro.

A instalação é definida como uma aplicação ligada a um banco de dados. Ela continua multi-Office e multiusuário; apenas a autoridade global é singleton. `PLATFORM_ADMIN` permanece fora de `EnsureOfficeContext`, não cria `OfficeMembership` implicitamente e não autoriza mutação fiscal de um Office sem membership real.

Esta change depende da entrega coordenada de `adaptar-aprovacoes-serpro-proprietario-unico`, porque credenciais produtivas e retirada de kill switch hoje esperam dois administradores globais.

## Goals / Non-Goals

**Goals:**

- Garantir no banco e no domínio no máximo um `PLATFORM_ADMIN` por instalação, inclusive sob concorrência.
- Considerar esse vínculo o Proprietário da instalação, independentemente de estar ativo, pendente ou temporariamente sem acesso.
- Manter zero proprietários somente antes do onboarding inicial; depois dele, recuperação não cria um segundo vínculo.
- Substituir API e UI plurais por uma superfície singular.
- Oferecer consolidação e recuperação operacionais explícitas, auditadas e sem senha em argumentos ou logs.

**Non-Goals:**

- Limitar usuários, Offices ou papéis de Office.
- Conceder ao proprietário acesso fiscal implícito ou confiar em `office_id` do cliente.
- Criar uma equipe global, um papel de coadministrador ou aprovação global delegada.
- Ligar flags, canais fiscais ou realizar chamadas SERPRO live.
- Redesenhar o shell do painel fora do arquétipo existente.

## Decisions

### 1. Unicidade em duas camadas

O serviço de domínio fará uma verificação antecipada e retornará conflito sanitizado (`platform_owner_already_exists`), mas o banco será a autoridade final com índice único parcial para `role = 'PLATFORM_ADMIN'`. PostgreSQL e SQLite suportam esse índice; a migration usará SQL compatível por driver e terá teste nos dois contratos de schema disponíveis.

Somente validação de aplicação foi rejeitada porque duas requisições concorrentes poderiam passar pela contagem antes de gravar. Tornar apenas `(user_id, role)` único também foi rejeitado porque já é a regra atual e não limita usuários distintos.

### 2. Ciclo de vida singleton

Uma base estruturalmente vazia pode ter zero proprietários. O onboarding inicial cria a única `PlatformMembership`; bootstrap e seed reutilizam a mesma regra e falham se qualquer vínculo global já existir, mesmo que ainda não exista Office. Inatividade, expiração de ativação ou perda de acesso não liberam a vaga global: o vínculo existente deve ser recuperado ou transferido.

Excluir o usuário proprietário por cascade será bloqueado no domínio. Desativação comum também não poderá deixar a instalação sem caminho de recuperação. Isso distingue perda de acesso de intenção de instalar uma segunda plataforma.

### 3. API e painel singulares

As rotas plurais `/api/v1/platform/admins` e a criação de administrador global serão removidas. A superfície autenticada passará a `GET /api/v1/platform/owner` e `PATCH /api/v1/platform/owner`, protegida por `EnsurePlatformAdmin`; alterações sensíveis continuam exigindo senha recente. O PATCH atualiza somente a identidade existente e seu Office padrão, nunca cria outra `PlatformMembership`.

No Nuxt, `/admin/admins` e “Novo administrador global” serão substituídos por `/admin/owner`, rotulado “Proprietário”. A tela seguirá `panel-ui`/`ui-archetype` e apresentará uma identidade singular, sem tabela ou linguagem de equipe global.

Manter endpoints plurais como aliases foi rejeitado porque perpetuaria um contrato incompatível e permitiria clientes antigos tentarem criar um segundo proprietário. A quebra será explícita e coberta por testes de rota.

### 4. Recuperação e transferência são operações de host

Um comando interativo `app:platform-owner:recover` permitirá ao operador com acesso ao host:

- corrigir nome/e-mail e definir nova senha no usuário atual; ou
- transferir atomicamente a única `PlatformMembership` para um usuário-alvo explicitamente escolhido.

Senha será recebida por prompt oculto e nunca por argumento, stdout, log ou auditoria. A operação exigirá confirmação textual, lock transacional, revogará sessões/tokens do titular anterior e do alvo, manterá exatamente um vínculo e registrará auditoria sanitizada. Transferência não significa coexistência: o anterior perde o papel no mesmo commit.

Reinstalar a aplicação como única recuperação foi rejeitado por colocar dados e continuidade operacional em risco. Recuperação self-service por e-mail também ficou fora por ampliar a superfície pública privilegiada.

### 5. Bases legadas exigem consolidação explícita

A migration contará vínculos `PLATFORM_ADMIN` antes do índice. Se houver mais de um, falhará com mensagem operacional e sem modificar dados. Um comando `app:platform-owner:consolidate --keep=<user-id>` removerá apenas os vínculos globais excedentes sob confirmação, revogará sessões dos afetados e preservará seus `OfficeMembership` e usuários comuns.

Nenhum registro será escolhido por data, e-mail ou atividade automaticamente. Essa decisão evita promover silenciosamente a pessoa errada ou apagar memberships de Office legítimas.

### 6. Entrega coordenada com SERPRO

O código que adapta aprovações SERPRO deve estar pronto antes ou no mesmo release que ativa o índice singleton. A aplicação não será considerada pronta para produção se mensagens ou serviços ainda aguardarem um segundo `PLATFORM_ADMIN`. O canário faturável continua separado: proprietário e `Office ADMIN` devem ser usuários distintos.

## Risks / Trade-offs

- [Comprometimento do único proprietário concentra autoridade] → exigir senha recente, confirmação explícita nas operações críticas, auditoria encadeada e recuperação restrita ao host.
- [Migration encontra instalação com vários administradores] → falhar antes do DDL e exigir consolidação explícita com relatório sanitizado.
- [Transferência deixa sessões antigas válidas] → revogar sessões e tokens de ambos os usuários dentro do fluxo e testar novo login/autorização.
- [Cascade remove o único vínculo] → bloquear exclusão comum do proprietário e cobrir integridade com testes de serviço e banco.
- [Índice varia entre PostgreSQL e SQLite] → usar índice parcial suportado por ambos e executar testes de migration/concorrência.
- [Deploy parcial quebra ações SERPRO] → tratar a change companheira como requisito de release e manter flags/kill switch fail-closed até a verificação conjunta.

## Migration Plan

1. Entregar comandos de inspeção/consolidação e o comportamento SERPRO compatível com proprietário único.
2. Executar preflight; em bases com mais de um vínculo, escolher explicitamente o titular e consolidar antes da migration.
3. Aplicar a migration de índice único parcial.
4. Trocar rotas/composables/UI plurais pela superfície singular e remover serviços de criação pendente global.
5. Verificar onboarding vazio, seed, bootstrap, concorrência, recuperação, autorização tenant-scoped e o conjunto SERPRO coordenado.

Rollback do índice somente será permitido se a versão anterior da aplicação realmente precisar voltar; isso não recria um segundo administrador automaticamente. Em produção, preferir correção para frente mantendo kill switches ativos, pois voltar ao fluxo de dois `PLATFORM_ADMIN` sem duas identidades tornaria operações críticas indisponíveis.

## Open Questions

Nenhuma. O nome canônico será “Proprietário da instalação”, mantendo `PLATFORM_ADMIN` como identificador técnico.
