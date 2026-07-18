## Context

A stack produtiva já existe como composição Docker separada: `compose.prod.yml` fixa `APP_URL`, `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS` em `app.inovaicontabil.com.br`, expõe Traefik em 80/443, usa ACME HTTP challenge, gera imagens `fiscal-hub-php` e `fiscal-hub-web` por `RELEASE_TAG`, mantém Postgres/Redis/vault/private storage em volumes e executa `php`, `horizon` e `scheduler`.

O `Makefile` já concentra os comandos de produção: `prod-check`, `prod-config`, `prod-build`, `prod-up`, `prod-backup`, `prod-backup-verify`, `prod-restore`, `prod-restore-smoke`, `prod-readiness` e `prod-release-manifest`. O deploy real deve aproveitar esse caminho existente, endurecer qualquer lacuna encontrada durante a implementação e produzir evidências sanitizadas, sem copiar segredos para specs, logs ou artefatos.

## Goals / Non-Goals

**Goals:**
- Publicar `https://app.inovaicontabil.com.br` por Traefik/HTTPS com redirecionamento HTTP para HTTPS.
- Rodar a stack produtiva com Laravel em produção, Nuxt SPA servida pelo `web`, filas Horizon, scheduler, Postgres, Redis, vault e storage privado persistentes.
- Validar ambiente, DNS, TLS, portas, containment fiscal, backup, release SHA, healthchecks e readiness antes do aceite.
- Carregar a massa piloto de `dados/` com `felipe@example.com` como acesso principal e senha inicial `password`.
- Manter backups cifrados, manifesto sanitizado e caminho operacional de rollback/restauração.
- Registrar um roteiro executável até conclusão, com gates que possam falhar cedo e apontar a correção necessária.

**Non-Goals:**
- Habilitar SERPRO live, `SERPRO_CAPABILITY_*=real`, smoke SERPRO faturável ou qualquer egress fiscal produtivo.
- Ativar mutações fiscais, canais SEFAZ outbound ou feature flags globais.
- Alterar o modelo multitenant, permissões, `CurrentOffice`, RBAC ou plataforma admin.
- Provisionar juridicamente o domínio, contratar SMTP/SERPRO/SEFAZ ou emitir parecer de conformidade.
- Importar PFX/PDF para o cofre, ativar SERPRO real ou executar carga de cliente fora da massa piloto autorizada.

## Decisions

1. Usar a stack produtiva existente em vez de criar um novo orquestrador.
   - Racional: `compose.prod.yml` e `docker/ops/deploy.sh` já modelam Traefik, healthchecks, migrações, volumes e labels OCI por SHA.
   - Alternativa considerada: criar scripts novos de deploy. Rejeitada porque duplicaria guardrails já testáveis em `make prod-*`.

2. Fazer go-live por release imutável baseada em `RELEASE_SHA`.
   - Racional: imagens PHP/web recebem label OCI com o SHA e `prod-build` valida que a imagem corresponde ao commit planejado.
   - Alternativa considerada: usar apenas tag mutável `prod`. Rejeitada como critério primário porque dificulta auditoria e rollback.

3. Manter o primeiro deploy em contenção fiscal.
   - Racional: o domínio público não deve implicar capacidade fiscal real. `prod-check` deve continuar bloqueando `FEATURES_GLOBAL_ENABLED=true`, `FEATURES_MUTATING_ENABLED=true`, `SERPRO_CAPABILITY_*=real`, `SERPRO_KILL_SWITCH=false`, `SERPRO_SMOKE_ENABLED=true` e canais SEFAZ ligados.
   - Alternativa considerada: liberar SERPRO `PRODUCTION` junto com o domínio. Rejeitada por exigir autorização operacional própria, credenciais reais, quatro olhos e controles de bilhetagem.

4. Tratar backup como pré-condição operacional, não como atividade pós-go-live opcional.
   - Racional: instância existente exige `PRE_DEPLOY_BACKUP` verificado, e instalação fresh exige `CONFIRM_FRESH_PROD=SIM`; após subir, deve haver backup cifrado inicial e evidência de verificação.
   - Alternativa considerada: subir a stack e configurar backup depois. Rejeitada porque vault/private storage e banco passam a conter dados operacionais assim que o sistema entra em uso.

5. Aceitar somente evidências sanitizadas.
   - Racional: readiness, manifestos e logs devem demonstrar estado sem expor `.env.prod`, chaves, PFX, tokens, XML fiscal ou senhas SMTP.
   - Alternativa considerada: anexar dumps de env ou logs integrais. Rejeitada por violar as regras de segurança do projeto.

6. Permitir o `PilotSeeder` em produção somente por flag explícita.
   - Racional: o usuário autorizou a massa de `dados/`, mas o ambiente produtivo não deve depender de `APP_ENV=local` para semear banco real. A flag `PILOT_SEED_ALLOW_PRODUCTION=true` torna a exceção auditável e mantém PFX/PDF fora do cofre.
   - Alternativa considerada: rodar o seeder com `APP_ENV=local` contra o banco produtivo. Rejeitada porque altera comportamento global de ambiente durante uma operação de produção.

## Mapa de dependências

- DAG externo: `deploy-producao-app-inovaicontabil` é C0 e não depende de upstream ativo.
- Ownership de capability: esta change possui `operacao-producao-dominio`; não modifica contratos fiscais, SERPRO, SEFAZ, RBAC ou UX.
- Marcos que liberam consumidores: specs desta change liberam implementação operacional; `verify` libera archive e commit; o deploy público só é aceito após readiness postdeploy e backup inicial.
- Pontos paralelos: preparação de DNS/host, preenchimento seguro de `.env.prod`, configuração de `/etc/fiscal-hub/backup.env` e validações source podem avançar em paralelo porque não editam o mesmo contrato.
- Coordenação com changes ativas: se outra change tocar `Makefile`, `compose.prod.yml`, `docker/ops/*`, auth de produção ou flags fiscais antes do deploy, reconciliar o diff e validar novamente `prod-check`, `prod-config` e `prod-readiness`.
- Ordem de rollout: source gate, predeploy gate, build por SHA, deploy confirmado, postdeploy gate, backup inicial, manifesto de release, registro final.
- Ordem de rollback: para falha antes do tráfego, corrigir e repetir gates; para falha após migração, usar backup verificado e tag SHA anterior quando aplicável; rollback automático de schema permanece proibido.

## Risks / Trade-offs

- DNS ainda não apontado para o host → mitigar validando `getent ahosts app.inovaicontabil.com.br`, portas 80/443 e readiness `PHASE=predeploy` antes de `prod-up`.
- ACME falha por firewall, DNS incorreto ou rate limit → mitigar com portas públicas liberadas, e-mail ACME válido e tentativa somente depois do precheck.
- `.env.prod` incompleto ou com placeholders → mitigar com `prod-check` fail-closed e revisão manual sem registrar valores secretos.
- Host já possui volumes produtivos sem backup → mitigar exigindo `PRE_DEPLOY_BACKUP` verificado ou `CONFIRM_FRESH_PROD=SIM` para base vazia comprovada.
- Deploy sobe, mas filas/scheduler não processam → mitigar com healthchecks, `ops:production-readiness --json --no-persist` e inspeção de serviços `horizon`/`scheduler`.
- Primeira exposição pública revela uma rota não endurecida → mitigar com `APP_DEBUG=false`, cookies seguros, Sanctum same-origin, feature flags OFF e smoke público restrito a readiness/HTML/login sem ações fiscais.
- Backups cifrados ficam irrestauráveis por chave externa errada → mitigar com `prod-backup-verify`, restore smoke isolado e armazenamento root-only da `BACKUP_PACKAGE_KEY` fora dos containers.

## Migration Plan

1. Preparar host e DNS: Docker/Compose instalados, firewall liberando 80/443, domínio apontando para o host e stack dev desligada de portas públicas.
2. Preparar segredos: copiar `.env.prod.example` para `.env.prod`, `chmod 600`, preencher chaves fortes, SMTP real, `ACME_EMAIL` e manter flags fiscais em contenção; preparar `/etc/fiscal-hub/backup.env` root-only com chave distinta.
3. Validar fonte e configuração: executar `make prod-readiness PHASE=source`, `make prod-check`, `make prod-config` e corrigir falhas.
4. Construir release: executar `make prod-build RELEASE_SHA=<sha>` e confirmar labels OCI PHP/web.
5. Executar pré-deploy: para base existente, verificar `PRE_DEPLOY_BACKUP`; para base fresh, usar `CONFIRM_FRESH_PROD=SIM`; executar `make prod-readiness PHASE=predeploy`.
6. Subir produção: executar `make prod-up CONFIRM_PROD=SIM RELEASE_SHA=<sha>` com os parâmetros apropriados.
7. Carregar piloto: executar `php artisan db:seed --class=PilotSeeder --force` no container PHP com `PILOT_SEED_ALLOW_PRODUCTION=true` e verificar `felipe@example.com`.
8. Validar pós-deploy: executar `make prod-readiness PHASE=postdeploy`, verificar HTTPS público, serviços, cookies seguros, healthchecks e readiness interno.
9. Fechar operação: executar backup inicial cifrado, verificar backup, gerar manifesto sanitizado e registrar evidências.

## Open Questions

- Qual host/IP público receberá o DNS final de `app.inovaicontabil.com.br`?
- Qual conta de e-mail será usada em `ACME_EMAIL` e no SMTP transacional inicial?
- O primeiro deploy será base fresh ou restauração/continuidade de uma instância existente?
- Onde será guardada a cópia offsite referenciada por `/etc/fiscal-hub/backup.env`?
