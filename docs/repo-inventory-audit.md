# Levantamento de Arquivos, Pastas e Possíveis Limpezas

Data do levantamento: 2026-07-18.

## Escopo

Inventário feito sobre arquivos versionados (`git ls-files`) para evitar `vendor`, `node_modules`, caches e artefatos locais. Listas completas:

- `docs/repo-file-list.txt`: 2.317 arquivos versionados após esta limpeza.
- `docs/repo-directory-list.txt`: 279 diretórios versionados após esta limpeza.

## Totais

| Área | Arquivos | Linhas |
|---|---:|---:|
| Total versionado | 2.317 | 380.474 |
| Código provável | 2.163 | 326.069 |
| Fonte principal (`backend/app`, `frontend/app`, `frontend/server`) | 1.457 | 222.031 |
| Testes (`backend/tests`, `frontend/tests`) | 551 | 78.135 |

## Distribuição por Raiz

| Raiz | Arquivos | Linhas |
|---|---:|---:|
| `backend` | 1.835 | 279.393 |
| `frontend` | 446 | 94.974 |
| `docker` | 24 | 2.137 |
| `docs` | 3 | 2.678 |
| raiz | 7 | 1.048 |
| `.github` | 1 | 160 |
| `openspec` | 1 | 84 |

## Achados com Forte Sinal de Limpeza

1. `backend/public/favicon.ico`
   - Arquivo versionado vazio.
   - Removido nesta rodada; o favicon ativo fica em `frontend/public/favicon.ico`.

2. `frontend/app/pages/docs/[accessKey].vue` e `frontend/app/pages/docs/catalog.vue`
   - Conteúdo idêntico: `<DocsWorkspace initial-view="document" />`.
   - Não remover sem ajuste de rota: `/docs/catalog` é referenciado por navegação, middleware e testes.
   - Candidato a deduplicar com wrapper/helper ou aceitar como alias explícito.

3. `.gitignore`/placeholder duplicados em `backend/storage/**` e `backend/bootstrap/cache/.gitignore`
   - Duplicatas esperadas em projetos Laravel para manter diretórios vazios.
   - Não são lixo, mas aparecem como duplicatas por hash.

## Artefatos Locais Ignorados que Merecem Atenção

`git clean -ndX` encontrou itens ignorados que não entram no commit, mas podem ocupar espaço ou conter material sensível:

- Ambientes locais: `.env`, `.env.prod` legado, `backend/.env`.
- Dependências/build: `backend/vendor/`, `frontend/node_modules/`, `frontend/.nuxt/`, `frontend/.output/`, `frontend/dist`.
- Cache/logs: `backend/.phpunit.result.cache`, `backend/bootstrap/cache/*.php`, `backend/storage/logs/laravel.log`.
- Dados e artefatos operacionais: `backend/storage/app/backups/`, `backend/storage/app/certs/`, `dados/`.
- Material sensível de teste/local: `backend/scripts/piloto-contador.pfx`, `backend/scripts/piloto-plataforma.pfx`, certificados `test-only.*`.

Não apagar esses itens automaticamente. Validar necessidade operacional e segredos antes.

## Arquivos Grandes para Revisão

- `backend/resources/serpro/official-service-catalog.v2026-07-16.json` (~724 KB): catálogo SERPRO versionado; provável fonte oficial.
- `frontend/pnpm-lock.yaml` (~527 KB) e `backend/composer.lock` (~331 KB): lockfiles esperados.
- `backend/resources/serpro/contract-fixtures.v2026-07-16.json` (~219 KB): fixture grande; revisar se ainda necessária.
- `backend/resources/views/welcome.blade.php` (~72 KB): arquivo Laravel padrão/custom grande; verificar se é usado no produto.

## Código Legado/Deprecated

Há vários marcadores `legacy`/`@deprecated`, mas muitos têm referências ativas e testes. Exemplos com uso confirmado:

- `SvrsNfceRateLimiter`: deprecated, mas usado por `OutboundXmlRecoveryOrchestrator` e testes.
- `RecentTwoFactorGate`: deprecated em nome/semântica, mas usado em mutações fiscais e guias.
- Rotas `/docs/catalog` e `/docs/:accessKey`: redundantes no conteúdo, mas ativas por navegação profunda.

Conclusão: remover código legado exige ciclo de migração/testes, não limpeza direta.

## Próximos Passos Recomendados

1. Verificar se `backend/resources/views/welcome.blade.php` ainda é servido.
2. Investigar deduplicação das páginas `frontend/app/pages/docs/*` sem quebrar rotas.
3. Revisar fixtures SERPRO grandes e versões antigas em `backend/resources/serpro/`.
4. Fazer limpeza local segura de caches/builds com comandos específicos, nunca `git clean -fdX` sem revisar segredos.
5. Consolidar ambientes no padrão único: `.env` real ignorado + `.env.example` como único template versionado.
