## Context

`SerproCredentialVersionService` força no mínimo duas aprovações distintas de `PLATFORM_ADMIN` antes do cutover. `SerproRolloutApprovalService` também presume dois olhos para todas as ações, inclusive retirada de kill switch, e compartilha a mesma estrutura com aprovações de canário/rollout. Com um único Proprietário da instalação, os fluxos globais ficam impossíveis, mas o canário faturável ainda pode e deve usar duas pessoas com papéis distintos: Proprietário e `Office ADMIN`.

As rotas continuam sob `/api/v1/platform/*`, `auth:sanctum`, `EnsureActiveUser`, `EnsurePlatformAdmin` e reconfirmação de senha recente. Segredos permanecem no `SecureObjectStore`; nenhuma aprovação substitui preflight de PFX, OAuth mTLS, flags, kill switches ou demais gates.

## Goals / Non-Goals

**Goals:**

- Permitir cutover, ativação/substituição/desbloqueio produtivo e retirada de kill switch pelo proprietário único.
- Compensar a retirada do segundo administrador com senha recente, confirmação textual específica, motivo, janela e auditoria persistente.
- Tornar a política de aprovação explícita por ação para não enfraquecer canários ou promoções que ainda exigem duas pessoas.
- Manter falha fechada e nunca executar chamada fiscal de negócio durante preflight/cutover.

**Non-Goals:**

- Executar OAuth ou SERPRO live em CI, ligar flags, retirar kill switch durante deploy ou promover mutações fiscais.
- Alterar a autorização separada do canário faturável (`PLATFORM_ADMIN` + `Office ADMIN` distintos).
- Expor PFX, senha, Consumer Secret, tokens, XML ou identificadores internos do vault.
- Permitir que CLI/job fabrique confirmação humana.

## Decisions

### 1. Política de aprovação por ação

`SerproRolloutApproval` receberá uma política explícita:

- `OWNER_CONFIRMATION` para `KILL_SWITCH_OFF`, `KILL_SWITCH_SOLUTION_OFF`, `CONTRACT_ACTIVATE` e `CREDENTIAL_CUTOVER`;
- `DUAL_ROLE` para canário faturável e promoções que já exigem pessoas distintas.

Registros existentes serão classificados como `DUAL_ROLE`; pendências das quatro ações globais serão expiradas e recriadas, nunca convertidas automaticamente. `isFullyApproved()` e o serviço de execução decidirão pela política e por allowlist fechada, não por uma contagem configurável genérica.

Reduzir globalmente o número mínimo para um foi rejeitado porque liberaria o canário com uma pessoa. Criar um novo papel de coaprovador global também foi rejeitado porque recriaria a equipe global que a decisão de produto removeu.

### 2. Confirmação reforçada do proprietário

Para política `OWNER_CONFIRMATION`, a requisição deve provar simultaneamente:

- ator autenticado é o `PLATFORM_ADMIN` singleton;
- senha foi reconfirmada na sessão há no máximo quinze minutos;
- frase exata e específica da operação, fornecida pelo servidor, foi digitada;
- motivo não vazio e janela de mudança vigente foram informados;
- aprovação pertence ao mesmo recurso, ambiente e ação executados.

A confirmação será persistida com ator, timestamps, política, código da frase, motivo, janela, escopo sanitizado e resultado. A frase não é um segundo fator; ela reduz ação acidental. TOTP continua descontinuado.

### 3. Cutover exige uma aprovação válida, não um contador configurável

`SerproCredentialVersionService` deixará de aplicar `max(2, cutover_approvals_required)`. O cutover buscará uma aprovação `OWNER_CONFIRMATION` vigente e vinculada à versão/contrato, validará o proprietário e a consumirá atomicamente com a mudança. Reuso, expiração, recurso divergente ou aprovação criada fora do fluxo HTTP bloqueiam a operação.

Leitura do vault, horizonte do certificado e OAuth mTLS real da versão pendente continuam obrigatórios antes de tornar a versão `ACTIVE`; falha restaura a versão anterior e mantém a nova como `VERIFIED`.

### 4. Kill switch liga imediatamente e desliga com confirmação

Ativar kill switch continua imediato e fail-closed. Retirar kill switch global ou de solução usa `OWNER_CONFIRMATION` e executa no mesmo fluxo transacional após todos os gates. A resposta deixa de dizer “aguardando segundo PLATFORM_ADMIN” e informa sucesso ou bloqueio objetivo.

### 5. Canário e promoções preservam separação humana

A política `DUAL_ROLE` validará os papéis esperados, não apenas IDs diferentes. Para canário faturável, uma aprovação deve vir do Proprietário e outra de `Office ADMIN` com membership ativa no Office delimitado; a mesma conta dual não pode preencher ambos. `ROLLOUT_PROMOTE` permanece dual até que sua capability defina outra regra.

Esse isolamento evita que a adaptação das credenciais altere silenciosamente `serpro-go-live-controlado`.

### 6. CLI e jobs só consomem aprovação persistida válida

Comandos e jobs podem executar trabalho técnico somente quando recebem o identificador de uma aprovação HTTP válida e ainda não consumida. Eles não podem criar `confirmed_at`, escolher ator, reduzir política ou usar bypass em produção. `skip_oauth` permanece exclusivo de `local/testing`.

## Risks / Trade-offs

- [Uma pessoa substitui dois olhos nas ações globais] → exigir senha recente, frase específica, motivo, janela curta, escopo exato e auditoria encadeada; acesso ao proprietário e ao host torna-se crítico.
- [Serviço compartilhado enfraquece o canário] → política por ação em allowlist fechada e testes separados para `OWNER_CONFIRMATION` e `DUAL_ROLE`.
- [Aprovação antiga é reinterpretada como autorização única] → expirar pendências globais existentes e nunca fazer backfill para `OWNER_CONFIRMATION`.
- [Confirmação é reutilizada em outro recurso] → vincular e consumir atomicamente por ação, subject, ambiente e versão.
- [Segredo aparece em auditoria ou resposta] → persistir somente metadados sanitizados; materiais continuam no vault.
- [OAuth acidentalmente faturável ou mutante] → limitar preflight ao endpoint OAuth/mTLS sem chamada de negócio e usar doubles apenas em local/testing.

## Migration Plan

1. Adicionar a política às aprovações, classificar histórico como `DUAL_ROLE` e expirar pendências globais incompatíveis.
2. Implementar confirmação do proprietário e tornar cutover/kill-off consumidores de aprovação singular vinculada.
3. Manter e testar política dual do canário antes de remover qualquer suposição global de dois aprovadores.
4. Atualizar UI, mensagens e testes; verificar com flags e kill switches desligados, sem chamada live.
5. Somente então aplicar a unicidade da change `tornar-platform-admin-proprietario-unico`.

Em rollback, manter kill switches ativos. Voltar ao código de dois `PLATFORM_ADMIN` depois da unicidade deixaria ações indisponíveis; portanto a estratégia preferida é correção para frente. Nenhuma migration de rollback deve transformar uma confirmação única já consumida em aprovação dual.

## Open Questions

Nenhuma. O canário permanece com Proprietário + `Office ADMIN`; somente ações globais da capability de credenciais adotam confirmação singular.
