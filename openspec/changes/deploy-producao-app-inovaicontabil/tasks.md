## 1. N0 — Preparação independente

- [x] 1.1 Inventariar host produtivo, IP público, DNS de `app.inovaicontabil.com.br`, firewall 80/443, Docker/Compose, usuário operacional e caminho do repo; registrar evidência sanitizada sem segredos.
- [x] 1.2 Preparar `.env.prod` e `/etc/fiscal-hub/backup.env` a partir dos exemplos, com `chmod 600`, chaves fortes, SMTP real, `ACME_EMAIL`, `BACKUP_PACKAGE_KEY` distinta e flags fiscais em contenção; registrar somente checklist de presença/força, nunca valores.
- [x] 1.3 Revisar `Makefile`, `compose.prod.yml`, `docker/ops/deploy.sh`, `docker/ops/prod-readiness.sh`, `docker/ops/release-manifest.sh` e scripts de backup/restore contra a spec `operacao-producao-dominio`; aplicar ajustes mínimos se algum requisito ainda não estiver coberto e registrar diff.
- [ ] 1.4 Executar gate de fonte com `make prod-readiness PHASE=source` e corrigir falhas de código/configuração versionada; preservar evidência sanitizada.

## 2. N1 — Gates de configuração e release

- [x] 2.1 Validar domínio e origem produtiva em configuração versionada (`APP_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, regra Traefik e redirects HTTPS); adicionar teste ou check se houver lacuna.
  Depende de: 1.3
- [x] 2.2 Executar `make prod-check` e `make prod-config` com `PROD_ENV=.env.prod` e `BACKUP_ENV=/etc/fiscal-hub/backup.env`; corrigir somente configuração não versionada ou patch versionado necessário, mantendo segredos fora do repo.
  Depende de: 1.2, 1.3
- [x] 2.3 Validar pré-condições públicas de DNS, portas 80/443, ACME e ausência de portas dev expostas; executar `make prod-readiness PHASE=predeploy` e registrar evidência.
  Depende de: 1.1, 2.2
- [ ] 2.4 Construir imagens produtivas com `make prod-build RELEASE_SHA=<sha>` e confirmar labels OCI de PHP/web iguais ao SHA planejado.
  Depende de: 1.4, 2.2

## 3. N2 — Deploy controlado

- [ ] 3.1 Definir se o deploy é fresh ou sobre instância existente; para fresh, comprovar base vazia e usar `CONFIRM_FRESH_PROD=SIM`; para existente, criar/verificar `PRE_DEPLOY_BACKUP`; registrar evidência sanitizada.
  Depende de: 2.3
- [ ] 3.2 Executar `make prod-up CONFIRM_PROD=SIM RELEASE_SHA=<sha>` com `PRE_DEPLOY_BACKUP` ou `CONFIRM_FRESH_PROD=SIM` conforme o caso; capturar logs operacionais sanitizados.
  Depende de: 2.4, 3.1
- [ ] 3.3 Verificar saúde local da stack produtiva com Compose: `web`, `php`, `postgres`, `redis`, `horizon`, `scheduler`, `traefik` e `socket-proxy`; corrigir falhas antes de qualquer aceite público.
  Depende de: 3.2
- [ ] 3.4 Executar `PilotSeeder` no container PHP com autorização explícita de produção, verificar `felipe@example.com` ativo como `PLATFORM_ADMIN`, `gustavo@example.com` no office contador e ausência de importação automática de PFX/PDF.
  Depende de: 3.3

## 4. N3 — Pós-deploy operacional

- [ ] 4.1 Executar `make prod-readiness PHASE=postdeploy` e validar HTTPS público, redirecionamento HTTP, resposta da SPA, readiness interno, cookies seguros e release SHA.
  Depende de: 3.2, 3.3, 3.4
- [ ] 4.2 Criar backup produtivo inicial com `make prod-backup`, verificar com `make prod-backup-verify BACKUP=<path>` e registrar referência opaca/offsite sem chaves.
  Depende de: 4.1
- [ ] 4.3 Gerar manifesto sanitizado com `make prod-release-manifest` e conferir que ele referencia SHA, build, domínio, containment fiscal e evidências sem segredos.
  Depende de: 4.1
- [ ] 4.4 Configurar ou validar timer/cron de backup host-only usando `docker/ops/host-backup.*` e `/etc/fiscal-hub/backup.env`; registrar evidência de agenda e retenção.
  Depende de: 4.2

## 5. N4 — Gates integrados e evidência de prontidão

- [ ] 5.1 Executar gate integrado final: `openspec validate --specs --strict`, status da change, `prod-readiness` nas fases necessárias, backup verificado, manifesto sanitizado e URL `https://app.inovaicontabil.com.br` acessível com TLS válido.
  Depende de: 4.1, 4.2, 4.3, 4.4
- [ ] 5.2 Consolidar relatório final de go-live com caminhos das evidências, SHA implantado, estado de containment fiscal, política de backup, comando de rollback/restauração e pendências abertas.
  Depende de: 5.1
