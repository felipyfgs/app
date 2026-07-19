# Evidências de verificação

Change: `alinhar-fronteiras-responsabilidades-stack`  
Data: 2026-07-18

## Gates

| Gate | Resultado |
|---|---|
| `openspec validate alinhar-fronteiras-responsabilidades-stack --strict` | PASS |
| Testes Laravel MEI (Unit + Feature) | PASS, 52 asserções, sem falhas |
| Pint nos arquivos Laravel afetados | PASS, 20 arquivos |
| Vitest da fronteira Nuxt→MEI | PASS, 1 teste |
| ESLint do teste arquitetural | PASS |
| `git diff --check` | PASS |
| `make check-mei-compose-boundary` (`node scripts/check-mei-compose-boundary.mjs`) | SKIP controlado: containers upstream ainda ausentes nos dois Compose |

Os warnings PHPUnit observados são causados pela permissão preexistente do
container ao tentar ler `apps/api/.env`; não houve falha funcional nem conteúdo
de ambiente registrado nesta evidência.

Quando a change upstream adicionar API/worker MEI ao Compose, o script de
fronteira descobrirá os serviços pelo nome, imagem ou build context e falhará
caso qualquer um publique `ports` no host.
