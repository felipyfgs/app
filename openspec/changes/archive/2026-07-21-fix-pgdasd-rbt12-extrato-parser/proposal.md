## Why

Na carteira PGDAS-D a coluna **RBT12** mostra "—" para todos os clientes: no banco local há **0** projeções `PARSED` (só `NOT_FOUND` / `FAILED` / `NO_DAS`). O extrato oficial do DAS (SERPRO `CONSEXTRATO16`) tipicamente coloca o rótulo RBT12 no cabeçalho e os valores (mercado interno / externo / total) nas linhas seguintes — o parser atual só aceita valor na **mesma** linha que contém `RBT12`, gerando `EXACT_RBT12_VALUE_NOT_FOUND`.

## What Changes

- Evoluir `PgdasdRbt12Parser` (v3) para o layout oficial do extrato: valores na linha da “receita bruta acumulada nos doze meses…” imediatamente antes de `(RBT12)`, ignorando RBT12p/RBA.
- Reprocessar projeções `NOT_FOUND` existentes a partir dos PDFs no cofre (dev/local) para popular a coluna sem nova consulta SERPRO.
- Seleção na carteira: preferir RBT12 `PARSED` do PA exibido (não ficar preso a ponteiro `FAILED`).
- Tooltip: total + composição + RPA quando presente; copy honesta (RBT12 ≠ RPA ≠ sublimite).

## Capabilities

### New Capabilities

- `pgdasd-rbt12-extrato-parse`: contrato de parse fail-closed do RBT12 (e composição) a partir do texto do extrato DAS PGDAS-D.

### Modified Capabilities

- (nenhuma em main specs)

## Impact

- API: `PgdasdRbt12Parser`, possivelmente `PgdasdRbt12Service` / `toPublicArray` (metadata RPA).
- Web: `pgdasdRbt12Tooltip` / `Rbt12Value.vue` (copy e detalhe).
- Dados existentes `NOT_FOUND` não se auto-corrigem sem reprocessar extrato (vault/consulta); novos parses usam a versão nova.
- Non-goals: inventar sublimite; chamar SERPRO na listagem; ligar flags; mei no Compose.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: pipeline CONSEXTRATO16 + `PdfTextExtractor` existentes
- Depende de: nenhuma
- Capability/contrato: `pgdasd-rbt12-extrato-parse`
- Desbloqueia: coluna RBT12 com valores reais após nova consulta/reparse
- Paralelismo: ok fora de arquivos do parser RBT12
