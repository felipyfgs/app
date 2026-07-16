# Guardrails de domínio (hub fiscal)

Injetar este bloco em **todo** planner, implementer e validator.

## Produto

- SaaS multi-escritório para **escritórios contábeis** (tenants), **não** portal de contribuinte final.
- Stack: monorepo `backend/` Laravel 13 / PHP 8.4, `frontend/` Nuxt 4 / Nuxt UI 4 SPA, Nginx same-origin (sem CORS em prod).
- Auth tenant: Fortify + Sanctum cookie/session, CSRF, TOTP; papéis `ADMIN` / `OPERATOR` / `VIEWER`.
- Plataforma: `PLATFORM_ADMIN` separado — **não** herda conteúdo fiscal de tenants.
- Tenancy: dados de tenant com `office_id` obrigatório; **nunca** confiar em `office_id` do client.
- SERPRO: contrato **global** da software house; tenants **não** recebem credenciais SERPRO.
- Data: PostgreSQL truth; Redis/Horizon; Scheduler (não DB queues).

## Segredos (proibido expor)

Nunca em API, logs, export, resposta de agent, commit ou artefato de run:

- PFX/P12, senha de certificado, chave privada, PEM
- Consumer Secret / tokens SERPRO
- Termo assinado / XML de Termo
- `VAULT_MASTER_KEY` e material de envelope crypto

Sem rota de “recuperar certificado”.

## Non-goals (bloquear se o pedido for isso)

- Portal/login de contribuinte final
- Scraping, CAPTCHA, Gov.br, cookies de navegador
- Cobertura integral FGTS Digital (só parcial via eSocial se aplicável)
- Gateway de pagamento / NF da assinatura no MVP
- Cloud KMS; sublicenciar credenciais SERPRO/PFX a tenants
- Assumir autorização comercial SERPRO SaaS sem evidência formal

## Domínio fiscal (quando o goal tocar captura)

- Um e-CNPJ A1 por raiz de cliente; CNPJ 14 chars texto uppercase unmasked
- ADN por NSU (não por data); não avançar NSU em falha de decode; bloquear após 5 falhas consecutivas
- Job: max 20 páginas e requeue; locks por estabelecimento; rate limit global
- XML original imutável (SHA-256); `dfe_documents` imutáveis
- Mesmo CNPJ em dois escritórios: queries/jobs **nunca** misturam tenants

## UI

- Se o goal tocar `frontend/`: seguir skills `panel-ui` + `ui-archetype` + Nuxt UI — não inventar shell.

## Idioma

- Comunicação e artefatos OpenSpec: **pt-BR**.
- Código: seguir convenções do repo (PHP/TS existentes).
