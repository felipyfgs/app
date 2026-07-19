## Why

A revisão do stack confirmou que Nuxt, Laravel/Horizon e o sidecar MEI (FastAPI/Celery/Playwright) têm papéis corretos, mas as fronteiras ainda não estão totalmente amarradas no contrato: risco de Redis competir com Postgres como SoT, payload sem allowlist, loop de artefato→vault incompleto e ambiguidade sobre unificar filas. Precisamos formalizar as decisões e ajustar o código/ops para que cada tecnologia fique com responsabilidade explícita e verificável.

## What Changes

- Formaliza o contrato de fronteiras: Nuxt = UI; Laravel = auth/tenancy/SERPRO/vault/SoT; MEI = executor efêmero de browser; Horizon = filas de domínio; Celery = só jobs de portal.
- Exige Postgres (`mei_automation_attempts`) como única fonte de verdade durável; Redis do MEI permanece store efêmero com TTL e sync obrigatório antes da expiração.
- Adiciona allowlist/redaction do `input` no Laravel antes do POST HMAC ao sidecar (sem CNPJ/PII em campos públicos, logs ou metadata do MEI).
- Fecha o loop operacional de fronteira: download autenticado de artefato → digest/validação → vault (`SecureObjectStore` / evidência fiscal); Redis nunca guarda prova de negócio.
- Documenta e reforça proibições: SPA não chama MEI; MEI sem Sanctum/tenancy/vault/ledger; Playwright fora do Horizon; FastAPI não é API de produto.
- Não troca stack (não move núcleo fiscal para Python/Node; não embute browser no PHP; não unifica Celery em Horizon).
- Não habilita portal live, captcha pago, mutações fiscais nem altera cobrança comercial.

## Capabilities

### New Capabilities
- `mei-stack-boundaries`: Contratos de responsabilidade e regras de fronteira entre Nuxt, Laravel/Horizon/Postgres/vault e o sidecar MEI (FastAPI/Celery/Playwright/Redis), incluindo SoT, filas, redact, sync de artefatos e proibições de stack.

### Modified Capabilities

Nenhuma capability principal versionada em `openspec/specs/` — o inventário main está vazio. O orquestrador em si permanece ownership da change `adicionar-orquestrador-portal-mei`; esta change só aperta fronteiras sem redefinir o ciclo de jobs do portal.

## Impact

- `apps/api`: client/orquestração MEI, redact/allowlist de payload, sync de tentativa antes do TTL, ingestão de artefato no vault.
- `services/mei`: permanece sem DB de negócio; reforço de que estado Redis é efêmero e a API HTTP é só contrato interno.
- `apps/web`: nenhum cliente MEI; sem mudança de UX além do que já passa pela API fiscal.
- Compose/ops: confirma rede interna, sem port publish do MEI; documenta Redis DB `/4` vs Horizon `/0`/`1`.
- Docs/OpenSpec: fronteiras explícitas para evitar regressão de ownership.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: Sanctum/Fortify, Horizon/Redis, `SecureObjectStore`, tenancy `Office`/`CurrentOffice`, adapter fiscal MEI via SERPRO.
- Depende de: `adicionar-orquestrador-portal-mei`.
- Capability/contrato: consome o contrato de jobs HMAC/`mei_automation_attempts` dessa change; cria `mei-stack-boundaries`.
- Marco exigido: `specs` (coordenada) — pode detalhar fronteiras em paralelo após as specs do orquestrador; implementação de sync/vault/redact exige o client e a persistência já especificados (`apply` da upstream quando as tasks de código forem tocadas).
- Relação: `coordenada` nos artefatos; `bloqueante` no apply das tasks que alteram client/sync/vault.
- Desbloqueia: hardening seguro do provider portal nas changes consumidoras (`automatizar-servicos-publicos-mei` e seguintes), sem ambiguidade de SoT/filas.
- Paralelismo: redação de specs de fronteira pode avançar junto com a upstream após `specs`; Compose do MEI permanece ownership da upstream (task 3.2); esta change não duplica Dockerfile/Compose, só valida a fronteira de rede/exposição.
