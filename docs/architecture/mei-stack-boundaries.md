# Fronteiras do stack MEI

## Responsabilidades

| Camada | Responsabilidade | Proibido |
|---|---|---|
| `apps/web` (Nuxt) | UI e chamadas autenticadas para a API Laravel | Chamar o sidecar MEI ou baixar artefatos dele |
| `apps/api` (Laravel) | Sanctum, tenancy, autorização, SERPRO, auditoria, idempotência, Postgres e vault | Executar Playwright ou tratar Redis MEI como fonte de verdade |
| Horizon | Jobs de domínio fiscal, SERPRO, SEFAZ, sync e ingestão no vault | Executar browser ou subprocesso Playwright |
| `services/mei` | FastAPI interna, Celery e browser efêmero por job | Sanctum, tenancy, credenciais de banco/vault, ledger e projeções fiscais |
| Celery MEI | Somente execução de browser do sidecar | Jobs de domínio Laravel |

O fluxo permitido é `Nuxt -> Laravel/Horizon -> FastAPI/Celery MEI`. O sidecar
não é API de produto e não pode ter `ports` publicado no Compose local ou de
produção. O Nuxt nunca conhece `MEI_AUTOMATION_URL` nem a chave HMAC.

## Estado e Redis

Postgres, especialmente `mei_automation_attempts`, é a única fonte durável do
ciclo de automação. Redis armazena somente transporte efêmero:

- Laravel usa DB `/0` e `/1` para cache, sessão e Horizon, conforme a configuração da aplicação.
- O sidecar usa `redis://redis:6379/4` para jobs, resultados, idempotência e anti-replay.
- O poll Laravel deve ser menor que `MEI_AUTOMATION_RESULT_TTL_SECONDS`.
- Perder `mei:job:*` não apaga a tentativa: Laravel registra `SYNC_LOST` e só pode reenfileirar quando não houver indicação de submissão.

Compartilhar o host Redis não transfere ownership. Prefixos, DB lógico, TTL e
métricas permanecem separados; um Redis dedicado pode ser adotado depois sem
alterar o contrato.

## Inventário e gaps

Inventário realizado em 2026-07-19 para a fronteira Laravel/MEI:

| Ponto | Estado encontrado | Gap tratado por `alinhar-fronteiras-responsabilidades-stack` |
|---|---|---|
| `MeiAutomationClient` | POST/get/cancel/resume e download HMAC | Sanitizar `input`, classificar `404` no poll e ingerir artefato no vault |
| `MeiAutomationHmacSigner` | Assinatura canônica implementada | Garantir que a allowlist seja aplicada antes da serialização e assinatura |
| `MeiAutomationAttempt` | Tenant-scoped, idempotência e metadata pública reduzida | Persistir último sync, submissão, perda de sync e referências de artefatos no vault |
| `MeiAutomationAttemptService` | Fingerprint e `client_ref` opaco | Canonicalizar somente o payload permitido por operação |
| Laravel config | Poll de 10 segundos | Declarar TTL contratual e falhar quando `poll >= TTL` |
| Sidecar config | Result TTL 900s, artifact TTL 300s, Redis `/4` | Nenhum; valores devem ser espelhados no contrato Laravel/ops |
| Compose | Containers MEI ainda pertencem à change upstream | Validar ausência de port publish quando a upstream os adicionar |
| Vault | `SecureObjectStore` disponível | Validar MIME, tamanho e SHA-256 antes do `put`; nunca reconstruir artefato expirado |
| Nuxt | Usa a API Laravel | Proteger a fronteira com teste arquitetural estático |

## Artefatos

Somente o Laravel move um artefato para a fonte durável:

1. consulta o job e persiste o status da tentativa;
2. baixa o arquivo com HMAC;
3. valida ID, MIME permitido, tamanho máximo e SHA-256 informado pelo descriptor;
4. grava bytes no `SecureObjectStore` com AAD tenant-scoped;
5. persiste apenas o identificador opaco do vault e metadados sanitizados;
6. em `404`/`410`, registra falha de ingestão sem fabricar conteúdo.

Logs, respostas públicas e `safe_metadata` nunca recebem CNPJ completo, HTML,
captcha, senha, token, bytes fiscais ou caminhos internos.
