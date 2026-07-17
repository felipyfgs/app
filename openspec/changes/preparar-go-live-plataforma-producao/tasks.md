## 1. Pré-requisitos e release imutável

- [x] 1.1 Concluir o aceite de software, sincronizar/arquivar e commitar no mesmo dia as changes `tornar-platform-admin-proprietario-unico`, `adaptar-aprovacoes-serpro-proprietario-unico`, `onboarding-inicial-plataforma` e `provisionar-admin-inicial-plataforma`; confirmar `openspec validate --specs --strict` e CI verde no commit resultante.
- [x] 1.2 Parametrizar PHP/web por `RELEASE_SHA`/`RELEASE_TAG`, adicionar labels OCI de revisão/data/source e preservar tags SHA anteriores; validar com `docker image inspect` que o SHA esperado aparece nas duas imagens.
- [x] 1.3 Fazer o build/deploy gerar manifesto sanitizado da release com SHA, IDs/digests locais, horário e lote de migrations em diretório fora do repositório, modo 600, e cobrir ausência de segredo com `docker/ops/secret-scan.sh`.

## 2. Readiness interno e orquestrador host

- [x] 2.1 Implementar serviço/comando global `ops:production-readiness --json --no-persist` para ambiente/debug, DB/migrations, Redis, Horizon, scheduler, storage/vault, SMTP, onboarding e contenção fiscal, sem resolver Office nem executar transporte externo.
- [x] 2.2 Implementar heartbeat leve do scheduler, registrá-lo em `backend/routes/console.php` e fazer readiness falhar quando estiver ausente/atrasado, com clock controlado nos testes.
- [x] 2.3 Cobrir o readiness backend com testes positivos e negativos de migrations, Horizon, heartbeat, bootstrap, flags/canais e redaction; executar `vendor/bin/pint --test` e `php artisan test --filter=ProductionReadiness`.
- [x] 2.4 Criar `docker/ops/prod-readiness.sh` com fases `source|predeploy|postdeploy`, JSON allowlisted, modos 700/600 e exit code fail-closed; expor por `make prod-readiness PHASE=...` sem imprimir valores de env.
- [x] 2.5 Implementar smoke postdeploy de DNS/TLS, redirect, HSTS, SPA/API, release e bloqueio de `/up`/Horizon, com allowlist explícita que impeça chamadas NFS-e/SEFAZ/SERPRO; validar os cenários shell contra fixtures/stack isolada.

## 3. Configuração, deploy seguro e backup

- [x] 3.1 Completar `.env.prod.example` e criar exemplo separado de backup host com `BACKUP_PACKAGE_KEY`, destino, retenção e referência off-site; ampliar `prod-check` para modos, placeholders, chaves distintas, SMTP/ACME, RPO/RTO, referências operacionais, flags OFF e portas dev fechadas.
- [x] 3.2 Alterar `docker/ops/deploy.sh` para classificar instalação fresh versus existente, exigir confirmação fresh específica ou backup v3 verificado pré-migration e manter aplicação fechada em estado indeterminado/falha; testar os três caminhos sem apagar volumes.
- [x] 3.3 Fornecer scaffold versionado e testável de timer/cron host para backup completo diário via `make prod-backup`, usando arquivo root-only e sem Docker socket/chave de pacote nos containers; validar pacote v3, `--verify-only`, retenção local 7, trinta referências externas e atraso off-site após 24h.
- [x] 3.4 Atualizar restore/rollback para selecionar tag SHA anterior e restaurar PostgreSQL+vault+private storage como conjunto; exercitar corrupção/recuperação em `make prod-restore-smoke` e documentar que rollback automático de schema é proibido.

## 4. E-mail, observabilidade e runbooks

- [x] 4.1 Implementar `ops:mail-smoke --to=...` com mensagem sem dado fiscal, saída sanitizada e teste usando mail fake; o envio SMTP real permanece ops-gated e nunca roda em CI/deploy automático.
- [x] 4.2 Configurar rotação defensiva dos logs de containers e template de evidência para coleta consultável, uptime, recursos, containers, Horizon, scheduler, backup, on-call, escalonamento, RPO 24h, RTO 4h e restore drill trimestral; testar rejeição de placeholders sem versionar contatos/credenciais.
- [x] 4.3 Criar runbook único de go-live/rollback da plataforma contida cobrindo firewall, DNS/ACME, segredos, onboarding temporário, pre/postdeploy, SMTP, backup off-site, restore drill e separação explícita do rollout SERPRO.

## 5. Verificação e aceite

- [x] 5.1 Atualizar CI para analisar/testar os novos scripts, buildar imagens SHA, rodar cenários fail-closed, Compose/Nginx/PHP, restore smoke e scanner de artefatos; manter `openspec validate preparar-go-live-plataforma-producao --type change --strict --json` verde.
- [ ] 5.2 Executar gates completos de software: backend Pint/PHPUnit, frontend lint/typecheck/unit/generate/E2E/artifacts, `./docker/ops/verify.sh --full`, builds produtivos e `make prod-restore-smoke`, anexando apenas referências sanitizadas ao aceite.
- [ ] 5.3 Live ops-gated: preparar host real, arquivos modo 600, firewall 22/80/443, DNS, SMTP, observabilidade, on-call, RPO/RTO e destino off-site; executar `source`/`predeploy` e manter pendente até existir evidência real.
- [ ] 5.4 Live ops-gated: publicar a release, concluir e encerrar onboarding, executar postdeploy, smoke SMTP recebido, backup off-site e restore drill real; aceitar somente com flags/canais fiscais OFF e sem promover estado SERPRO.
- [ ] 5.5 Após aceite completo de software e live ops, sincronizar `go-live-plataforma-producao`, arquivar a change e commitar no mesmo dia main spec, histórico e eventual ajuste de CI.
