## Context

O `DatabaseSeeder` é protegido por ambiente e cria o Office `demo`, assinatura ativa e três usuários de Office (`operador@example.com`, `admin@example.com` e `viewer@example.com`), todos com a senha local convencional `password`. Os seeders fiscal e Work também criavam Offices sentinela próprios, fazendo detalhes técnicos aparecerem como identidades de produto no seletor. Nenhum usuário tenant representa corretamente o perfil exclusivamente global: o `admin@example.com` possui OfficeMembership real e não serve para validar capacidades próprias de um `PLATFORM_ADMIN` sem vínculo tenant.

O domínio já possui `User`, `PlatformMembership.default_office_id`, separação de perfis, factories e testes de contexto global. A change `cadastrar-ativar-offices-usuarios` implementa o onboarding real de novos administradores globais, mas esse fluxo pressupõe um administrador autenticado e não deve ser usado para massa demo reproduzível.

### Dados da fixture

| Dado | Valor/estado inicial | Motivo |
|------|----------------------|--------|
| `users.name` | `Admin Plataforma Demo` | Identidade explícita e distinta do admin do Office |
| `users.email` | `plataforma@example.com` | Endereço estável e reservado à fixture |
| `users.password` | hash de `password`, somente na primeira criação | Mesma convenção dos usuários demo existentes |
| `users.email_verified_at` | preenchido | Conta pronta para login local |
| `users.is_active` | `true` | Conta pronta para uso |
| `users.password_change_required` | `false` | Não usa onboarding/primeiro acesso |
| `users.selected_office_id` | `null` | Seleção global não é membership tenant |
| `platform_memberships.role` | `PLATFORM_ADMIN` | Grant global exclusivo |
| `platform_memberships.is_active` | `true` | Grant pronto para uso |
| `platform_memberships.default_office_id` | Office `plataforma` | Contexto global próprio e determinístico |
| OfficeMembership | inexistente | Não converter em conta dual nem consumir seat |
| `account_activations` | inexistente | Fixture pronta, fora do onboarding real |
| TOTP/2FA | ausente | Produto não exige mais 2FA |

## Goals / Non-Goals

**Goals:**

- Tornar o ambiente demo reproduzível com um perfil exclusivamente `PLATFORM_ADMIN` pronto para login.
- Garantir que rodar o seed novamente não crie duplicatas nem redefina a senha já alterada localmente.
- Falhar em colisões incompatíveis em vez de promover automaticamente um usuário existente.
- Provar a separação entre Office padrão global e OfficeMembership.
- Manter a fixture impossível de executar fora de `local`/`testing`.
- Reduzir a massa limpa a dois Offices ativos com nomes de produto claros: `Plataforma` e `Contador Genérico`.
- Preservar isolamento multi-tenant reutilizando `Plataforma` como sentinela técnico dos módulos demo.

**Non-Goals:**

- Provisionar contas reais em staging/produção.
- Substituir `app:bootstrap-office` ou o onboarding real de administradores globais.
- Criar conta dual, ativação ou endpoint.
- Semear dados fiscais, habilitar feature flags ou realizar chamadas externas.

## Decisions

### 1. Seeder dedicado chamado pelo `DatabaseSeeder`

Criar `PlatformAdminDemoSeeder` e chamá-lo depois da criação do Office demo e da assinatura. O seeder dedicado também verificará diretamente `local`/`testing`, para continuar seguro quando alguém executar `db:seed --class=PlatformAdminDemoSeeder` sem passar pelo guard do `DatabaseSeeder`.

Alternativa considerada: adicionar as linhas diretamente ao `DatabaseSeeder`. Rejeitada porque mistura a identidade global ao agregado tenant e dificulta o teste isolado do guard e da idempotência.

### 2. Fixture exclusivamente global com Office próprio

O `DatabaseSeeder` garantirá um Office `plataforma` ativo e o sub-seeder criará `User` e `PlatformMembership` em uma transação, verificando que não existe OfficeMembership para o usuário. `default_office_id` apontará a esse Office próprio, mas `selected_office_id` permanecerá nulo. Isso permite exercitar o contexto global sem adulterar equipe ou limite de usuários. O Office `demo` será reconciliado com o nome `Contador Genérico`.

Alternativa considerada: promover `admin@example.com` a conta dual. Rejeitada porque mascararia justamente as diferenças de autorização que o produto precisa testar.

### 3. Primeira criação determinística, repetição conservadora

Na primeira execução, a fixture receberá os valores da tabela acima. Em nova execução, o seeder localizará o e-mail reservado e aceitará somente a identidade já exclusivamente global; preservará `password`, nome e timestamps do usuário, garantindo a `PlatformMembership` ativa e o Office demo padrão sem duplicar registros.

Se o e-mail existir com OfficeMembership, outro papel/grant ou estado incompatível, a execução falhará com mensagem explícita e não concederá privilégio. Como User e PlatformMembership nascem na mesma transação, não haverá recuperação automática de usuário órfão por promoção silenciosa.

Alternativa considerada: `updateOrCreate` irrestrito pelo e-mail. Rejeitada porque poderia transformar uma conta tenant em administradora global e redefinir credenciais locais sem intenção.

### 4. Senha demo conhecida apenas em ambiente protegido

A senha `password` seguirá a convenção já existente e será definida apenas quando o usuário for criado. O valor não será lido de `.env`, para não sugerir configuração de credencial real, e o sub-seeder recusará ambientes diferentes de `local`/`testing`.

Alternativa considerada: usar ativação pendente ou senha aleatória. Rejeitada porque reintroduziria o cadastro manual que a fixture pretende eliminar e duplicaria o onboarding real.

### 5. Sentinelas técnicos reutilizam o Office da Plataforma

Os defaults `FISCAL_DEMO_SENTINEL_SLUG` e `WORK_DEMO_SENTINEL_SLUG` apontarão para `plataforma`. Assim, os dados sentinela continuam em Office diferente de `demo`, preservando as provas de isolamento, mas deixam de criar `demo-sentinel` e `demo-work-sentinel`. Ao repetir o `DatabaseSeeder`, esses dois slugs legados serão apenas desativados; Offices manuais ou com outros slugs não serão alterados.

### 6. Seletor preserva a composição de uma linha do TeamsMenu e amplia a exploração

O gatilho do seletor global exibirá somente o nome do Office corrente em uma linha, com truncamento nativo, avatar e chevron do `TeamsMenu.vue`. O overlay usará o `USelectMenu` do Nuxt UI, será mais largo que o gatilho dentro do viewport e oferecerá busca por nome ou slug, descrições legíveis e estado vazio explícito. O rodapé fixo mostrará visualmente apenas um escudo, `Plataforma` e, quando for diferente, o Office corrente separado por ponto médio. O papel técnico `PLATFORM_ADMIN` permanecerá no `aria-label` e em texto exclusivo para leitores de tela, sem linguagem interna ostensiva, sem comprimir o cabeçalho da sidebar nem adicionar borda privilegiada.

## Risks / Trade-offs

- [Credencial demo conhecida ser executada por engano em produção] → guard duplicado no `DatabaseSeeder` e no sub-seeder, com teste explícito de recusa.
- [E-mail reservado colidir com usuário local criado manualmente] → validar grants e falhar sem promover ou sobrescrever a conta.
- [Reexecução apagar uma senha alterada durante desenvolvimento] → definir senha somente na criação e testar preservação do hash.
- [Office da Plataforma ausente ou inativo] → falhar com instrução acionável; o `DatabaseSeeder` mantém a ordem Offices → assinaturas → admin global.
- [Dados manuais serem apagados ao limpar o seed] → desativar somente os dois slugs sentinela historicamente reservados; não remover qualquer outro Office.
- [Fixture global aparecer na equipe ou consumir vaga] → zero OfficeMembership e teste por API/contagem de seats.

## Migration Plan

1. Adicionar o sub-seeder e seu teste isolado.
2. Integrá-lo ao `DatabaseSeeder` depois dos Offices `Plataforma` e `Contador Genérico`.
3. Rodar o seed duas vezes em `testing` e comprovar ids/contagens estáveis e senha preservada.
4. Validar login/contexto global e ausência na equipe do Office.
5. Rollback de código: remover a chamada e o sub-seeder; dados demo existentes podem ser apagados manualmente apenas em ambiente local.

## Open Questions

Nenhuma decisão de produto bloqueante. Nome, e-mail e senha são valores demo explícitos e podem ser ajustados durante o apply se já houver uma convenção local mais forte documentada no repositório.
