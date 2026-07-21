## 1. N0 — Canonicalização do digest

- [x] 1.1 Em `PagtowebPaymentListCodec`, adicionar forma canônica do número (dígitos, sem zeros à esquerda) e usá-la em `documentDigest` (e, se necessário, ao normalizar lista de entrada)
- [x] 1.2 Teste unitário: DAS `0720…` (17) e resposta `720…` (16) produzem o mesmo digest; apply marca `PAID`
  - Evidência: `php artisan test --filter=PgdasdPagtowebReconciliationTest` (6 passed)

## 2. N1 — Reapply e gates

- [x] 2.1 Reapply office-scoped da evidência PAGTOWEB já persistida (sem live SERPRO) para corrigir `NOT_FOUND` falsos; documentar comando/caminho
  - Depende de: 1.1
  - Comando: `php artisan fiscal:reapply-pgdasd-pagtoweb-evidence [--office=] [--client=]`
  - Serviço: `PgdasdPagtowebEvidenceReapplyService`
- [x] 2.2 `vendor/bin/pint --test` nos arquivos tocados + `openspec validate --changes --strict` (change `pagtoweb-digest-normaliza-numero-documento`)
  - Depende de: 1.1, 1.2, 2.1
  - Evidência: pint PASS (4 files); `openspec validate --changes --strict` ✓ change/pagtoweb-digest-normaliza-numero-documento
