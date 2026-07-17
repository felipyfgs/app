## Context

A stack produtiva single-host já existe em `compose.prod.yml`: Traefik/ACME publica somente 80/443, Nginx serve a SPA e encaminha API/Fortify ao PHP-FPM, Postgres e Redis ficam internos, Horizon e scheduler rodam como processos separados e o deploy migra antes de liberar tráfego. O CI já constrói as imagens, valida Compose/Nginx/PHP e executa restore smoke isolado.

O gap está entre “artefato tecnicamente construível” e “instância aceita para uso”: o tag local `prod` não identifica inequivocamente o commit, o preflight não produz um relatório único, o deploy não exige backup anterior à migration, não há smoke HTTPS externo canônico e as responsabilidades de backup off-site, logs, alertas, on-call e RPO/RTO continuam sem evidência. Nesta máquina, o DNS já aponta para a VPS, mas somente a stack dev ocupa 3000/8080 e não existe `.env.prod` nem listener 443.

A change atua somente na plataforma/infraestrutura global. Não cria rota tenant, não aceita `office_id`, não muda papéis e não acessa dados fiscais. Ela depende do onboarding inicial já especificado e preserva a separação entre `PLATFORM_ADMIN` e `OfficeMembership`.

## Goals / Non-Goals

**Goals:**

- transformar build, preflight, deploy, smoke e aceite em um fluxo único, fail-closed e auditável;
- identificar imagens e evidências por commit SHA imutável;
- impedir migration de uma instância existente sem backup cifrado verificável;
- provar HTTPS, serviços, migrations, filas, scheduler, e-mail, backup e restore antes do aceite;
- permitir o primeiro bootstrap web por janela curta e comprovar seu encerramento;
- manter integrações fiscais e contexto privilegiado contidos no primeiro go-live;
- documentar rollback e responsabilidades operacionais sem versionar segredo, contato pessoal ou identidade de Office.

**Non-Goals:**

- alta disponibilidade, Swarm/Kubernetes, registry externo ou migração para múltiplos hosts;
- habilitar NFS-e, SEFAZ, autXML, drivers SERPRO reais, mutações ou flags globais;
- live smoke, canário faturável, ticket SERPRO, contrato, jurídico ou promoção `PRODUCTION_READY` do Integra Contador;
- criar dashboard próprio de métricas ou acoplar o produto a um fornecedor de observabilidade;
- alterar páginas autenticadas, shell Nuxt ou fluxos tenant.

## Decisions

### 1. Um orquestrador host terá fases explícitas e saída sanitizada

`docker/ops/prod-readiness.sh` será a entrada canônica, exposta por `make prod-readiness`. Ele terá fases `source`, `predeploy` e `postdeploy`, executáveis em conjunto ou isoladamente:

- `source`: worktree limpo, commit existente, changes pré-requisito encerradas, Compose válido, scripts analisáveis e imagens identificadas pelo SHA;
- `predeploy`: `.env.prod`/arquivo de backup com modo 600, ausência de placeholders, segredos com formato mínimo, contenção fiscal, disco/memória, ausência da stack dev, portas 80/443 disponíveis ou pertencentes ao edge produtivo e DNS coerente;
- `postdeploy`: HTTPS e certificado válidos, redirect 80→443, HSTS, superfícies internas bloqueadas, release esperada, migrations, Redis, Horizon, heartbeat do scheduler, backup/restore e evidências operacionais.

O script retornará código diferente de zero para requisito obrigatório e produzirá JSON em diretório host configurável, fora do repositório e com modo 700/600. O JSON conterá apenas SHA, timestamps, nomes de checks, estados, hashes e referências opacas. Valores de env, hosts SMTP privados, contatos e payloads não serão impressos.

Alternativa considerada: ampliar apenas `make prod-check`. Rejeitada porque mistura validação estática com provas que só existem depois do deploy e não gera evidência persistente.

### 2. Release será endereçada pelo commit, não pelo tag mutável `prod`

`RELEASE_SHA` será o SHA completo do commit aprovado. As imagens PHP e web receberão tag derivada do SHA e labels OCI (`revision`, `created`, `source`); o Compose consumirá `RELEASE_TAG`, sem depender exclusivamente de `:prod`. O deploy gravará manifesto sanitizado com SHA, digests/IDs locais, migration batch e horário. Tags anteriores não serão removidas automaticamente, preservando um artefato de rollback no host.

O gate `source` recusará árvore suja e SHA diferente de `HEAD`. O CI continuará construindo com um SHA sintético/real explicitamente informado, enquanto produção exigirá commit remoto aprovado e CI verde como evidência operacional. A integração com API do GitHub não será requisito de runtime; o operador registra uma referência opaca do run aprovado.

Alternativa considerada: manter `fiscal-hub-*:prod`. Rejeitada porque dois deploys distintos se tornam indistinguíveis e o rollback pode apontar para uma imagem sobrescrita.

### 3. Configuração produtiva será dividida entre runtime e backup host

`.env.prod` continuará sendo o env do Compose, modo 600, com chaves da aplicação/vault, banco, SMTP, ACME e flags fail-closed. Uma configuração root-only separada, por padrão `/etc/fiscal-hub/backup.env`, conterá `BACKUP_PACKAGE_KEY`, `BACKUP_DIR` e parâmetros de retenção/replicação. A chave do pacote de backup não será entregue aos containers do aplicativo nem confundida com `VAULT_MASTER_KEY`.

Referências operacionais não secretas (`CI_RUN_REFERENCE`, `ON_CALL_REFERENCE`, `OBSERVABILITY_REFERENCE`, `OFFSITE_BACKUP_REFERENCE`, RPO/RTO) ficarão em um arquivo de aceite separado ou no diretório de evidência, nunca como dados funcionais do Office. O preflight validará presença/formato sem revelar conteúdo.

Alternativa considerada: colocar todas as chaves em `.env.prod`. Rejeitada por ampliar o acesso do runtime à chave capaz de abrir os pacotes de backup.

### 4. Instância existente exige backup pré-migration; instalação nova exige confirmação distinta

Antes de parar processos ou migrar, o deploy detectará se há schema/dados persistentes. Instância existente exigirá um backup v3 cifrado recém-criado, `--verify-only` aprovado e registrado no manifesto. Se a aplicação antiga estiver indisponível mas o volume do banco existir, o deploy falhará e encaminhará ao runbook de backup offline, sem presumir instalação vazia.

Uma instalação realmente nova somente poderá pular o backup com `CONFIRM_FRESH_PROD=SIM`, depois de provar banco sem migrations e vault/private storage vazios. `CONFIRM_PROD=SIM` não substituirá essa confirmação específica.

Falhas depois da migration manterão web, PHP, Horizon e scheduler fechados. O rollback será explícito: selecionar a tag SHA anterior, restaurar o backup pré-deploy compatível e repetir readiness; não haverá rollback automático de migration destrutiva.

Alternativa considerada: subir a imagem antiga automaticamente após falha. Rejeitada porque código anterior contra schema parcialmente migrado pode corromper dados.

### 5. Backup periódico será um job do host, com prova off-site separada

O backup consistente existente em `docker/ops/backup.sh` continuará como mecanismo canônico porque entra em manutenção, pausa consumidores e empacota Postgres, vault e storage privado. Um timer/cron host documentado o executará com o arquivo root-only, fora do scheduler Laravel; assim o container não recebe Docker socket nem chave do pacote.

O aceite exigirá: pacote v3 cifrado recente, verificação de checksums/autenticidade, replicação off-site identificada por referência opaca e restore drill real em ambiente isolado. O smoke destrutivo de CI prova o código, mas não substitui o drill com o artefato e a custódia reais. Retenção local e off-site serão alinhadas ao RPO/RTO registrados.

Alternativa considerada: habilitar `BACKUP_SCHEDULE_ENABLED` no scheduler da aplicação. Rejeitada para produção porque o job host já coordena containers/maintenance e mantém a chave de pacote fora do runtime.

### 6. Readiness interno será um comando global, sem API pública nova

Um comando Artisan `ops:production-readiness --json --no-persist` agregará checks sanitizados de ambiente/debug, conexão DB, migrations pendentes, Redis, Horizon, heartbeat do scheduler, storage/vault escrevível, configuração de mail, estado do onboarding e flags/canais contidos. Um comando agendado leve atualizará o heartbeat do scheduler em cache/DB sem executar integração externa.

Não haverá endpoint público de readiness; `/up` e Horizon continuarão bloqueados no Nginx externo. O orquestrador executará o comando com `docker compose exec -T php`. O comando não consulta `Office`, não recebe `office_id` e não chama SERPRO/SEFAZ/NFS-e.

Alternativa considerada: publicar `/ready`. Rejeitada por ampliar a superfície externa e revelar estado operacional desnecessário.

### 7. Smoke externo será seguro e dividido entre automático e ops-gated

O smoke automático fará apenas HTTP(S) e checks internos gratuitos: certificado/hostname, redirect, HSTS, SPA, status público do onboarding, bloqueio de `/up` e `/horizon`, release SHA e comando interno de readiness. Nenhuma rota fiscal será chamada como health check.

SMTP terá comando explícito `ops:mail-smoke --to=<destino>` com mensagem sem dado fiscal, acompanhado de teste com mail fake. O envio real e a confirmação de recebimento serão ops-gated e registrados no aceite; não ocorrerão em CI/deploy automaticamente.

### 8. O bootstrap é uma janela temporária e a contenção fiscal é invariável

Instalação nova poderá iniciar com `INITIAL_ONBOARDING_ENABLED=true` e token forte somente enquanto a base estiver estruturalmente vazia. O smoke aceitará esse estado apenas com `CONFIRM_INITIAL_ONBOARDING=SIM`. Após criar o primeiro `PLATFORM_ADMIN` e Office, o gate pós-bootstrap exigirá flag false/token ausente e comprovará que o endpoint não está disponível.

O primeiro aceite produtivo exigirá `FEATURES_GLOBAL_ENABLED=false`, mutações false, contexto privilegiado false, fake clients false, kill switch SERPRO ligado, todos os drivers SERPRO não reais e canais SEFAZ/autXML desligados. A readiness da plataforma não promoverá estados de `serpro-go-live-controlado`.

### 9. Observabilidade mínima será fornecedor-neutra e comprovada por evidência

Containers usarão logs `stderr` com rotação local defensiva para evitar exaustão de disco. O aceite exigirá referências para coleta/consulta de logs, uptime HTTPS, alertas de disco/CPU/memória, falha de container, Horizon/scheduler e backup atrasado, além de on-call, escalonamento e RPO/RTO. O produto não armazenará contatos reais nem credenciais do coletor.

CI validará estrutura, redaction e cenários fail-closed. A existência e o disparo reais dos alertas serão tarefas ops-gated com evidência, não serão marcados por testes unitários.

## Risks / Trade-offs

- [O gate vira checklist que sempre passa por placeholders] → campos obrigatórios, referências não-placeholder, exit code fail-closed e testes negativos.
- [Relatório vaza segredo ou topologia] → allowlist de campos, valores booleanos/hash, modo 600 e scanner de artefatos.
- [Backup pré-deploy prolonga indisponibilidade] → executar antes de parar a aplicação quando possível, medir duração e definir janela conforme RTO.
- [Vault/private mudam durante dump] → maintenance e pausa de Horizon/scheduler no backup host canônico.
- [DNS ainda não propagou] → fase predeploy falha antes de solicitar ACME; override não silencioso somente para ensaio local, nunca para aceite.
- [SMTP ou observabilidade externa indisponível] → plataforma pode permanecer implantada, mas o aceite produtivo continua bloqueado.
- [Conta inicial ou token expostos] → janela explícita, HTTPS, fragmento, redaction e encerramento comprovado conforme a change de onboarding.
- [PLATFORM_ADMIN ganha acesso fiscal durante smoke] → comando global sem Office e drivers/flags contidos; testes de arquitetura impedem imports fiscais.
- [Health check aciona cobrança] → allowlist de checks locais/HTTP e proibição normativa de rotas fiscais externas.
- [Single-host continua sendo ponto único de falha] → backup off-site e RTO explícito; HA permanece change futura.

## Migration Plan

1. Concluir, sincronizar, arquivar e commitar as changes de onboarding e fixture do administrador demo.
2. Implementar scripts/comandos/testes desta change e commitar os artefatos de proposta antes do código.
3. Rodar CI completo e gerar release com `RELEASE_SHA` imutável.
4. Preparar host: firewall, diretórios/modos, `.env.prod`, arquivo de backup, DNS e referências operacionais; manter dev desligado.
5. Executar `make prod-readiness PHASE=predeploy`, corrigindo todos os checks obrigatórios.
6. Em instalação nova, subir com confirmação fresh e janela de onboarding; em upgrade, gerar e verificar backup pré-deploy.
7. Executar deploy, bootstrap inicial quando aplicável, desligar onboarding e repetir deploy/configuração.
8. Executar readiness pós-deploy, smoke SMTP manual, primeiro backup, replicação off-site e restore drill real.
9. Registrar aceite da plataforma contida. Promoções fiscais continuam em changes/runbooks próprios.

Rollback: manter a aplicação fechada, preservar evidências e backup pré-deploy, selecionar a tag SHA anterior, restaurar os três componentes com confirmação destrutiva e executar novamente as fases pre/postdeploy. Nunca apagar volumes, ACME ou chaves para “tentar de novo”.

## Open Questions

Nenhuma decisão de software bloqueante. Durante o apply/ops devem ser preenchidos, fora do git, o provedor de SMTP, o destino off-site, os responsáveis on-call e os valores de RPO/RTO.
