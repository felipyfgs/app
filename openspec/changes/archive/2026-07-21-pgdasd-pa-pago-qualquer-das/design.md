## Context

`CONSDECLARACAO13` persiste vários DAS por PA (`Geração de DAS` reemitida). `dasPago`/`payment_located` é por **número de DAS**, não por quitação do PA. O resolver atual e `openPaymentCompetencies` usam “qualquer `false` → unpaid”, o que marca Pendências mesmo quando já existe DAS pago no mesmo PA. SITFIS (outro serviço) pode mostrar “sem débitos RFB” enquanto a UI lista competências falsas.

Upstream estável: coluna Pagamento + popover + enrich de `amount_cents` já em main/archive.

## Goals / Non-Goals

**Goals:**

- PA quitado quando existe ≥1 DAS local com `payment_located=true`.
- Badge do PA esperado e lista `payment_open_competencies` alinhadas a essa regra.
- Testes cobrindo PA misto (pago + guias `false` remanescentes).

**Non-Goals:**

- Cruzar SITFIS/PAGTOWEB na coluna Pagamento.
- Live SERPRO no portfolio GET.
- Apagar operações DAS antigas; mudar Situação de entrega; flags ON; mei no Compose.

## Decisions

1. **Regra de quitação do PA (“any paid wins”)**  
   Para um `period_key`, se existir ao menos um DAS com `payment_located=true`, o PA é **pago** para estado operacional e NÃO entra em `payment_open_competencies`. Guias do mesmo PA com `false` são tratadas como substituídas/irrelevantes para “pendência”.  
   Alternativa rejeitada: só o DAS mais recente por `issued_at` — falha quando há reemissão pós-pagamento ainda `false` (ex.: abril com pago antigo + reemitido unpaid) enquanto RFB/SITFIS já considera quitado.  
   Alternativa rejeitada: exigir todos `true` — status quo, falsos positivos.

2. **Precedência atualizada do resolver (PA esperado)**  
   1. Sem evidência produtiva + zero DAS → `UNVERIFIED`  
   2. ≥1 `payment_located=true` → `PAID` (mesmo com irmãos `false`)  
   3. Nenhum `true` e ≥1 `false` → `UNPAID`  
   4. Só nulos → `UNVERIFIED`  
   5. Zero DAS com consulta produtiva → `NO_DAS`

3. **`openPaymentCompetencies`**  
   Agrupar por `period_key`. Excluir PA se `any(payment_located===true)`. Entre os restantes, só considerar DAS com `payment_located=false` para montante (tax_guides → operation.amount_cents → vault GERAR_DAS). Agregação fail-closed de cents inalterada **dentro** do PA ainda unpaid.

4. **Gap de amount / CONSEXTRATO**  
   Fora do MUST desta change. MAY opcionalmente pular enqueue quando o PA já tem DAS pago (otimização); não bloqueia aceite.

5. **Web**  
   Zero mudança de contrato; popover já consome a lista.

## Risks / Trade-offs

- [PA com DAS pago + novo DAS de cobrança real unpaid] → “any paid” pode ocultar cobrança adicional. Mitigação: escopo consciente; SITFIS continua a fonte de débitos apurados; evoluir depois se GERARDASCOBRANCA exigir eixo próprio.
- [Junho só com `false` e SITFIS limpo] → ainda mostra pendência no índice PGDAS — esperado até `dasPago` atualizar; não inventar pago.
- [Tenancy] → queries continuam office-scoped.

## Mapa de dependências

| Upstream | Capability | Marco | Relação |
|----------|------------|-------|---------|
| (nenhuma ativa) | — | — | — |

- Nível: **C0**
- Rollout: só código API + testes. Rollback: reverter precedência.
