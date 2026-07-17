# Runbook: go-live da plataforma em produção contida

Escopo: publicar a **plataforma** (auth, painel, administração) com integrações
fiscais **desligadas**. Não cobre live smoke SERPRO, canário faturável nem
promoção `PRODUCTION_READY`.

## Pré-requisitos

1. Changes de onboarding/proprietário arquivadas e CI verde no SHA a publicar.
2. Host com Docker, firewall liberando **22/80/443** apenas (produto).
3. DNS `A`/`AAAA` de `app.inovaicontabil.com.br` apontando ao host.
4. Arquivos modo **600**:
   - `.env.prod` (a partir de `.env.prod.example`)
   - `/etc/fiscal-hub/backup.env` (a partir de `docker/ops/backup.env.example`)
5. Referências operacionais preenchidas no template
   `docs/ops/go-live-evidence.template.md` (fora do git ou com valores opacos).

## Fluxo

### 1. Source

```bash
RELEASE_SHA=$(git rev-parse HEAD)
export RELEASE_SHA RELEASE_TAG=sha-${RELEASE_SHA:0:12}
make prod-readiness PHASE=source
```

Worktree limpo, SHA = HEAD, Compose válido, pré-requisitos arquivados.

### 2. Predeploy

```bash
make prod-readiness PHASE=predeploy
make prod-config
```

Valida modos 600, placeholders, chaves, SMTP/ACME, contenção fiscal, portas dev.

### 3. Build imutável

```bash
make prod-build
docker image inspect fiscal-hub-php:$RELEASE_TAG --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'
docker image inspect fiscal-hub-web:$RELEASE_TAG --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'
```

Tags SHA **não** são removidas automaticamente (rollback).

### 4. Deploy

**Fresh (primeira instalação):**

```bash
CONFIRM_PROD=SIM CONFIRM_FRESH_PROD=SIM make prod-up
# Com onboarding temporário: INITIAL_ONBOARDING_* no .env.prod + HTTPS
```

**Upgrade (dados existentes):**

```bash
# Backup v3 verificado antes da migration
BACKUP_PACKAGE_KEY=... make prod-backup
make prod-backup-verify BACKUP=/caminho/nfse-backup-...
CONFIRM_PROD=SIM PRE_DEPLOY_BACKUP=/caminho/nfse-backup-... make prod-up
```

Falhas mantêm web/php/Horizon/scheduler **fechados**. Não há rollback automático
de schema.

### 5. Onboarding inicial

1. Abrir janela só com base vazia + token forte + HTTPS.
2. Completar bootstrap web do primeiro `PLATFORM_ADMIN`/Office.
3. Remover `INITIAL_ONBOARDING_ENABLED`/`TOKEN` do `.env.prod` e redeploy/recreate.
4. Postdeploy deve falhar se a janela permanecer aberta após bootstrap.

### 6. Postdeploy

```bash
make prod-readiness PHASE=postdeploy
```

Comprova DNS/TLS, redirect, HSTS, SPA, bloqueio `/up` e `/horizon`, readiness
interno. **Não** chama NFS-e, SEFAZ nem SERPRO.

### 7. SMTP (ops-gated)

```bash
docker compose --env-file .env.prod -f compose.prod.yml -p fiscal-hub \
  exec -T php php artisan ops:mail-smoke --to=ops@seu-dominio --json
```

Confirmar recebimento manualmente. Não roda em CI/deploy automático.

### 8. Backup off-site e restore drill

```bash
# Job host diário (systemd/cron) — ver docker/ops/host-backup.*.example
make prod-backup
# Replicar pacote cifrado off-site; registrar OFFSITE_BACKUP_REFERENCE
# Restore drill real (isolado) trimestral — CI smoke NÃO substitui
make prod-restore-smoke   # só prova de código
```

Política inicial: RPO 24h, RTO 4h, 7 pacotes locais, 30 refs externas,
off-site atrasado após 24h.

## Rollback

1. Manter tráfego fechado (`compose stop web horizon scheduler php`).
2. Preservar evidências e `PRE_DEPLOY_BACKUP`.
3. Selecionar **tag SHA anterior** (`fiscal-hub-php:sha-…` / `web`).
4. Restaurar **PostgreSQL + vault + private storage** em conjunto
   (`make prod-restore BACKUP=... CONFIRM_PROD_RESTORE=SIM`).
5. Subir a release anterior e repetir pre/postdeploy.
6. **Não** apagar volumes, ACME ou chaves para “tentar de novo”.
7. **Proibido** rollback automático de migration destrutiva.

## Contenção fiscal (invariável neste go-live)

- `FEATURES_GLOBAL_ENABLED=false`, mutações false
- `SERPRO_KILL_SWITCH=true`, drivers ≠ `real`, fake clients false
- Canais SEFAZ/autXML OFF
- Nenhum health check em rota `/Apoiar|Monitorar|Consultar|Emitir|Declarar`

Promoção SERPRO: `docs/ops/runbooks/serpro-go-live-rollout.md` e change própria.
