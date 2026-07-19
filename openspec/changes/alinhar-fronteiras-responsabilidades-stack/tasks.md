## 1. N0 — Inventário e contratos de fronteira

- [x] 1.1 Mapear pontos Laravel↔MEI existentes (`MeiAutomationClient`, attempts, config TTL/poll) e registrar gaps vs `mei-stack-boundaries` sem alterar a change `adicionar-orquestrador-portal-mei`.
- [x] 1.2 Documentar no README/ops do MEI (ou doc interno apontado pelo design) os papéis Horizon vs Celery e Redis DB `/0`/`/1` vs `/4`, com proibição de port publish.
- [x] 1.3 Adicionar teste estático/arquitetural no web garantindo ausência de cliente HTTP para o host/URL do sidecar MEI.
  Depende de: nenhuma interna; coordenada com `adicionar-orquestrador-portal-mei` @ `specs`

## 2. N1 — Allowlist, redact e SoT

- [x] 2.1 Implementar allowlist de `input` por operação + redaction antes do POST HMAC, com testes unitários de rejeição/remoção de campos e ausência de CNPJ/PII em metadata pública.
  Depende de: 1.1
  Externa: `adicionar-orquestrador-portal-mei` @ `apply` (client/attempts)
- [x] 2.2 Implementar sincronização de status da tentativa em Postgres com intervalo de poll menor que o TTL Redis, incluindo estado de perda de sync quando o job efêmero sumir sem submissão.
  Depende de: 1.1
  Externa: `adicionar-orquestrador-portal-mei` @ `apply`
- [x] 2.3 Cobrir 2.1 e 2.2 com testes Feature/Unit Laravel (idempotência da tentativa, redact, `SYNC_LOST` ou equivalente).
  Depende de: 2.1, 2.2

## 3. N2 — Artefato → vault

- [x] 3.1 Implementar download HMAC do artefato, validação de tipo/tamanho/digest e gravação no vault/`SecureObjectStore`, atualizando a tentativa.
  Depende de: 2.2
  Externa: `adicionar-orquestrador-portal-mei` @ `apply` (endpoint de artifacts)
- [x] 3.2 Testes de ingestão bem-sucedida e de artefato expirado (falha de ingestão sem inventar conteúdo).
  Depende de: 3.1

## 4. N3 — Gates integrados

- [x] 4.1 Rodar validação OpenSpec da change, testes Laravel relevantes e checagem de fronteira de rede (MEI sem port publish na config Compose da upstream, quando disponível); registrar evidências sem segredos.
  Depende de: 1.2, 1.3, 2.3, 3.2
