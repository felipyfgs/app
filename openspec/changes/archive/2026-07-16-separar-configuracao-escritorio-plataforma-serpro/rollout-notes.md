# Rollout notes — separar-configuracao-escritorio-plataforma-serpro

Notas operacionais mínimas. **Não** habilita produção sozinho; gates e aceite de software (7.1/7.2) vêm antes. Sem segredos, CNPJs ou identidades de canário.

## 1. Flags default OFF

Todas as chaves novas desta change nascem **OFF**. Kill switch global (`FEATURES_KILL_SWITCH`) e kill SERPRO (`SERPRO_KILL_SWITCH`) vencem qualquer enable. Mutações e módulos SERPRO legados permanecem nos defaults fail-closed existentes.

| Capacidade | Env / config | Default | Notas |
|---|---|---|---|
| Contexto privilegiado `PLATFORM_ADMIN` | `PLATFORM_PRIVILEGED_CONTEXT` → `features.platform_privileged_context.enabled` | `false` | **Gate jurídico/segurança obrigatório** antes de ON em prod |
| Config unificada (perfil + A1 canônico + consentimento) | `FEATURE_UNIFIED_OFFICE_CONFIG_ENABLED` (+ allowlist / allow_all) | `false` / allowlist vazia | Rollout por coorte: IDs em `FEATURE_UNIFIED_OFFICE_CONFIG_OFFICE_ALLOWLIST` ou `ALLOW_ALL_OFFICES` |
| Franquia comercial monitores | `FISCAL_MONITORING_COMMERCIAL_ENABLED` → `fiscal_monitoring.commercial.enabled` | `false` | Sem débito comercial até ON |
| Scheduler mensal office+monitor | `FISCAL_MONITORING_COMMERCIAL_MONTHLY_ENABLED` → `fiscal_monitoring.scheduler.commercial_monthly_enabled` | `false` | Depende do comercial e do scheduler base |
| Bloqueio por ledger comercial | `SERPRO_USAGE_COMMERCIAL_BLOCKING` | `false` | Separado do orçamento técnico `UsageBudgetGate` |
| Drivers / capabilities SERPRO | `SERPRO_USE_FAKE_CLIENTS`, `SERPRO_CAPABILITY_*`, módulos `FEATURE_*` | fake/simulated/off | Automação de Termo/Apoiar **não** implica live HTTP |

Ordem de promoção sugerida (design § Migration Plan):

1. Deploy com flags OFF; migrations aditivas e backfill seguro.
2. `FEATURE_UNIFIED_OFFICE_CONFIG_*` em allowlist (leitura/estados em `/settings`).
3. Onboarding automático (jobs) só com perfil+consentimento+A1 e drivers ainda simulados/gated.
4. `FISCAL_MONITORING_COMMERCIAL_ENABLED` → depois `…_COMMERCIAL_MONTHLY_ENABLED`.
5. Por último: `PLATFORM_PRIVILEGED_CONTEXT=true` **somente** com gate §5 aceito.

## 2. Métricas (sem PII)

Labels de baixa cardinalidade (status, monitor_key, access_mode, resultado). **Proibido:** CNPJ, e-mail, fingerprint completo, correlation com payload fiscal, PFX/token.

| Sinal | Uso no rollout |
|---|---|
| Onboarding state machine (`incomplete`…`revoked`) por contagem de office | Health da automação; `technical_error` vs `action_required` |
| Jobs Termo/Apoiar: enfileirados, sucesso, falha sanitizada, retries | Detectar regressão de automação sem detalhe OAuth ao tenant |
| Ledger comercial: unidades debitadas / bloqueadas (`COMMERCIAL_QUOTA_EXHAUSTED`, min-interval) | Correlacionar com ledger técnico (N:1), sem equivalência 1:1 |
| Scheduler mensal: itens criados, spillover, backlog por monitor | Picos e fila multi-dia |
| Contexto privilegiado: contagem de selects e eventos em `platform_privileged_audit_events` (metadados sanitizados) | Adoção e anomalias de suporte |
| A1: alertas 30/7/1 no painel; uploads rejeitados (motivo genérico) | Operação de certificado |
| Kill / flags: snapshot config (booleans) em ops platform | Confirmar teto de promoção |

Dashboards existentes SERPRO (OAuth/breaker/filas/reconciliação de fatura) continuam; esta change **não** substitui `serpro-operacao-observavel`.

## 3. Reconciliação de A1 divergente

Problema: o mesmo office pode ter PFX distinto em `office_credentials` (ex. autXML) e em `OfficeSerproAuthorization` (autor Termo), com fingerprints diferentes.

Regras (design § Migration + risk):

1. Inventariar por `office_id` + `fingerprint_sha256` (metadado) — **nunca** exportar/logar bytes do vault.
2. Unificar **somente** correspondências inequívocas (mesmo fingerprint + titularidade compatível com perfil).
3. Preferir credencial explicitamente ativa por finalidade; criar vínculos `SERPRO_TERM_SIGNING` / `NFE_AUTXML_DISTDFE` apontando para a canônica `CANONICAL_ECNPJ_A1`.
4. **Conflito (fingerprints diferentes ou titularidade divergente):** não escolher silenciosamente; office fica em estado de reconciliação da plataforma; onboarding/finalidades bloqueados até cutover manual validate-before-cutover ou remoção confirmada.
5. Fallback de leitura legado (`OfficeCredentialResolver`) permanece até coorte estável; só então desligar compat.
6. Substituição em runtime: validate-before-cutover; falha preserva A1 anterior.

Checklist ops pré-enable de coorte:

- [ ] Contagem de offices com 0 / 1 / N fingerprints ativos
- [ ] Lista de conflitos (só office_id + fingerprints curtos) revisada pela plataforma
- [ ] Nenhum download de PFX; vault intacto
- [ ] Após unificação, smoke de resolução por finalidade (sem HTTP real se drivers OFF)

## 4. Rollback

| Alavanca | Efeito | Não faz |
|---|---|---|
| `PLATFORM_PRIVILEGED_CONTEXT=false` | Interrompe seletor/contexto privilegiado | Apagar auditoria privilegiada |
| `FEATURE_UNIFIED_OFFICE_CONFIG_ENABLED=false` (ou esvaziar allowlist) | UI/API unificada deixa de ser a superfície ativa da coorte | Apagar perfil/consentimentos |
| `FISCAL_MONITORING_COMMERCIAL_MONTHLY_ENABLED=false` | Para novos itens mensais | Apagar ledger comercial |
| `FISCAL_MONITORING_COMMERCIAL_ENABLED=false` | Para débitos comerciais | Reverter consumo histórico |
| `FEATURES_KILL_SWITCH` / `SERPRO_KILL_SWITCH` | Bloqueio transversal de egress/jobs novos | Purge de reservas/evidências |
| Reativar leitura compat legada (resolver/purpose antigo) | Continuidade autXML/Termo se necessário | Restaurar PFX aposentados automaticamente |

Política:

- Migrations aditivas: **não** reverter destrutivamente em incidente.
- Autorizações criadas na automação permanecem revogadas/seguras até reconciliação explícita.
- Certificados aposentados **não** são reativados por flag.
- Ledgers comercial + técnico e `platform_privileged_audit_events` são append-only para conciliação.

## 5. Gate externo — jurídico / segurança

Antes de `PLATFORM_PRIVILEGED_CONTEXT=true` em produção:

| Item | Status |
|---|---|
| Revisão LGPD / sigilo fiscal do acesso integral `PLATFORM_ADMIN` (sem membership) | **Pendente** — registrar parecer/ticket fora do repo se contiver PII |
| Threat model: remoção TOTP global de navegação + reconfirmação de senha em mutações/A1 | **Pendente** |
| Plano de rollout por coorte + rollback (§1 e §4) aceito por ops/segurança | **Pendente** |
| Política de retenção da auditoria privilegiada e alertas internos de acesso | **Pendente** (open question de design; não bloqueia implementação) |
| Textos de consentimento técnico versionado (UI) | **Pendente** jurídico de produto (não bloqueia flag unificada em staging) |

Deploy com gate insatfeito: código pode ir, **flag privilegiada permanece OFF** (spec `acesso-global-platform-admin`).

Não reabrir go-live live SERPRO nesta change (non-goal; ver archive `operacionalizar-integra-contador-producao` / external gates).

## 6. Main specs — reconciliação no archive (checklist)

Deltas da change já cobrem MODIFIED/ADDED nas capabilities tocadas. **Não** reescrever main specs agora. No `/opsx-archive` (após 7.1/7.2 e aceite de software), sincronizar e revisar:

### Já com delta nesta change (sync direto no archive)

- [ ] `serpro-gateway-seguro` — autor/Termo derivados; onboarding automatizado; config global só plataforma
- [ ] `serpro-monitoramento-familias` — franquia comercial + desafio senha em `platform_privileged` (não só TOTP membership)
- [ ] `serpro-cadastro-processos-ui` — `CurrentOffice` privilegiado; `/settings` unificado; procuração na UI

### Main specs **sem** delta nesta change — reconciliar manualmente no archive

Conflitos com o modelo desta change (archive go-live promoveu regras opostas):

- [ ] **`serpro-onboarding-procuracoes`**: “`PLATFORM_ADMIN` sem membership → negado” **vs** contexto privilegiado integral; “2FA vigente + escolha deliberada de autor/Termo pelo office” **vs** automação interna + reconfirmação de senha no privilegiado; upload/gestão técnica tenant-facing **vs** A1 canônico + estados acionáveis
- [ ] **`serpro-termo-procurador`**: fluxo A1 gerenciado / assinatura com “ADMIN autoriza” ainda válido em espírito, mas entrada passa a ser perfil+consentimento+canônica e jobs internos — alinhar wording ao delta de gateway
- [ ] **`serpro-credenciais-produtivas`**: TOTP em cutover dual-approval de **contrato global** permanece (não confundir com remoção de TOTP da **navegação** platform); documentar fronteira
- [ ] **`serpro-go-live-controlado`**: smoke faturável / dual TOTP PLATFORM_ADMIN — permanece; não afrouxar por esta change
- [ ] **`serpro-operacao-observavel`**: runbooks/métricas — acrescentar sinais §2 se necessário; sem relaxar kill switch
- [ ] **Novas main specs** a criar no archive a partir dos ADDED: `configuracao-escritorio-unificada`, `acesso-global-platform-admin`, `franquia-agendamento-monitor-serpro`

Precedência até o archive: **deltas desta change** sobre main specs conflitantes para implementação; CI da change ativa valida o nome da change, não paths em `archive/`.

## 7. Encerramento OpenSpec (ainda não)

- [x] `openspec validate separar-configuracao-escritorio-plataforma-serpro --strict`
- [ ] 7.1 backend green (outro agente)
- [ ] 7.2 frontend + `verify.sh` (outro agente)
- [ ] Aceite de software
- [ ] `/opsx-archive` + sync main specs (§6) + commit **no mesmo dia** (`docs(openspec): …`)

**Não arquivar** até 7.1/7.2 e aceite.
