## ADDED Requirements

### Requirement: Office home cockpit layout

O painel Início em `/` SHALL apresentar um cockpit operacional do escritório ativo com seções distintas e rotuladas em pt_BR: Bloqueios/saúde, Operações (sync/certificados/SVRS/backup), Trabalho, Fiscal (resumo), SERPRO do escritório, Atendimento (quando a capability de comunicação estiver habilitada para o office ou quando houver dados/flags a exibir) e Atenção (inbox). Cada seção acionável SHALL oferecer deep-link para a rota canônica correspondente (`/health`, `/syncs`, `/work`, `/monitoring`, `/communication`, `/conta/consumo`, `/clients`, `/exports`) sem redesenhar o shell do dashboard.

#### Scenario: Cockpit loads for office user

- **WHEN** um usuário autenticado com office válido abre `/`
- **THEN** a UI renderiza as seções do cockpit a partir de dados reais do office
- **AND** exibe timestamp de atualização e ação de refresh
- **AND** NÃO duplica charts densos do dashboard fiscal `/monitoring`

#### Scenario: Refresh failure preserves last valid snapshot

- **WHEN** existe um snapshot válido do summary e uma atualização posterior falha
- **THEN** a UI mantém os últimos valores confirmados visíveis
- **AND** sinaliza a falha de atualização sem substituir valores por zero inventado

### Requirement: Operations summary typed contract

O sistema SHALL expor `GET /api/v1/operations/summary` autenticado no contexto do office da sessão com payload tipado que INCLUI, além dos contadores operacionais legados (clientes, estabelecimentos, notas, exports, sync, certificados, inbox counts, backup, `svrs_nfce`, `generated_at`): `platform_health` (sanitizado), `blocks`, `serpro_authorization`, `proxy_powers`, `modules` (flags/kill switch do hub), `fiscal_pending`, `fiscal_coverage`, `usage`, `subscription`, `uncertain_results` e `guides_due_7d`. O endpoint SHALL NÃO disparar consultas SERPRO/SEFAZ live nem incluir segredos (PFX, tokens, fingerprint, custo global, dados de outros offices).

#### Scenario: Summary returns office-scoped aggregate

- **WHEN** um usuário autenticado com office válido solicita `GET /api/v1/operations/summary`
- **THEN** a API retorna HTTP 200 com as chaves tipadas acima preenchidas a partir de read models locais
- **AND** `platform_health` omite campos proibidos (contrato global, fingerprint, PFX, OAuth secrets)

#### Scenario: Tenant isolation

- **WHEN** o office A solicita o summary
- **THEN** a resposta NÃO inclui contadores, bloqueios, autorização, procurações, pendências ou uso do office B

### Requirement: Communication rollup in operations summary

O summary SHALL incluir uma seção `communication` com: flags (`global_enabled`, `gateway_enabled`, `office_enabled`); contagem de inboxes por status do domínio; contagens de outbox `RETRY` e `DEAD`; contagens de conversas `OPEN` e `PENDING`. Quando a leitura falhar, a seção SHALL reportar indisponibilidade honesta (`available: false` ou equivalente) e NÃO inventar zeros como “tudo bem”. Contagens SHALL ser escopadas ao `office_id` da sessão.

#### Scenario: Communication rollup for office with inboxes

- **WHEN** o office possui inboxes e outbox/conversas persistidos
- **THEN** `communication` reflete contagens reais do office
- **AND** deep-link canônico da UI aponta para `/communication`

#### Scenario: Communication section fail-closed

- **WHEN** a agregação de comunicação falha
- **THEN** o summary permanece HTTP 200 para as demais seções
- **AND** `communication` indica indisponibilidade sem mascarar como zero saudável

### Requirement: Optional light fiscal automation counters

O summary MAY incluir seções leves `mei_automation` e/ou `fiscal_runs` com contagens 24h (failed/uncertain/running) escopadas ao office. Se incluídas e a leitura falhar, SHALL usar `available: false` (ou omitir com honestidade) sem inventar KPIs. Se omitidas na implementação, a UI NÃO inventa esses blocos.

#### Scenario: Light counters when available

- **WHEN** as contagens leves estão implementadas e os dados locais existem
- **THEN** o summary inclui as contagens 24h do office
- **AND** a UI do Início deep-linka para `/monitoring/mei` ou `/monitoring` conforme o domínio

### Requirement: Honest KPI rendering on home

Widgets do Início SHALL mapear apenas dados presentes no summary/work KPIs. Valor zero SHALL ser exibido somente após resposta válida. Loading, ausência de cobertura e falha SHALL usar estados visuais distintos. Bloqueios (`blocks.blocked` / `blocks.reasons`) e `platform_health` degradado SHALL ser destacados acima da dobra quando aplicável.

#### Scenario: Zero only after valid payload

- **WHEN** o summary ainda não carregou
- **THEN** a UI NÃO mostra zeros como se fossem métricas confirmadas

#### Scenario: Blocks banner when office blocked

- **WHEN** `blocks.blocked` é true ou há reasons críticos (kill switch, circuit open, auth incompleta/bloqueada)
- **THEN** o Início exibe banner/alerta acionável com deep-link para a ação seguinte (`next_action` ou `/health` / conta SERPRO do office)
