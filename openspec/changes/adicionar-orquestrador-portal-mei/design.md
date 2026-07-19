## Context

O núcleo fiscal em `apps/api` executa `FiscalSourceAdapter` em jobs Horizon e o adapter `SimplesMeiAdapter` chama diretamente o SERPRO. A nova fonte em `services/mei` usa navegador e pode levar minutos, exigir captcha ou terminar sem certeza após uma submissão; ela não pode bloquear o worker Laravel nem se tornar dona de dados fiscais ou credenciais de tenant. O Nuxt permanece em `apps/web`.

## Goals / Non-Goals

**Goals:**

- Criar um executor Python assíncrono, interno e sem estado de negócio.
- Autenticar Laravel e Python com HMAC resistente a replay.
- Persistir no Laravel cada tentativa e sua proveniência sem armazenar HTML ou PII em logs.
- Selecionar provider por operação com fallback classificado e compatibilidade total quando a feature estiver OFF.

**Non-Goals:**

- Automatizar portais live, resolver captcha, abrir sessão Gov.br ou executar mutações nesta change.
- Substituir Horizon, Redis, `SecureObjectStore`, autorização ou ledger comercial do Laravel.
- Copiar código de repositórios externos ou emitir parecer jurídico sobre automação dos portais.

## Decisions

1. **FastAPI + Celery + Redis.** A API valida e enfileira; o worker executa. Celery oferece estado durável e cancelamento sem manter requests HTTP longos. Laravel faz polling e continua como fonte de verdade.
2. **Playwright Chromium com contexto por job.** O processo do browser pode ser reaproveitado no worker, mas cookies, storage e downloads pertencem a um `BrowserContext` não persistente, fechado em `finally`.
3. **Contrato HTTP HMAC.** A assinatura canônica é `METHOD\nPATH\nSHA256(body)\nTIMESTAMP\nNONCE`; timestamp aceita 60 segundos e nonce fica bloqueado por 300 segundos no Redis.
4. **Estado efêmero no Python.** Job/resultados expiram no Redis; artefatos têm download autenticado e TTL curto. Laravel calcula digest, valida tipo/tamanho e move o conteúdo para o vault.
5. **Router dentro de `INTEGRA_MEI`.** O registro fiscal continua resolvendo um adapter por coordenada. O adapter MEI delega a providers ordenados por política, mantendo o transporte SERPRO atual como fallback.
6. **Fallback por taxonomia.** Apenas `PORTAL_UNAVAILABLE`, `PORTAL_DRIFT`, `CAPTCHA_EXHAUSTED` e `PORTAL_CNPJ_FORMAT_UNSUPPORTED`, antes de submissão, permitem próximo provider. Erro de negócio e resultado incerto encerram a cadeia.
7. **Proveniência explícita.** `RECEITA_PORTAL` é origem live oficial por artefato, distinta de `SERPRO_REAL`; `verification_kind` informa se a prova veio de API ou portal.

## Risks / Trade-offs

- [Redis indisponível perde estado efêmero] → Laravel preserva tentativa/idempotência e pode reenfileirar somente jobs não submetidos.
- [Browser consome memória] → concorrência inicial 1, limites por job e reinício do worker após quantidade configurável.
- [Contrato interno exposto por engano] → nenhum port mapping, rede interna e HMAC obrigatório inclusive em desenvolvimento fora de `testing`.
- [Mudança do portal quebra parser] → erro `PORTAL_DRIFT`, sem fallback cego após submissão, versão do parser registrada.
- [Nova proveniência altera projeções] → esta change só cria o enum/metadata; cada operação futura decide explicitamente quando pode projetar.

## Migration Plan

1. Publicar migração e código Laravel com `MEI_AUTOMATION_ENABLED=false`.
2. Subir API/worker e validar health/HMAC com job fixture.
3. Habilitar somente ambiente local/testing, sem egress live.
4. Rollback: desligar a flag e remover os containers; runs existentes seguem usando SERPRO e tentativas permanecem auditáveis.

## Mapa de dependências

`adicionar-orquestrador-portal-mei (C0)` → `automatizar-servicos-publicos-mei (C1, verify)` → `habilitar-operacoes-assistidas-e-mutantes-mei (C2, verify)`. Python e persistência Laravel têm ownership separado e podem avançar em paralelo; o provider router só é registrado após ambos cumprirem testes de contrato.

## Open Questions

Nenhuma decisão bloqueante. Provider portal e captcha permanecem desligados até as changes consumidoras.
