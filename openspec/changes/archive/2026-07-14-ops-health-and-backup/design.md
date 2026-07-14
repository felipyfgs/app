## Context

O produto interno já oferece:

- Resumo agregado em `GET /api/v1/operations/summary` (contagens de bloqueados, falhas 24h, A1 em 30d).
- Histórico de sync, detalhe de cliente (sync/A1), elegibilidade centralizada e auditoria em banco.
- Cofre em filesystem (`VAULT_DISK_ROOT`, envelope) e PostgreSQL como verdade de cursores/NSU.
- Home e `NotificationsSlideover` que **derivam** alertas grosseiros do summary + últimas falhas de sync — sem item por estabelecimento e sem status de backup.

A spec `operations-dashboard` já exige saúde por estabelecimento e backup verificável; a implementação parou em totais. O piloto com dados reais exige fechar esses buracos sem abrir gestão de usuários, override de NSU ou KMS cloud.

## Goals / Non-Goals

**Goals:**

- Fila operacional (inbox) por escritório, tipada, severizada e acionável por deep-link, sem material sensível.
- Metadados de backup/restore drill da **instância**, com alerta de atraso >24h no painel.
- Comandos operacionais reproduzíveis (Docker Compose) para backup e ensaio de restore.
- UI no template: home, notificações e lista de saúde; status de backup também em Admin (ADMIN+2FA).
- Testes automatizados de isolamento, sanidade de payload e regra de alerta.

**Non-Goals:**

- Desbloqueio de cursor / edição de NSU (change futura).
- Convite/gestão de usuários; e-mail ou webhooks de alerta.
- Smoke ADN real; restore “um clique” em produção.
- Incluir `VAULT_MASTER_KEY` no artefato de backup; PITR/WAL shipping; multi-região.
- Multi-escritório SaaS (backup permanece por **deployment/instância** no MVP).

## Decisions

### 1. Inbox como projeção sob demanda (não fila persistida)

**Decisão:** agregar itens em tempo de request a partir de `sync_cursors`, `sync_runs`, `client_credentials` e regras de elegibilidade já existentes.

**Por quê:** evita dual-write e atraso de job de projeção; o volume por escritório no MVP é compatível com queries indexadas.  
**Rejeitado:** tabela `ops_inbox_items` alimentada por eventos — útil em escala, prematura agora.

### 2. Tipos e severidade fixos (whitelist)

| `type` | Severidade base | Fonte |
|--------|-----------------|-------|
| `cursor_blocked` | `critical` | cursor `BLOCKED` |
| `cursor_error` | `high` | cursor `ERROR` (com `last_error` sanitizado) |
| `sync_failed_recent` | `high` | último `SyncRun` FAILED nas 24h por estabelecimento (dedupe) |
| `credential_expired` | `critical` | A1 ACTIVE com `valid_to` < now **ou** status expirado operacional |
| `credential_expiring_7d` | `high` | alertas 7d/1d |
| `credential_expiring_30d` | `medium` | alerta 30d sem 7d/1d |
| `backup_stale` | `high` | sem backup SUCCESS &lt; 24h (instância; visível a todos autenticados do escritório) |
| `backup_never` | `critical` | nenhum SUCCESS registrado |

Itens **não** gerados para inelegibilidade “esperada” silenciosa (capture off deliberado sem falha, idle normal). Captura off **com** cursor `BLOCKED`/`ERROR` continua pelo tipo de cursor.

### 3. Contrato API

- `GET /api/v1/operations/inbox?severity=&type=&limit=&cursor=`  
  - Resposta: `{ data: InboxItem[], meta: { next_cursor, total_estimate?, generated_at } }`  
  - Cada item: `id` estável (hash do tipo+subject), `type`, `severity`, `title`, `body`, `reasons[]` (códigos), `client_id`, `establishment_id?`, `occurred_at`, `links` (`client`, `sync`, `credential` paths SPA), `actions[]` (`open`, `trigger_sync` se papel + elegível).  
  - **Proibido:** PFX, senha, PEM, XML, `vault_object_id`, body remoto ADN, cookie/token.

- Ampliar `GET /api/v1/operations/summary` com:
  - `inbox_critical`, `inbox_high`, `inbox_total` (contagens)
  - `backup`: `{ last_success_at, last_status, last_restore_drill_at, last_restore_drill_status, stale: bool, never: bool }`

### 4. Backup da instância, não por `office_id`

**Decisão:** no MVP de um escritório por deployment, metadados de backup são **globais da instância** (tabela `instance_backup_runs` sem `office_id`, ou com `office_id` nulo). A API de summary/inbox **expõe** o status a usuários autenticados do escritório ativo (alerta operacional); comandos Artisan são de **infra** (CLI no container), não rotas HTTP de restore.

**Artefatos de um run `full` (mínimo):**

1. `pg_dump` lógico do banco da aplicação (sem secrets de env).  
2. Cópia/arquivo do diretório do cofre (`VAULT_DISK_ROOT`) — **objetos já cifrados**.  
3. Se XMLs fiscais estiverem fora do cofre/DB, incluir o path de storage de documentos configurado.  
4. Manifest JSON: paths relativos, tamanhos, checksums SHA-256, versões de app se disponíveis.  
5. **Nunca** gravar `VAULT_MASTER_KEY` no manifest nem no tarball.

Destino: diretório configurável (`BACKUP_DISK_ROOT`, fora do webroot), retenção simples (N runs) documentada.

**Restore drill:** comando que restaura em diretório/DB temporário ou valida integridade do último artefato (existência + checksums + sample decrypt **opcional e desligado por padrão** — o default do drill é validar manifesto + `pg_restore --list` / smoke de arquivos, **sem** exigir master key no CI). Registro `kind=restore_drill` com SUCCESS/FAILED.

### 5. Comandos

- `php artisan ops:backup-run {--kind=full|database|vault}` — executa, grava `instance_backup_runs`, exit code ≠0 se falha parcial do kind pedido.  
- `php artisan ops:backup-restore-drill {--run=latest|id}` — valida artefato; grava drill.  
- Agendamento opcional no `routes/console.php` (ex.: diário) **somente se** `BACKUP_SCHEDULE_ENABLED=true` (default false em dev).

### 6. Frontend (template)

- Home: card/alerta de backup (stale/never) + bloco “Atenção operacional” com top N da inbox e link “Ver todos”.  
- Nova rota lista (ex. `/health` ou seção em home expandida + deep-link `/syncs` filtrável): preferir **`/health`** com tabela server-side no estilo template, filtros `severity`/`type` na URL.  
- `NotificationsSlideover`: preferir `GET /operations/inbox?limit=20` em vez de inventar contagens soltas; manter fallback se inbox falhar.  
- Admin: card somente leitura de backup (ADMIN+2FA) com timestamps e status — **sem** botão de restore em prod.  
- Deep-links: `/clients/{id}/sincronizacao`, `/clients/{id}/certificado`, `/syncs` conforme `links`.

### 7. Autorização

- Inbox e summary: usuários autenticados ativos com contexto de escritório (já gated).  
- `trigger_sync` na action list: somente se policy atual de sync manual permitir **e** elegibilidade true.  
- Comandos de backup: CLI com usuário do host/container; não expor HTTP.

### 8. Observabilidade e auditoria

- Log estruturado de backup: kind, status, duração, byte_size, **sem** paths absolutos se contiverem home do user — paths relativos ao root de backup.  
- Opcional: `audit_logs` action `ops.backup_run` / `ops.restore_drill` com actor `system` quando via scheduler.

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Inbox lenta com muitos estabelecimentos | Índices em `status`, `next_sync_at`, `valid_to`; limit default 50; total_estimate opcional; sem N+1 (eager client/est). |
| Falso positivo de “problemas” | Whitelist de types; não listar idle/capture-off sem erro. |
| Backup “verde” incompleto | Status SUCCESS só se **todos** os componentes do kind `full` ok; falha parcial = FAILED + mensagem sanitizada. |
| Operador achar que dump restaura A1 | Runbook: master key **separada**; drill documenta o procedimento offline. |
| Expor secrets no JSON da inbox | Testes de superfície (string scan) + DTO whitelist. |
| Concorrência de dois backup-run | Lock file/advisory lock no comando. |

## Migration Plan

1. Migração `instance_backup_runs` + config `backup.php` / env examples.  
2. Comandos + testes unitários de manifest (sem dump real pesado: filesystem fake).  
3. Serviço `OperationsInboxBuilder` + endpoint + testes feature.  
4. Ampliar summary.  
5. Frontend: types, home, `/health`, notifications, admin card.  
6. Documentar runbook em `openspec/changes/ops-health-and-backup/ops-notes.md` (ou README ops).  
7. Validar `openspec validate` e suítes PHPUnit/vitest/typecheck.

## Open Questions

- Retenção padrão de artefatos (sugerido: 7 diários locais) — configurável, default 7.  
- Se o storage de XML já está 100% no cofre via `SecureObjectStore`, o kind `full` = DB + vault root apenas (confirmar na implementação lendo paths reais).  
- Nome da rota SPA: `/health` vs `/operations` — default **`/health`** (curto, não conflita com “operations” da API).
