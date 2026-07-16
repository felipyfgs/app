## Context

O produto usa um único `User` com memberships independentes em `platform_memberships` e `office_user`. A change concluída `separar-configuracao-escritorio-plataforma-serpro` introduziu seleção global e contexto privilegiado, mas hoje essa seleção vive somente em sessão/cache, concede papel efetivo de ADMIN inclusive a partes de Work e convive com `EnsureAdminTwoFactor` e gates TOTP antigos. O frontend também mantém um banner privilegiado, contrato divergente para `GET /platform/offices` e uma página `/settings` com mensagens repetidas.

Esta change prepara a fronteira de perfis para a posterior criação de Offices e usuários. O tenant continua sendo `Office`; nenhum controller tenant-scoped aceitará `office_id` do body/query. O acesso fiscal global já decidido permanece, mas Work terá uma exceção explícita baseada em membership real. As telas autenticadas serão alteradas com `panel-ui` → `ui-archetype`, preservando o shell do template.

## Goals / Non-Goals

**Goals:**

- representar corretamente a conta dual do bootstrap e administradores globais sem membership;
- resolver um Office padrão persistente para a plataforma no servidor;
- manter um shell único com identidade visual compacta e rotas separadas;
- permitir suporte de leitura em Work sem conceder autoria ou execução fictícia;
- substituir todos os gates TOTP/2FA por reconfirmação de senha de quinze minutos;
- tornar `/settings` conciso e cobrir a regra de conteúdo com testes de superfície;
- reconciliar as main specs que ainda normatizam TOTP/2FA.

**Non-Goals:**

- criar Office, usuário, convite ou ativação;
- excluir imediatamente colunas ou segredos TOTP legados;
- mudar plano, franquia, cobrança ou gates operacionais não relacionados à identidade;
- fazer smoke real, habilitar flags/canais ou alterar contrato SERPRO;
- redesenhar o shell do dashboard ou criar notificações externas.

## Decisions

### 1. Perfil será composição de vínculos, não um tipo exclusivo no usuário

O sistema manterá `PlatformMembership` e `OfficeMembership` como concessões independentes. A primeira execução de `app:bootstrap-office` criará, na mesma transação, um único usuário com `PLATFORM_ADMIN`, membership `OfficeRole::ADMIN` no Office criado e esse Office como padrão global. Essa conta dual é uma exceção intencional e não será dividida em dois usuários.

Novos administradores globais, entregues pela change seguinte, receberão somente `PlatformMembership`; portanto não aparecerão em consultas de equipe. Usuários de escritório continuam sendo definidos pelas memberships reais existentes. O payload de `/me` exporá capacidades derivadas (`is_platform_admin`, papel real, modo de acesso e Office corrente), sem inventar um enum de perfil que não representaria a conta dual nem dados legados.

Alternativa considerada: `users.profile_type = PLATFORM|OFFICE`. Rejeitada porque impediria o bootstrap dual, exigiria exceções permanentes e não eliminaria a necessidade das memberships.

### 2. O Office padrão global será persistente e resolvido no servidor

`platform_memberships` receberá `default_office_id`, referenciando `offices`. Para o bootstrap e novos administradores o campo será obrigatório no serviço de domínio. O backfill escolherá deterministicamente o Office ativo mais antigo apenas para memberships globais legadas sem valor e registrará a decisão sem criar membership de Office.

Ao montar o contexto, o servidor usará nesta ordem: seleção global válida da sessão, `default_office_id` ativo e, se nenhum for válido, estado `office_context_required`. Não aceitará `office_id` de body/query para resolver tenant. Um Office padrão inativo não será substituído silenciosamente; o administrador continuará acessando `/admin/*` para escolher outro Office válido. Uma seleção global concluída atualizará também `default_office_id`, de modo que o novo contexto sobreviva ao próximo login.

A seleção global continuará separada de `users.selected_office_id`. `CurrentOffice` passará a carregar explicitamente `office`, ator, `access_mode` e `real_membership` anulável. Quando o ator também possuir membership ativa no Office selecionado, esse campo preservará o vínculo e o papel real para as policies que exigem autoria; as demais policies poderão usar a capacidade global já definida. `/me` publicará separadamente `access_mode` e `real_office_role`, sem transformar privilégio em membership.

Sem contexto válido, `/me` responderá `200` com `current_office=null` e `context_status=office_context_required`; endpoints tenant-scoped responderão `409` com o código estável `office_context_required`. Rotas globais continuarão disponíveis para corrigir o padrão.

Alternativa considerada: escolher o primeiro Office ativo em toda requisição. Rejeitada por tornar o escopo imprevisível e esconder a desativação do Office configurado.

### 3. Work terá leitura de suporte e mutações baseadas em membership real

Rotas de leitura do módulo Work poderão usar o contexto global selecionado e terão queries escopadas ao Office do servidor. Toda operação que gere autoria, estado ou exportação — criar/editar/excluir, executar, reivindicar, atribuir, comentar, anexar evidência e exportar — exigirá uma `OfficeMembership` ativa do ator naquele Office e a capacidade do papel real. O papel efetivo privilegiado não satisfará esse gate.

A defesa será duplicada no agrupamento de rotas/middleware e nas policies/serviços de Work, cobrindo também comandos que não seguem estritamente o verbo HTTP. Uma conta dual poderá mutar Work somente no Office em que possui membership; um administrador global comum recebe `403` sem efeitos.

Alternativa considerada: esconder Work inteiro da plataforma. Rejeitada porque impediria diagnóstico de suporte. Permitir escrita privilegiada foi rejeitado porque criaria autor, responsável ou evidência sem membership válida.

### 4. Senha recente substituirá TOTP/2FA sem remover os outros gates

Login, navegação e leitura usarão e-mail e senha, sem desafio TOTP para nenhum perfil. `POST /api/v1/auth/confirm-password` registrará exclusivamente na sessão Sanctum corrente o instante da confirmação do próprio ator; cache global por usuário não poderá confirmar outra sessão. O gate será válido por quinze minutos, calculado no servidor, e será invalidado em logout, troca/redefinição de senha, desativação do usuário ou rotação da sessão de segurança.

Toda ação hoje protegida por TOTP/2FA passará a exigir senha recente quando humana e sensível: A1, CNPJ, mutações fiscais, contrato/credenciais SERPRO, kill switch, canário e aprovações globais. A change seguinte aplicará o mesmo gate à criação de Office/admin, regeneração de ativação e gestão sensível da equipe. Quatro olhos continua exigindo dois usuários distintos e confirmação própria de cada um. Aprovações e auditorias persistentes guardarão `confirmation_method=PASSWORD` e `confirmed_at`, nunca a senha ou seu hash.

CLI e jobs não poderão criar aprovação humana, preencher `confirmed_at` nem contornar esse gate. Comandos de console ficam limitados a inspeção/manutenção não autorizativa; qualquer operação que exija decisão humana deverá consumir uma aprovação criada por usuário autenticado via HTTP e ainda validar os demais gates no momento da execução. Jobs não podem transformar uma autorização de leitura em mutação.

Colunas TOTP existentes ficam ignoradas e sem novas telas durante uma janela de compatibilidade; remoção física pode ocorrer em migration posterior depois de verificar rollback e clientes antigos.

Alternativa considerada: manter TOTP apenas para plataforma. Rejeitada pela decisão de autenticação uniforme e por perpetuar dois fluxos sem necessidade de produto.

### 5. Rotas e contrato de Offices terão uma fronteira única

`/api/v1/platform/*` permanecerá fora de `EnsureOfficeContext` e protegido por `EnsurePlatformAdmin`; endpoints tenant-scoped continuarão sob `EnsureOfficeContext`, que descarta `office_id` do request. `/admin/*` será reservado globalmente e Departamentos passará para `/settings/departments`, reutilizando as APIs tenant-scoped de Work. O endpoint de seleção atualizará em uma transação a sessão global e o Office padrão do ator.

`GET /api/v1/platform/offices` terá o envelope canônico:

```json
{
  "data": {
    "offices": [],
    "selected_office_id": null,
    "default_office_id": null
  }
}
```

Cada resumo conterá somente identidade institucional, status e `selectable`; a lista incluirá também Offices inativos ou pendentes para o Admin, enquanto o seletor permitirá escolher somente `selectable=true`. O composable e o Admin consumirão esse contrato sem fallback para `/platform/tenants`. Seleção continua em endpoint dedicado, validando o destino no servidor.

Alternativa considerada: alterar o backend para devolver somente um array porque o frontend atual espera isso. Rejeitada porque perderia os identificadores corrente/padrão e recriaria chamadas paralelas.

### 6. O shell será único e a identidade privilegiada será compacta

A navegação derivará grupos de capacidade de `/me`: módulos normais para qualquer usuário com contexto de Office e grupo `Admin` adicional para `PLATFORM_ADMIN`. Usuário sem papel global não verá nem abrirá `/admin/*`. A conta dual verá ambos sem trocar de aplicação.

`PrivilegedContextBanner` será removido. O seletor exibirá apenas `Plataforma · <nome do Office>` em selo compacto e permitirá a troca. O backend continuará sendo a fonte de autorização; ocultar item de menu não substitui middleware.

### 7. Concisão da UI será contrato testável

Uma política de conteúdo comum será aplicada a `/settings` e aos seus modais. `UAlert` ficará restrito a erro real, bloqueio com ação ou risco imediato. Estados normais e sucesso usarão badge, toast ou empty state. Cada página terá no máximo uma introdução curta e cada seção, no máximo uma descrição de uma linha.

A tela removerá “Configuração unificada em implantação”. Consentimento aceito será compacto; A1 ausente terá ação “Enviar certificado”; mensagem de segurança/sem download aparecerá uma única vez no fluxo de upload. Impactos de trocar CNPJ ou remover A1 ficarão apenas no modal, em até duas frases, sem `UAlert` aninhado. Termos internos como `CurrentOffice`, vault, OAuth, tokens, implementação pendente e auditoria interna não serão renderizados.

Além de testes de componentes/e2e, um teste de superfície pesquisará as rotas e componentes de Settings por mensagens removidas, `UAlert` informativo e duplicação das explicações críticas. O teste não substituirá revisão visual, mas impedirá a regressão específica relatada.

## Risks / Trade-offs

- [Policy genérica continuar autorizando escrita Work pelo papel efetivo ADMIN] → middleware dedicado, policies com membership real e matriz de testes para todos os endpoints mutantes/extrações.
- [Conta dual perder poderes legítimos de Work] → preservar e consultar a membership real mesmo no contexto visual de Plataforma.
- [Office padrão inválido deixar módulo tenant sem contexto] → estado explícito com acesso ao Admin/seletor, sem fallback silencioso.
- [Remoção transversal de TOTP enfraquecer outro gate] → matriz de todas as referências TOTP/2FA, substituição por senha recente e testes que mantêm flags, assinatura, orçamento, idempotência e kill switch.
- [Sessão de confirmação sobreviver à troca de senha] → invalidar timestamp e sessões aplicáveis no mesmo fluxo.
- [Teste textual ficar frágil] → verificar somente regras proibidas estáveis e usar assertions semânticas de componentes para os demais casos.
- [Deltas conflitarem com a change anterior ainda não arquivada] → aplicar esta change depois da anterior e reconciliar os requisitos de acesso global no sync/archive.

## Migration Plan

1. Reconciliar o estado final da change `separar-configuracao-escritorio-plataforma-serpro` sem arquivar main specs antes de código e testes verdadeiros.
2. Adicionar `default_office_id` de forma nullable, fazer backfill determinístico e só então exigir valor nos serviços de criação de administradores globais.
3. Atualizar bootstrap e `/me`; validar a conta dual e administradores existentes antes de mudar policies.
4. Introduzir o gate de membership real em Work e executar a matriz de negação antes de remover o gate TOTP.
5. Substituir middleware/referências TOTP por senha recente, mantendo temporariamente os dados legados para rollback.
6. Corrigir o contrato de Offices e trocar sidebar, selo, rotas de Departamentos e conteúdo de Settings com testes.
7. Rodar suites backend/frontend/e2e e OpenSpec strict; só então sincronizar as main specs.

Rollback: restaurar o roteamento/UI anterior e desabilitar a resolução automática do Office padrão sem apagar o backfill. O timestamp de senha recente pode ser ignorado com segurança. Não recriar memberships nem reativar TOTP automaticamente sem uma migration/revisão explícita.

## Open Questions

Não há decisão funcional bloqueante. A remoção física dos campos TOTP e a política operacional para um Office padrão desativado além do estado `office_context_required` ficam para manutenção posterior, sem mudar o contrato desta change.
