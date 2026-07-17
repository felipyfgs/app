## Why

O repositório já possui uma stack produtiva segura, mas o go-live ainda depende de verificações dispersas e de decisões operacionais não comprovadas: não há `.env.prod` real, HTTPS ativo, release imutável publicada, backup externo validado, observabilidade/on-call aceitos nem evidência única de readiness. Precisamos transformar o caminho existente em um gate reproduzível que permita publicar a plataforma com integrações fiscais contidas sem confundir deploy técnico com promoção SERPRO.

## What Changes

- Criar um gate único de readiness pré e pós-deploy que valide release identificável, configuração produtiva, portas/DNS/TLS, serviços, migrations, Horizon/scheduler, SMTP, backup e invariantes de contenção.
- Completar o contrato de `.env.prod` e do Compose para exigir segredos fortes, backup cifrado persistente, destino replicável fora da VPS e referências operacionais sanitizadas, sem imprimir valores sensíveis.
- Tornar o deploy responsável por produzir evidência sanitizada da versão, executar backup pré-migration quando houver estado persistente e manter a aplicação fechada se migration, health ou smoke falhar.
- Padronizar bootstrap e encerramento do onboarding inicial: habilitação temporária somente em base vazia, uso por HTTPS e verificação posterior de que token/flag foram removidos.
- Adicionar smoke pós-deploy não faturável para HTTPS, headers, superfícies bloqueadas, API, filas e e-mail, sem acessar rotas fiscais externas.
- Definir runbook e evidência de aceite para firewall, logs/alertas, on-call, RPO/RTO, backup off-site e restore drill real.
- Manter todos os canais fiscais, drivers SERPRO reais, mutações e contexto privilegiado desligados no primeiro go-live.
- Non-goals: habilitar flags fiscais ou canais SEFAZ; executar live smoke, canário ou chamada faturável SERPRO; resolver tickets SERPRO; produzir parecer jurídico/LGPD; promover `PRODUCTION_READY` do Integra Contador; criar alta disponibilidade multi-host.

## Capabilities

### New Capabilities

- `go-live-plataforma-producao`: Gate técnico e operacional para publicar, verificar e aceitar a plataforma em produção contida, com release rastreável, bootstrap seguro, backup recuperável e observabilidade mínima.

### Modified Capabilities

Nenhuma. O onboarding inicial continua definido pela change ativa `onboarding-inicial-plataforma`, e a promoção SERPRO permanece regida por `serpro-go-live-controlado`.

## Impact

- **Orquestração:** `Makefile`, `compose.prod.yml` e scripts em `docker/ops/` para readiness, deploy, backup e smoke.
- **Backend:** comandos/read models operacionais para readiness sanitizado, backup/restore, filas e estado de bootstrap; nenhuma nova autorização tenant ou chamada fiscal.
- **Configuração:** `.env.prod.example` passa a declarar todos os controles obrigatórios, mantendo `.env.prod`, chaves e contatos reais fora do git.
- **Operação:** novos runbook e template de evidência em `docs/ops/`, com referências opacas em vez de segredos ou identidades reais.
- **CI:** testes dos gates fail-closed, sintaxe dos scripts, Compose produtivo, imagens imutáveis e restore smoke.
- **Dependências:** requer concluir e arquivar `onboarding-inicial-plataforma` e `provisionar-admin-inicial-plataforma` antes do aceite final desta change.
