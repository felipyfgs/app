## Context

O domínio atual possui três acoplamentos que precisam ser desfeitos em conjunto:

1. o escritório configura manualmente detalhes internos da autorização SERPRO em `/settings`, apesar de o contrato, o OAuth mTLS e a operação do gateway pertencerem à plataforma;
2. o mesmo e-CNPJ A1 pode existir como `OfficeCredential` para autXML e como PFX do autor em `OfficeSerproAuthorization`, com ciclos de vida independentes;
3. `CurrentOffice`, policies e troca de tenant aceitam apenas memberships, enquanto o modelo operacional decidido requer acesso integral de todo `PLATFORM_ADMIN` a qualquer escritório.

Além disso, `OfficeSubscription.monthly_api_quota` e o `UsageBudgetGate` medem chamadas técnicas potencialmente faturáveis, não a unidade comercial de “uma consulta de um monitor para um cliente”. `FiscalMonitoringSchedule` também é hoje orientado a intervalo por cliente/serviço, e não a uma execução mensal por monitor para a carteira inteira.

A mudança atravessa domínio, APIs Sanctum, jobs Horizon, dados, segurança e Nuxt SPA. O tenant continua sendo `Office`, nenhum endpoint aceitará `office_id` do cliente HTTP e segredos permanecem exclusivamente no `SecureObjectStore`. O rollout real continua subordinado às feature flags e kill switches existentes.

Stakeholders: administradores e operadores dos escritórios, administradores e suporte da plataforma, segurança/compliance, financeiro do produto e operação SERPRO.

## Goals / Non-Goals

**Goals:**

- oferecer ao escritório uma única configuração institucional com perfil, consentimento e A1 canônico;
- tornar a autorização SERPRO uma automação interna derivada, sem campos técnicos tenant-facing;
- compartilhar a mesma credencial física entre finalidades autorizadas sem duplicar o segredo;
- implementar contexto privilegiado explícito para `PLATFORM_ADMIN`, com auditoria interna e desafio de senha em ações sensíveis;
- sincronizar procurações oficiais e bloquear somente operações que dependem do poder ausente;
- separar franquia comercial de monitor do ledger/orçamento técnico do gateway;
- executar uma consulta automática mensal por cliente e monitor, com distribuição de carga e consumo idempotente;
- migrar dados existentes sem expor ou baixar certificados e com rollback controlado.

**Non-Goals:**

- e-CPF, A3, assinatura externa, criação/importação manual de procuração ou override de poder;
- checkout, créditos adicionais, excedente, rollover ou alteração do nome do plano por limite negociado;
- mudar o comportamento de NFS-e, SEFAZ ou autXML em tempo real;
- habilitar capacidades SERPRO reais/mutantes hoje desligadas;
- permitir download, recuperação ou exibição de PFX, senha, Termo, token ou payload fiscal;
- enviar alertas por e-mail, WhatsApp ou SMS;
- substituir o ledger técnico ou o `UsageBudgetGate` pela franquia comercial.

## Decisions

### 1. Separar superfícies de plataforma e escritório

`/settings` será a única superfície de configuração do `Office`; `/admin/*` será reservada ao `PLATFORM_ADMIN`. A API seguirá a mesma divisão: perfil, consentimento e gestão do A1 permanecem tenant-scoped; contrato SERPRO, credenciais globais, orçamento, saúde técnica e diagnóstico ficam em `/api/v1/platform/*`; comandos de autorização técnica tornam-se serviços/jobs internos e deixam de aceitar parâmetros técnicos do frontend.

O frontend tenant-facing receberá apenas estados acionáveis: completar perfil, consentir, enviar/substituir A1 ou regularizar procuração no e-CAC. Erros de OAuth, mTLS, Termo, ETag, catálogo ou transporte serão sanitizados para o escritório e detalhados somente na área da plataforma.

Alternativa considerada: manter um modo avançado para o contador preencher o Termo. Rejeitada porque conserva a responsabilidade errada, aumenta exposição de segredo e cria estados impossíveis de suportar.

### 2. Modelar uma credencial A1 canônica com vínculos de finalidade

O agregado do escritório terá no máximo uma credencial canônica ativa do tipo e-CNPJ A1. O PFX/P12 e sua senha serão validados em memória e armazenados cifrados no vault; tabelas relacionais guardarão somente identificador do objeto, status e metadados seguros como subject, CNPJ titular, validade e fingerprint.

Uma coleção de vínculos de finalidade (`SERPRO_TERM_SIGNING`, `NFE_AUTXML_DISTDFE` e futuras finalidades aprovadas) referenciará a credencial canônica em vez de copiar o segredo. Cada uso registrará finalidade e ator/job na auditoria. A senha nunca será um campo recuperável da API.

Substituição seguirá validate-before-cutover: o novo arquivo precisa ser A1, estar válido, abrir com a senha e ter CNPJ exatamente compatível com o perfil. Só então uma transação troca a referência ativa, aposenta a anterior e publica eventos de reonboarding. Falha de validação preserva a credencial anterior. Remoção confirmada revoga vínculos imediatamente, invalida as autorizações derivadas e preserva apenas metadados/auditoria.

Alternativa considerada: manter um certificado por integração. Rejeitada por duplicar material criptográfico, senha, alertas e lógica de rotação.

### 3. Tratar perfil, consentimento e autorização como estados derivados

O perfil institucional contém exatamente CNPJ, razão social, e-mail institucional e telefone institucional. Qualquer membro `OfficeRole::ADMIN`, ou `PLATFORM_ADMIN` em contexto privilegiado, pode alterá-lo. Mudança de CNPJ exige confirmação forte e invalida A1 incompatível, Termo, tokens e integrações derivadas no mesmo fluxo transacional/outbox; o novo onboarding só começa após um A1 com titularidade exata.

O consentimento técnico registra versão, finalidades apresentadas, ator e instante. Novo uso material cria nova versão e exige reconcordância, mas não novo upload se a credencial continuar compatível. Consentimento não substitui os contratos comerciais entre plataforma–SERPRO ou escritório–plataforma.

Um state machine tenant-scoped (`incomplete`, `ready`, `provisioning`, `authorized`, `action_required`, `technical_error`, `revoked`) deriva sua entrada de perfil + consentimento + A1. Jobs Horizon geram e assinam o Termo, chamam `Apoiar`, armazenam token/ETag de forma segura, sincronizam poderes e renovam o necessário. Locks por office, idempotency keys e outbox evitam duplicidade. Controllers tenant-scoped apenas comandam o domínio e não importam credenciais SERPRO globais.

Alternativa considerada: armazenar Termo enviado pelo usuário. Rejeitada porque permite divergência do perfil/A1 e expõe detalhes técnicos ao tenant.

### 4. Introduzir contexto privilegiado sem membership fictícia

O seletor global lista todos os escritórios somente para `PLATFORM_ADMIN`. A seleção grava uma chave de sessão separada da seleção comum, por exemplo `platform_selected_office_id`; não cria `OfficeMembership` nem altera `users.selected_office_id`. `CurrentOffice` resolve primeiro o modo privilegiado para o papel global e produz um contexto que contém `office`, `actor` e `access_mode=platform_privileged`.

Policies passam a autorizar um `PLATFORM_ADMIN` nesse contexto com capacidades efetivas de `OfficeRole::ADMIN`, inclusive leitura fiscal, configuração do A1 e mutações fiscais. Escopo de queries, jobs, locks e eventos continua usando o `office_id` resolvido no servidor. O cliente HTTP nunca escolhe escopo por body/query.

O login e a navegação comuns do administrador da plataforma não exigirão TOTP. Substituição/remoção do A1 e operações fiscais mutantes exigirão reconfirmação recente da senha do ator real. Os demais gates existentes — assinatura writable, flags, allowlist, elegibilidade, orçamento, contrato, idempotência e kill switch — continuam fail-closed.

Cada leitura ou mutação relevante feita em modo privilegiado gera auditoria interna append-only com ator real, office, ação, alvo, resultado, request/tag e metadados sanitizados. A trilha não é exposta em APIs ou telas do escritório. A implantação depende de revisão jurídica de LGPD e sigilo fiscal.

Alternativas consideradas: membership automática, impersonação de usuário do escritório e sessão temporária com justificativa. Foram rejeitadas porque falsificam autoria ou contrariam a decisão de acesso permanente e direto; a auditoria explícita preserva a identidade real sem alterar o modelo de memberships.

### 5. Sincronizar procurações como evidência oficial

Um job periódico e acionável pela plataforma consulta a operação oficial do catálogo, normaliza poderes por cliente e mantém status `authorized`, `missing`, `expired` ou `unverified`, validade, última verificação e evidência segura. A coluna “Procuração” apenas traduz esses estados.

Antes de cada operação, o gateway consulta o metadado oficial da `operation_key`. Se poder/procuração não se aplicar, a execução segue. Se for obrigatório e não estiver autorizado, somente aquela operação falha antes do transporte. Não haverá entrada nem override manual.

Alternativa considerada: um bloqueio global do cliente sem procuração. Rejeitada porque o requisito de poder varia por operação e impediria serviços oficiais que não dependem dela.

### 6. Separar unidade comercial de consumo técnico

A unidade comercial será um despacho lógico de `client_id + monitor_key` dentro do período da assinatura. Ela terá origem `inaugural`, `manual` ou `scheduled`, estado de despacho e `quota_units` 0 ou 1. O ledger técnico existente continuará registrando cada chamada HTTP/operação e aplicando orçamento/bilhetagem, podendo haver várias chamadas técnicas para uma única consulta comercial.

O período comercial usa `current_period_starts_at` e `current_period_ends_at` da assinatura. Os entitlements são:

| Plano | Unidades por cliente + monitor + período | Máximo de clientes |
|---|---:|---:|
| Starter | 5 | 100 |
| Professional | 7 | 150 |
| Enterprise | 10 | 200 |

Um limite de clientes acima de 200 pode ser negociado e definido por `PLATFORM_ADMIN`, sem trocar o plano. Não existe override da franquia de consultas. Uma chave única por office, cliente, monitor, período e idempotency key impedirá consumo repetido. A unidade só é debitada imediatamente antes do primeiro despacho remoto real; validação, item enfileirado, bloqueio por procuração/flag e falha antes do transporte não consomem. Tentativas técnicas potencialmente faturáveis continuam no ledger técnico.

Cada combinação cliente + monitor recebe uma execução inaugural única após sua ativação, com `quota_units=0`; nova assinatura ou novo período não recria esse benefício. Manual e scheduled compartilham o mesmo saldo. Ao esgotar, o servidor bloqueia novos despachos até o próximo período; saldo expira sem rollover.

Alternativas consideradas: reutilizar `monthly_api_quota` e vender créditos adicionais. Rejeitadas porque uma consulta lógica pode gerar múltiplas chamadas SERPRO e porque o produto definido não terá excedente/top-up.

### 7. Agendar por monitor e distribuir a carteira deterministically

Cada office + monitor terá uma política com dia 1–28 e timezone do escritório. Na ausência de configuração, o dia será derivado por hash estável de `office_uuid + monitor_key`, distribuído entre 1 e 28. O administrador do escritório pode alterar o dia, que vale para toda a carteira daquele monitor no período seguinte ou para itens ainda não despachados do período atual, sem criar uma segunda execução.

No dia devido, o scheduler cria itens idempotentes para os clientes elegíveis e Horizon os distribui ao longo do dia respeitando limites oficiais, concorrência, flags e orçamento. Se a fila não terminar, itens seguem nos dias posteriores até conclusão. A unidade é consumida somente no despacho real. Se o saldo foi esgotado por consultas manuais, o item automático é marcado como bloqueado por franquia e não chama o SERPRO naquele período.

Cada cliente + monitor terá no máximo uma execução scheduled por período. A API de consulta manual retorna último snapshot/horário e indicador de recência; a UI exige confirmação informada, mas o backend continua impedindo execução abaixo do intervalo mínimo oficial/do módulo.

Alternativas consideradas: intervalo por cliente e horário escolhido pelo usuário. Rejeitadas por ampliar complexidade operacional e permitir picos; o dia por portfólio é simples e a plataforma controla a dispersão horária.

### 8. Expor estados seguros no painel

A página única `/settings` será construída com `panel-ui` → `ui-archetype` e seções de perfil, consentimento, certificado e agenda dos monitores. O certificado usa o padrão visual já existente de status, titular, CNPJ, validade, fingerprint, alertas, upload/substituição/remoção, sem ação de download. Alertas de 30, 7 e 1 dia são persistidos/exibidos apenas no painel e deduplicados por janela.

Listas de clientes exibem a procuração e os monitores mostram saldo do período, próxima execução, último snapshot e motivo de bloqueio. Erros técnicos internos são substituídos para o tenant por estados acionáveis e correlation id; detalhes ficam no painel da plataforma.

Alternativa considerada: páginas separadas por integração. Rejeitada porque recriaria configurações duplicadas e dificultaria compreender qual A1 está ativo.

## Risks / Trade-offs

- [Acesso fiscal permanente por `PLATFORM_ADMIN` amplia materialmente o impacto de uma conta comprometida] → revisão jurídica e de threat model antes do rollout, senha forte, expiração de sessão, reconfirmação nas ações sensíveis, auditoria append-only, alertas internos e rollout gradual.
- [Remover TOTP reduz uma camada de proteção atual] → limitar a remoção ao requisito explícito de navegação do papel global, manter controles de sessão/Fortify e registrar como decisão breaking; reavaliar autenticação adicional em change futura sem bloquear esta implementação.
- [Resolver o tenant por dois modos pode causar vazamento entre offices] → objeto de contexto com `access_mode`, sessão separada, escopo obrigatório em models/repos/jobs e testes de arquitetura/policies para membership, ausência de membership e troca de contexto.
- [Migração de PFX duplicados pode encontrar certificados diferentes] → nunca escolher silenciosamente; validar fingerprint/titularidade, preferir a credencial explicitamente ativa por finalidade e colocar conflito em estado de reconciliação da plataforma antes de apagar referências.
- [Mudança de CNPJ ou remoção do A1 interrompe integrações] → confirmação forte, prévia de impacto, invalidação atômica, outbox e estados acionáveis; não preservar autorização criptograficamente incompatível.
- [Fila mensal pode gerar pico ou atravessar vários dias] → hash determinístico, rate limits, Horizon, locks, backpressure, continuidade idempotente e métricas de backlog por office/monitor.
- [Contagem comercial divergir do faturamento SERPRO] → ledgers distintos e correlacionados por id; dashboards conciliam consulta comercial com chamadas técnicas sem inferir equivalência 1:1.
- [Procuração desatualizada bloquear ou liberar indevidamente] → expiração explícita, estado `unverified` fail-closed apenas quando a operação exige poder, timestamp visível e sincronização/retry controlados.
- [Change ativa anterior contém regra oposta para `PLATFORM_ADMIN`] → declarar precedência desta change, atualizar delta specs antes do go-live e impedir aplicação das tarefas conflitantes da change anterior.

## Migration Plan

1. Obter aprovação jurídica/segurança para o modelo privilegiado e registrar flags de rollout default OFF para contexto privilegiado, perfil unificado, autorização automática e franquia/scheduler.
2. Criar estruturas aditivas: perfil institucional, credencial canônica/vínculos, consentimentos, estado de onboarding, sessão/auditoria privilegiada, procurações, entitlements, ledger comercial e políticas mensais.
3. Fazer backfill de perfil a partir de `Office`/`OfficeFiscalIdentity`, sem confiar em dados enviados pelo cliente durante a migração.
4. Inventariar PFX existentes pelo identificador/fingerprint seguro. Unificar somente correspondências inequívocas; conflitos ficam bloqueados para reconciliação da plataforma. Não exportar nem logar material do vault.
5. Adaptar consumidores de autXML e assinatura SERPRO para vínculos da credencial canônica, mantendo leitura compatível temporária enquanto o backfill é verificado.
6. Migrar planos para 5/7/10 unidades por cliente+monitor e limites 100/150/200, mantendo `monthly_api_quota`/`UsageBudgetGate` como controles técnicos separados. Criar políticas mensais e inaugural sem debitar históricos retroativamente.
7. Implantar APIs/jobs e testar isolamento, segredo, idempotência, renovação, spillover, procuração, franquia e reconfirmação; chamadas reais permanecem desativadas em CI.
8. Trocar a UI para `/settings`, retirar campos técnicos tenant-facing e reservar `/admin/*` à plataforma. Habilitar por coortes após reconciliação de dados.
9. Ativar primeiro leitura/estados, depois onboarding automático, depois scheduler comercial e, por último, contexto privilegiado, sempre com métricas e kill switch.
10. Após período de estabilidade, remover endpoints/colunas duplicados e a compatibilidade de leitura antiga.

Rollback: desativar as flags novas para interromper seleção privilegiada, automação e novos despachos; preservar ledgers/auditoria e reativar temporariamente a leitura compatível das referências antigas. Não reverter migração destrutiva nem restaurar certificados aposentados automaticamente. Qualquer autorização criada permanece revogada/segura até reconciliação explícita.

## Open Questions

Não há decisão funcional bloqueante para implementação. Textos jurídicos do consentimento, prazo de retenção da auditoria privilegiada e política interna de alerta de acesso serão definidos com jurídico/segurança antes do rollout, sem alterar os comportamentos normativos desta change.
