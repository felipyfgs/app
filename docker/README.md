# Docker

Cada imagem customizada tem contexto próprio e usa o nome canônico `Dockerfile`:

```text
docker/
├── frontend/   # Nuxt em desenvolvimento e geração estática
├── nginx/      # edge HTTP e entrega da SPA
├── php/        # PHP-FPM, Horizon, scheduler e comandos Artisan
├── traefik/    # HTTPS/ACME e inicialização segura do armazenamento
└── ops/        # backup, restore e verificação do backend
```

Arquivos de inicialização se chamam `entrypoint.sh`; configurações usam o nome curto
do papel (`app.ini`, `fpm.conf`, `app.conf`). O Compose concentra apenas a topologia,
os volumes, as variáveis e os comandos dos processos.

Os builds seguem o padrão recomendado pela documentação do Docker: um `Dockerfile`
principal na raiz de cada contexto de serviço.

## Produção

`compose.prod.yml` é uma stack independente para `app.inovaicontabil.com.br`. Ela:

- gera imagens imutáveis do backend e da SPA, sem bind mounts de código;
- publica somente Traefik nas portas `80` e `443`;
- mantém PostgreSQL, Redis, PHP-FPM e Nginx apenas nas redes internas;
- redireciona HTTP para HTTPS e emite o certificado pelo desafio HTTP-01 da
  Let's Encrypt;
- emite HSTS apenas no Nginx produtivo;
- persiste o estado ACME em volume nomeado, inicializando `acme.json` com modo
  `600`; certificados e chaves não entram no repositório.

Antes do primeiro deploy:

1. Aponte os registros DNS `A` e, se usado, `AAAA` de
   `app.inovaicontabil.com.br` para o servidor.
2. Libere as portas TCP `80` e `443` no firewall/NAT.
3. Copie `.env.prod.example` para `.env.prod`, substitua todos os placeholders e
   execute `chmod 600 .env.prod`. Configure SMTP conforme o provedor; `smtp` em
   `MAIL_SCHEME` não afirma TLS implícito, enquanto `smtps` deve ser usado apenas
   quando o provedor exigir esse esquema.
4. Valide com `make prod-config`.
5. Suba com `make prod-up CONFIRM_PROD=SIM`.

O dashboard do Traefik permanece desativado e nenhum certificado é montado do
host. Somente `socket-proxy` monta o socket Docker; o Traefik acessa uma allowlist
somente leitura (`containers`, `events`, `networks`, `ping`, `version`) por uma
rede interna exclusiva. O provider usa `exposedByDefault=false` e apenas `web`
possui a label de exposição.

O IP `172.31.255.2` pertence exclusivamente ao Traefik na rede edge. O Nginx
aceita `X-Forwarded-For` somente dessa origem e entrega ao FastCGI um IP
normalizado. Se essa sub-rede colidir com a rede do host, altere em conjunto o
IPAM do Compose, o IP estático do Traefik e `set_real_ip_from` no `prod.conf`.

`prod-up` retira a aplicação antiga do tráfego, espera banco/Redis/edge,
executa migrations com a imagem nova, espera os processos novos e só conclui
após smokes de PHP e SPA. Falhas mantêm os processos de aplicação parados para
evitar mistura entre código e schema incompatíveis.

Backups produtivos usam `make prod-backup` e guardam três componentes separados:
PostgreSQL, vault e `private_storage`; a chave mestra do vault continua fora do
backup. A chave do pacote (`BACKUP_PACKAGE_KEY`) fica só no host
(`/etc/fiscal-hub/backup.env`, modo 600) — ver `docker/ops/backup.env.example` e
timers em `docker/ops/host-backup.*.example`. `prod-restore` exige
`CONFIRM_PROD_RESTORE=SIM` e restaura o conjunto completo. Rollback automático de
schema é proibido. O gate `prod-restore-smoke` prova o código em projeto isolado,
mas não substitui o restore drill operacional trimestral.

Release: imagens `fiscal-hub-php`/`fiscal-hub-web` usam tag `sha-<12>` derivada de
`RELEASE_SHA`, labels OCI (`revision`, `created`, `source`) e manifesto sanitizado
via `make prod-release-manifest` (fora do repo). Tags SHA anteriores são
preservadas.

Gate de go-live: `make prod-readiness PHASE=source|predeploy|postdeploy` (script
`docker/ops/prod-readiness.sh`). Runbook:
`docs/ops/runbooks/go-live-plataforma-producao.md`.

Logs produtivos seguem para `stderr` com rotação defensiva `json-file`
(max-size/max-file no Compose). O mailer de produção é SMTP real;
`MAIL_MAILER=log` não é aceito. Smoke de e-mail: `php artisan ops:mail-smoke --to=...`
(ops-gated, nunca no CI automático).

No CI, `pnpm run generate` precede E2E: o Playwright valida o preview do artefato
estático já gerado, não um servidor Nuxt de desenvolvimento reutilizado.

Referências: [Dockerfile](https://docs.docker.com/build/concepts/dockerfile/),
[contexts adicionais](https://docs.docker.com/reference/compose-file/build/#additional_contexts)
[Traefik com ACME HTTP-01](https://doc.traefik.io/traefik/user-guides/docker-compose/acme-http/)
e [segurança do provider Docker](https://doc.traefik.io/traefik/reference/install-configuration/providers/docker/).
