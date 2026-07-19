# Evidencia de verificacao: automatizar-servicos-publicos-mei

Data: 2026-07-19

## Resultado

- OpenSpec estrito: valido.
- Python 3.12: 39 testes aprovados; Ruff/format aprovados; mypy sem erros em 30 arquivos.
- Laravel: 55 testes aprovados com 242 assercoes; Pint aprovado em 1.415 arquivos.
- Nuxt: gate global aprovado (ESLint, typecheck e 56 testes em 13 arquivos).
- Nuxt generate: concluido em output isolado, com 61 rotas e PWA. O `.output` padrao possui artefato de outro UID e nao foi alterado.
- Compose local/producao: configuracoes validas; `mei` e `mei-worker` somente em rede interna, saudaveis e worker Celery com `pong`.
- Smoke Docker PGMEI: a imagem reconstruida percorreu identificacao, menu, ano, competencia, data de pagamento, atualizacao de valores e emissao uma vez (`updateCount=1`, `emissionCount=1`), publicando um artefato validado.
- Probe oficial read-only: PGMEI e DASN abriram os formularios atuais e terminaram `CAPTCHA_EXHAUSTED`, `submitted=false`, sem resolver captcha ou submeter formulario.

## Correcao validada nesta rodada

O roteiro Puppeteer fornecido revelou que o handler live pulava a data de pagamento e o checkpoint `Atualizar Valores`. O contrato agora:

- aceita e valida `due_date` antes do browser;
- inclui a data no preflight, fingerprint/idempotencia e job enviado ao sidecar;
- aguarda `#dataPagamentoInformada`, `#btnAtualizarValores` e rede ociosa antes de habilitar a unica submissao;
- preserva `UNCERTAIN` para qualquer falha posterior ao clique `Apurar/Gerar`.

## Comandos executados

```text
openspec validate automatizar-servicos-publicos-mei --strict
pytest
ruff check .
ruff format --check .
mypy src tests
php artisan test --do-not-cache-result
vendor/bin/pint --test
pnpm run test:gate
pnpm exec eslint <arquivos MEI>
pnpm exec vitest run tests/unit/mei-public-services.test.ts
NITRO_OUTPUT_DIR=/tmp/nuxt-mei-gate-output pnpm run generate
docker compose config --quiet
docker compose -f compose.prod.yml config --quiet
node scripts/check-mei-compose-boundary.mjs
docker compose build mei
docker run --rm --user mei --entrypoint python app-mei:dev <smoke PGMEI sanitizado>
```

Nenhuma evidencia contem CNPJ real, sitekey, token de captcha, chave NoPeCHA, Gov.br ou conteudo fiscal. Egress live, NoPeCHA e allowlists permaneceram desligados; o identificador usado nos probes foi sintetico e nao houve POST ao portal.
