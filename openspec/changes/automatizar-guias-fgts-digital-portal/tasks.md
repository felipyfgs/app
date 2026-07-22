## 1. N0 — Contratos, schema e runtime fail-closed

- [x] 1.1 Criar `config/fgts_digital.php` e `.env.example` com driver `disabled|fixture|portal_browser`, CAPTCHA `disabled|nopecha`, proxy compartilhado opcional, hosts/runtimes/endpoints allowlisted, egress/mutações OFF, orçamento/limites e kill switch; validar defaults e combinações inválidas em teste unitário.
- [x] 1.2 Criar migrations/models tenant-scoped para identidades/procurações/sessões/runs/artefatos e metadados FGTS, com blobs cifrados, índices/idempotência, casts e `hidden`; cobrir schema, escopo e serialização negativa em testes Feature.
- [x] 1.3 Definir contrato JSON versionado, DTOs, enums e exceções sanitizadas entre Laravel e RPA para readiness/auth/query/preview/emit/download e contexto privado do solver; cobrir validação, tamanho e ausência de chave/token/proxy em teste unitário PHP/Python.
- [x] 1.4 Adicionar target Horizon RPA às imagens dev/prod com versões fixadas de Chromium/Playwright e política de egress/certificado, sem novo serviço Compose; validar build/smoke e ausência de `mei`/`mei-worker`.

## 2. N1 — Processo RPA por fixtures e portal oficial

- [x] 2.1 Implementar runner Python/Playwright com stdin/stdout, timeout, diretório efêmero, limpeza garantida, allowlist e logs sanitizados; testar processo fake, falhas, limites e remoção de material.
  Depende de: 1.1, 1.3, 1.4
- [x] 2.2 Implementar state machine de login certificado, sessão, seleção de perfil/empregador, solver NopeCHA no contexto real do navegador e fallback `HUMAN_CHALLENGE_REQUIRED`, incluindo importação autorizada de sessão local; descobrir `sitekey`/URL/`rqdata`/cookies/user-agent, compartilhar proxy opcional quando configurado, validar marcador autenticado e cobrir token aceito/rejeitado, execução sem proxy, proxy inválido, cliente, procurador, procuração ausente e sessão expirada com fixtures/mocks.
  Depende de: 1.2, 1.3, 2.1
- [x] 2.3 Implementar manifesto/page objects para consulta de débitos, guias, pagamento, reimpressão e download, com detecção `PORTAL_CONTRACT_CHANGED`; cobrir todos os parsers, PDF inválido e contrato divergente por fixtures sanitizadas.
  Depende de: 2.1, 2.2
- [x] 2.4 Implementar preview e emissão rápida `MONTHLY|TERMINATION|CONSIGNMENT|MIXED`, reconciliação pré/pós-clique e resultado ambíguo; testar sucesso, `REUSED`, divergência e `RECONCILIATION_REQUIRED` por fixtures.
  Depende de: 2.2, 2.3
- [x] 2.5 Implementar guia parametrizada com filtros/seleção, fingerprint e confirmação vinculada; testar mudança de seleção/valor, autorização expirada e política fora de escopo.
  Depende de: 2.3, 2.4

## 3. N2 — Laravel, vault, jobs e API tenant

- [x] 3.1 Implementar resolução de A1 cliente/escritório, procuração, sessão cifrada, processo, locks e readiness, sem materializar segredo em bloqueios; cobrir outro office, expiração, challenge, concorrência e limpeza em Unit/Feature.
  Depende de: 1.1, 1.2, 1.3, 2.2
- [x] 3.2 Implementar consulta/persistência/dedupe de guias, débitos, pagamento e artefatos privados usando `TaxGuide` e descriptor autenticado; cobrir reconsulta, número/hash, status desconhecido, tenant e downloads em Feature.
  Depende de: 2.3, 3.1
- [x] 3.3 Implementar preview/autorização/idempotência/emissão com `FiscalMutationOperation`, reconciliação e jobs Horizon; cobrir papéis, replay, guia existente, resultado ambíguo e proibição de Pix em Unit/Feature.
  Depende de: 2.4, 2.5, 3.1, 3.2
- [x] 3.4 Adicionar scheduler opt-in e rotas/controllers Sanctum para coverage/readiness/sync/preview/authorize/emit/runs/download, com códigos HTTP estáveis; cobrir flags, kill switch, isolamento e ausência de enqueue em bloqueios.
  Depende de: 3.1, 3.2, 3.3

## 4. N3 — Painel FGTS e Central de Guias

- [x] 4.1 Evoluir tipos/client fiscal e `/monitoring/fgts` para fontes eSocial/portal, readiness, guias/pagamentos, runs, challenge e modal preview-autorização, preservando o shell; cobrir contratos, permissões e estados em Vitest.
  Depende de: 3.4
- [x] 4.2 Integrar `FGTS_DIGITAL_PORTAL` à Central de Guias, dedupe, contadores e descriptor autenticado; cobrir linha real/virtual, filtro tenant e status sem evidência em API e Vitest.
  Depende de: 3.2, 3.4

## 5. N4 — Gates e rollout controlado

- [x] 5.1 Rodar testes do contrato RPA/fixtures e gates API (`composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, `php artisan test`), corrigindo regressões sem sobrescrever mudanças locais.
  Depende de: 3.4, 4.2
- [x] 5.2 Rodar gates web (`pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity`, `pnpm run test:artifacts`) e corrigir regressões.
  Depende de: 4.1, 4.2
- [x] 5.3 Validar changes/specs OpenSpec e Compose dev/prod, secret-scan, imagem Horizon RPA e rollout `disabled -> fixture -> consultas -> emissões manuais -> políticas opt-in`; comprovar que certificados/sessões não entraram no Git.
  Depende de: 5.1, 5.2
