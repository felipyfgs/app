## Context

`PgdasdRbt12Parser` v1 exige `\bRBT12\b` na mesma linha do valor. Extratos PGDAS-D oficiais (pdftotext `-layout`) costumam ser:

```
… (RBT12)
Mercado Interno    Mercado Externo    Total
10.000,00          0,00               10.000,00
```

Isso produz `EXACT_RBT12_VALUE_NOT_FOUND`. A coluna web só mostra valor se `status === 'PARSED'`.

## Goals / Non-Goals

**Goals:**

- Parsear layout tabular multi-linha e linha única; bump `VERSION` (`pgdasd-rbt12-v2`).
- Tooltip com RBT12 + mercado interno/externo; copy honesta (RBT12 ≠ RPA ≠ sublimite).
- Se RPA estiver etiquetada no texto, expor no tooltip sem virar o chip principal.

**Non-Goals:**

- Reprocessamento em massa de vault (chave/AAD pode falhar localmente).
- Extrair alíquota/anexo/fator R nesta change.
- Mudar quando a carteira dispara CONSEXTRATO.

## Decisions

1. **Layout oficial CONSEXTRATO16 (v3)** — valores na linha “Receita bruta acumulada nos doze meses anteriores ao PA”, marcador `(RBT12)` na linha seguinte; ignorar RBT12p/RBA/RBAA.
2. **Fail-closed** — múltiplos totais conflitantes → `AMBIGUOUS`; zero total inequívoco → `NOT_FOUND` (exceto total monetário zero explícito).
3. **RPA** — linha “Receita Bruta do PA (RPA)”; gravar em `metadata.rpa_cents` / public `rpa_cents` só para tooltip.
4. **Carteira** — preferir `PARSED` do PA exibido; não preferir ponteiro `FAILED`.
5. **UI** — humanizar `unavailable_reason`; chip só com total RBT12.
## Risks / Trade-offs

- [Layout PDF variar por ano] → Mitigação: fixtures + fail-closed.
- [Confundir RPA com RBT12] → Mitigação: chip só RBT12; tooltip distingue.
- [Dados antigos NOT_FOUND] → Mitigação: documentar necessidade de nova consulta/reparse.

## Migration Plan

Deploy API+web. Clientes só passam a ter valor após novo extrato parseado com v2. Rollback = reverter parser.
