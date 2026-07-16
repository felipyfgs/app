# Checklist de isolamento multi-tenant

**Change:** `build-complete-fiscal-monitoring-hub` · task 1.6  
**Atualizado:** 2026-07-15  
**Escopo:** inventário do código **atual** em `backend/` (pré-implementação completa do SaaS) + checklist de isolamento a aplicar em toda evolução multi-escritório.

> Leitura de código em 2026-07-15. Não altera comportamento; documenta riscos e padrões. Paths absolutos do monorepo: `/home/obsidian/dev/app/backend/...`.

## Modelo alvo (resumo)

- **Tenant** = `Office` com dados sob `office_id`.
- **Plano de controle global** = contrato SERPRO, platform memberships, catálogo/preços, fatura consolidada, flags (sem `office_id` de tenant) — ver `docs/adr/005-control-plane-vs-data-plane.md`.
- Mesmo CNPJ pode existir em **dois** escritórios; isolamento é por `office_id`, não por CNPJ global.

---

## 1. Inventário — padrões atuais

### 1.1 Resolução de escritório (request HTTP)

| Peça | Path | Comportamento atual | Risco multi-tenant |
|------|------|---------------------|--------------------|
| `CurrentOffice` | `app/Support/CurrentOffice.php` | Resolve via `User::activeMembership()`; `assertBelongsToOffice` aborta 404 se divergir | **Parcial:** sem troca explícita de tenant; primeira membership ativa (`orderBy id`) vence se houver várias |
| `User::activeMembership()` | `app/Models/User.php` | `where is_active` + office ativo, `first()` | Usuário multi-office não escolhe tenant; sessão “gruda” no menor `id` |
| `EnsureOfficeContext` | `app/Http/Middleware/EnsureOfficeContext.php` | Exige office; **remove** `office_id` de query/body/JSON | Bom: não confia no cliente |
| Rotas API | `routes/api.php` | Grupo autenticado + `EnsureOfficeContext` + 2FA admin | Sem área `PLATFORM_ADMIN`; tudo é tenant-scoped ou público pontual |
| `MeController` | `app/Http/Controllers/Api/V1/MeController.php` | Devolve um único `office` + `role` | Não lista memberships; UI não pode trocar tenant |
| `BootstrapOfficeCommand` | `app/Console/Commands/BootstrapOfficeCommand.php` | Falha se **já existir** qualquer office | Assumia bootstrap single-office; onboarding SaaS precisará de fluxo multi |

### 1.2 Persistência e scopes

| Peça | Path | Comportamento atual | Risco multi-tenant |
|------|------|---------------------|--------------------|
| Trait `BelongsToOffice` | `app/Models/Concerns/BelongsToOffice.php` | Global scope filtra por `CurrentOffice::id()`; no `creating`, preenche `office_id` se nulo | **Jobs/console sem CurrentOffice:** scope **não** filtra (`officeId === null` → sem where). Código de background **deve** filtrar `office_id` manualmente ou usar `withoutGlobalScopes` + assert |
| Models com trait | Vários (`Client`, cursores, outbound, autXML, …) | Padronizam tenant em HTTP | Consistência boa no request; frágil fora do request |
| Policies | `app/Policies/*` | `sameOffice` via `CurrentOffice` | Dependem do office resolvido da sessão |
| `withoutGlobalScopes()` | Ex.: `OutboundXmlRecoveryOrchestrator`, `OutboundDeadlineSatisfactionService` | Usado em jobs para carregar por id | **Obrigatório** revalidar `office_id` do grafo (profile/request/client) — já há padrões, mas regressão é fácil |

### 1.3 Papéis

| Peça | Path | Atual | Lacuna SaaS |
|------|------|-------|-------------|
| `OfficeRole` | `app/Enums/OfficeRole.php` | `ADMIN` / `OPERATOR` / `VIEWER` | Sem `PLATFORM_ADMIN` |
| Membership | `office_user` / `OfficeMembership` | Papel por office | Falta membership de plataforma e seleção de tenant |

### 1.4 APIs e superfícies de dados

| Superfície | Observação de isolamento |
|------------|---------------------------|
| `/api/v1/clients`, establishments, contacts, credentials | Tenant via scope + policies |
| `/api/v1/documents*`, notes, unlock, manifestations | Filtram por office do export/document |
| `/api/v1/exports` | ZIP em `storage/app/private/exports/{office_id}/` — path por office |
| `/api/v1/operations/*`, quarantine | Builders usam `where('office_id', $officeId)` explícito em vários pontos |
| `/api/v1/office/*` (fiscal-identity, autxml, integration-tokens) | Escopo do office da sessão |
| `/api/v1/outbound/*` | Profiles/seed/CSC por office |
| `/api/v1/integrations/cte/push` | Token de integração por office (`OfficeIntegrationToken`); **não** usa sessão | Validar que token não cruza office no push |
| CNPJ lookup | Cache por CNPJ normalizado (ver §1.6) — dado cadastral público, mas rate limit é global por processo |

### 1.5 Jobs e comandos de console

| Job / comando | Isolamento observado | Risco |
|---------------|----------------------|-------|
| `SyncEstablishmentDistributionJob` / ADN dispatch | Cursor/establishment com `office_id`; lock `adn:est:{establishment_id}` | Establishment id é globalmente único → lock ok; garantir que establishment.office_id não mude indevidamente |
| `SyncSefazDistDfeJob`, `SyncSefazCteDistDfeJob` | Locks por cursor/canal | Ok se payload carrega office e revalida |
| `SyncOfficeAutXmlDistDfeJob`, `SyncOfficeCteAutXmlDistDfeJob` | Office explícito no job | Gate `AutXmlFeature::isOfficeAllowed($officeId)` |
| `DispatchDueSyncsCommand` / due autXML / due sefaz / MA / SVRS | Iteram cursores/enrollments no banco | Devem processar **todos** os offices elegíveis com fairness; não assumir um único office |
| `BuildExportZipJob` | `where('office_id', $export->office_id)` + path por office | Bom padrão |
| `ProcessDocumentImportBatchJob` | Batch com office | Revalidar itens |
| `RecoverSvrsNfceXmlJob` / orchestrator | `withoutGlobalScopes` + office no request | Revisar em testes de dois offices |
| `PlanOutboundDeadlineScheduleJob` / planner | Snapshots por office | Métricas agregadas sem office podem misturar (§1.7) |
| `app:bootstrap-office` | Um office na instância | Substituir/complementar por onboarding multi |
| Backup `InstanceBackupService` | Backup de **instância** inteira (DB+vault) | Correto para DR; **não** é export por tenant — documentar custódia multi-tenant |

### 1.6 Feature flags e allowlists (já multi-conscious)

| Peça | Path | Comportamento |
|------|------|---------------|
| `AutXmlFeature` | `app/Support/AutXmlFeature.php` | `enabled` + kill switch + `office_allowlist`; allowlist vazia bloqueia salvo `allow_all_offices` |
| `CteAutXmlFeature` | `app/Support/CteAutXmlFeature.php` | Padrão análogo |
| Config SEFAZ autXML / MA / SVRS | `config/sefaz.php` | `office_allowlist`, pilot flags, kill switches | Bom modelo para Integra Contador (global + por tenant) |
| Kill switches runtime | `SvrsNfceKillSwitchService`, `SvrsNfe55KillSwitchService`, `OutboundKillSwitchService` | Chaves de cache **globais** da instância | Ativar kill switch de um canal afeta **todos** os offices — desejável para incidente global; falta kill switch **por office** onde o design exige |

### 1.7 Cache, locks, rate limits

| Área | Chaves / padrão | Tenant-safe? | Ação |
|------|-----------------|--------------|------|
| ADN establishment lock | `adn:est:{establishment_id}` | Sim (id único) | Manter; nunca lock só por CNPJ |
| MA outbound sequence lock | lock por perfil/série (config TTL) | Verificar se inclui office ou id de profile | Preferir ids internos, não CNPJ isolado |
| SVRS recovery lock | `svrs_nfce.recovery.{requestId}` | Sim se requestId único | Manter |
| SVRS portal egress governor | Redis/cache: inflight global, last por root, mutex | **Global do canal** (proteção SEFAZ) + root CNPJ | Root sem office pode colidir conceitualmente entre tenants com mesmo CNPJ: o rate limit **deve** ser global por root na SEFAZ, mas **estado de negócio** (requests) continua por office |
| Circuit breaker SVRS | chaves por canal (ex. `sefaz.svrs_nfce_xml.breaker.*`) | Global do canal | Ok para proteção de infraestrutura |
| CNPJ.ws lookup | cache prefix + RateLimiter | Global por CNPJ | Aceitável (dado público); não cachear dados fiscais de tenant sob chave só-CNPJ |
| Login / 2FA | RateLimiter Fortify | Por login/IP | Ok |
| `OutboundMetrics` counters | `metrics.counter.{name}` **sem** `office_id` | **Agrega todos os tenants** | Para SaaS: contadores de produto por tenant devem incluir `office_id` **só** se baixa cardinalidade e política permitir; preferir agregados em DB com office_id. Nunca CNPJ/chave como label |
| Backup lock | `ops.backup-run` | Instância | Ok |

### 1.8 Storage e cofre

| Recurso | Path / padrão | Isolamento |
|---------|---------------|------------|
| Exports ZIP | `storage/app/private/exports/{office_id}/` | Por office no filesystem |
| Manifests mensais outbound | `.../exports/{office_id}/manifests` | Por office |
| Import spools | vault object ids em tabelas com `office_id` | Metadata no DB; objeto no cofre por ULID |
| `FilesystemSecureObjectStore` | root único + id ULID | **Não** particiona path por office; isolamento é **autorização de quem conhece o id** + metadata de propósito. Vazamento de `vault_object_id` entre tenants = risco crítico |
| Credenciais | `ClientCredential`, `OfficeCredential` com `vault_object_id` Hidden | API não devolve id de vault |

### 1.9 Métricas, logs e auditoria

| Peça | Padrão atual | Risco |
|------|--------------|-------|
| `OutboundMetrics` | Labels allowlist baixa cardinalidade; sem access_key/CNPJ | Contador cache global mistura tenants |
| `AuditLogger` | Registra `office_id` em vários fluxos | Manter office em todo evento tenant; platform admin com office null só em ações globais |
| Logs de jobs | Variam | Proibir PFX/token/XML; evitar logar CNPJ completo como label de métrica |

### 1.10 Premissas “single-office” remanescentes

1. `activeMembership()` = primeira membership (sem switch).
2. `app:bootstrap-office` recusa segundo office.
3. Kill switches de canal só no nível instância.
4. Métricas cache sem dimensão de tenant.
5. Cofre sem prefixo de office (mitigado por opacidade do id + policies).
6. Ausência de `PLATFORM_ADMIN` e de APIs de plano de controle.
7. Docs/AGENTS legados (atualizados na task 1.1) citavam multi-office como non-goal.

---

## 2. Checklist de isolamento (usar em PRs e na change)

Marcar por entrega relevante. Itens **MUST** bloqueiam merge de feature multi-tenant.

### 2.1 HTTP / API

- [ ] **MUST** Nenhuma action confia em `office_id` do request; middleware continua stripando.
- [ ] **MUST** Listagens e `find` de recursos tenant passam por scope, policy ou `where office_id = current`.
- [ ] **MUST** IDOR: usuário do office A recebe 404 (não 403 detalhado) ao acessar id do office B.
- [ ] **MUST** Endpoints de plataforma (`PLATFORM_ADMIN`) ficam em rotas/guards separados e **não** reutilizam `BelongsToOffice` com office nulo para “ver tudo”.
- [ ] **SHOULD** `/me` (ou equivalente) lista memberships e office ativo; troca de tenant é endpoint dedicado + audit.

### 2.2 Domínio / DB

- [ ] **MUST** Toda tabela de negócio de tenant nova tem `office_id` NOT NULL + FK + índices compostos começando por `office_id` quando houver busca.
- [ ] **MUST** Unique constraints de negócio são **por office** (ex.: CNPJ único em `(office_id, cnpj)`), nunca globais por CNPJ fiscal se o SaaS permitir o mesmo contribuinte em dois escritórios.
- [ ] **MUST** Tabelas globais **não** têm `office_id` opcional; escopo pelo tipo (ADR 005).
- [ ] **MUST** Testes com dois offices e o **mesmo** CNPJ: zero cruzamento em API, job e export.

### 2.3 Jobs / filas / scheduler

- [ ] **MUST** Payload do job inclui `office_id` (ou id de agregado que carrega office) e revalida antes de I/O externo.
- [ ] **MUST** Após `withoutGlobalScopes()`, todo write/read de negócio confere `office_id` esperado.
- [ ] **MUST** Locks: chave inclui id de recurso único ou `office_id`+recurso; **proibido** lock global só por CNPJ para mutação de dados de tenant.
- [ ] **SHOULD** Dispatch “due” percorre offices com espalhamento justo (não um office monopoliza a fila).
- [ ] **MUST** Tenant `SUSPENDED` não enfileira novas chamadas externas/mutações (quando lifecycle existir).

### 2.4 Cache / rate limit

- [ ] **MUST** Cache de **dados fiscais ou de tenant** inclui `office_id` na chave.
- [ ] **MAY** Cache de dado público (CNPJ.ws) e rate limit de **proteção de terceiro** (SEFAZ root) serem globais — documentar a decisão no PR.
- [ ] **MUST** Tokens Bearer SERPRO / JWT: cache cifrado no plano de controle, **nunca** chaveada por office como se fosse credencial do tenant.
- [ ] **SHOULD** Kill switch: par global (incidente) **e** par por `office_id` (abuso/suspensão).

### 2.5 Storage / exports / cofre

- [ ] **MUST** Paths de export/import temporário incluem `office_id` (já padrão em exports).
- [ ] **MUST** Download de export valida office da sessão == `export.office_id`.
- [ ] **MUST** Leitura de vault para XML/export usa metadata de office (AAD) quando o desenho do cofre exigir; nunca servir `vault_object_id` cruzado.
- [ ] **MUST** Purge de exports/spools filtra por office ou por id de export já isolado.

### 2.6 Métricas / logs / auditoria

- [ ] **MUST** Sem PFX, senha, PEM, Consumer Secret, token, Termo XML, payload fiscal em log/métrica.
- [ ] **MUST** Eventos de auditoria de ação de tenant incluem `office_id`.
- [ ] **SHOULD** Contadores operacionais por tenant usam agregação em DB ou labels de baixa cardinalidade **com** office só se política de privacidade permitir.
- [ ] **MUST** `PLATFORM_ADMIN` não gera trilha de “leitura fiscal” sem fluxo break-glass futuro.

### 2.7 Integra Contador / SERPRO (novo)

- [ ] **MUST** Credenciais do contrato só no plano de controle.
- [ ] **MUST** Toda chamada registra/reserva consumo com `office_id` do tenant autor da operação.
- [ ] **MUST** Corpo da chamada montado de registros persistidos (contratante global + autor/contribuinte do office), não de input livre do browser.
- [ ] **MUST** Elegibilidade falha fechado se office, termo, procuração ou plano inválidos.

### 2.8 Frontend (quando tocado)

- [ ] **MUST** Shell mostra office ativo; requests não enviam `office_id` autoritativo.
- [ ] **MUST** Troca de tenant limpa estado client (queries, stores) do office anterior.
- [ ] **MUST** Sem telas de contrato SERPRO bruto para o tenant.

---

## 3. Testes negativos mínimos (matriz)

| # | Cenário | Resultado esperado |
|---|---------|-------------------|
| T1 | User A (office 1) GET recurso id de office 2 | 404 |
| T2 | Dois offices, mesmo CNPJ cliente; listagem A não vê B | Isolado |
| T3 | Job enfileirado com office 1 não grava projeção em office 2 | Isolado |
| T4 | Export office 1 não inclui XML office 2 | Isolado |
| T5 | Token integração office 1 não autentica push como office 2 | 401/403 |
| T6 | `PLATFORM_ADMIN` sem membership não lista documentos | 403 |
| T7 | Request com body `office_id: 2` sendo user de office 1 | Ignorado; dados de office 1 |
| T8 | Cache key fiscal sem office não é usada para servir office B | N/A ou miss |

---

## 4. Prioridade de remediação (legado → SaaS)

| Prioridade | Item | Task relacionada (change) |
|------------|------|---------------------------|
| P0 | Switch de tenant + rejeição de office livre (já strip) | 2.5, 2.6 |
| P0 | `PLATFORM_ADMIN` sem herança fiscal | 2.4 |
| P0 | Testes dois offices / mesmo CNPJ | 2.10 |
| P1 | Kill switch e quotas por office | 1.7, 2.7, 6.x |
| P1 | Métricas/ledger por office | 6.x |
| P2 | Bootstrap/onboarding multi-office | 2.x |
| P2 | Particionamento opcional de paths de vault por office (defesa em profundidade) | 3.x |

---

## 5. Relacionados

- ADR 005: `docs/adr/005-control-plane-vs-data-plane.md`
- Gate SERPRO comercial: `docs/ops/serpro-integra-contador-commercial-legal-evidence.md`
- Design: `openspec/changes/build-complete-fiscal-monitoring-hub/design.md`
