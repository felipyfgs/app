# Rollout gradual CT-e — allowlist e monitoramento de fechamento

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import` · task **16.7**  
**Atualizado:** 2026-07-15  
**Status:** plano documental — **nenhuma allowlist de produção ativada neste registro**

Pré-requisitos: smoke `cte-prod-smoke-runbook.md` + gates PASS em `cte-pilot-gates-status.md` para o stream que será liberado.

## Princípio

Liberar CT-e de forma **gradual**: flags default off → um office / poucas raízes → ampliar só após estabilidade e **ao menos um fechamento mensal** observado.  
Não pular para `ALLOW_ALL` sem métricas e aceite operacional (`cte-pilot-acceptance.md`).

## Streams com governança separada

| Stream | Flag principal | Escopo de allowlist |
|--------|----------------|---------------------|
| DistDFe cliente (5 papéis) | `SEFAZ_CTE_ENABLED` | política de elegibilidade por office/cliente (conforme deploy) |
| autXML escritório | `SEFAZ_CTE_AUTXML_DISTDFE_ENABLED` | `SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST` |
| GA autXML | `SEFAZ_CTE_AUTXML_ALLOW_ALL_OFFICES` | **somente** após coortes estáveis |
| EMITTER_PUSH | `SEFAZ_CTE_EMITTER_PUSH_ENABLED` | por necessidade de emitente; token por office |

Kill switch: `SEFAZ_CTE_AUTXML_KILL_SWITCH=true` corta o stream office sem apagar cursores/documentos.

## Coortes sugeridas

| Coorte | Quem | Streams | Duração mínima | Critério de avanço |
|--------|------|---------|----------------|--------------------|
| **C0** | staff / homolog | fixtures + opcional smoke | 3–5 dias | gates 15.x do stream alvo |
| **C1** | **1** office piloto | cliente DistDFe **ou** autXML (um de cada vez preferível) | ≥ 14 dias **e** ≥ 1 fechamento mensal parcial/completo | zero 656 não explicado; sem cross-tenant; cobertura honesta |
| **C2** | 2–5 offices | mesmos streams C1 | ≥ 14 dias + 1 fechamento | erro &lt; limiar; suporte absorvível |
| **C3** | expand | + push/import como padrão documentado | ≥ 30 dias | 2 ciclos de fechamento sem incidente P1 |
| **C4** | GA seletiva | allow_all só se produto autorizar | — | docs + on-call + aceite |

Nunca ativar autXML **e** dezenas de raízes cliente no mesmo dia sem ownership inventariado.

## Ativação C1 (exemplo)

```env
# Após PASS 15.2–15.4 (stream cliente)
SEFAZ_CTE_ENABLED=true
# restringir office/clientes conforme mecanismo de elegibilidade do deploy

# Após PASS 15.5–15.7 (stream office) — separado
SEFAZ_CTE_AUTXML_DISTDFE_ENABLED=true
SEFAZ_CTE_AUTXML_ALLOW_ALL_OFFICES=false
SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST=<office_id_piloto>
SEFAZ_CTE_AUTXML_KILL_SWITCH=false

# Push só se emitentes dependerem
# SEFAZ_CTE_EMITTER_PUSH_ENABLED=true
```

Registrar no ticket: office_id, data, streams, operador, link ao gate status.

## Monitoramento do fechamento mensal

Antes de promover coorte, revisar **pelo menos um** ciclo de fechamento contábil/mensal do piloto:

| Sinal | O que observar | Limiar sugerido |
|-------|----------------|-----------------|
| Cobertura por cliente/período | `CAPTURED_*` vs `PENDING_IMPORT` / `HISTORICAL_GAP` / `BLOCKED` | pendências explicadas e com procedimento |
| 137 / quiet | filas alcançadas sem martelo | quiet honrado |
| 656 / circuito | aberturas | **zero** não investigado |
| Decode / quarentena | taxa e idade | sem crescimento monotônico não tratado |
| Qualidade | % ORIGINAL vs AUTXML_REDACTED | redigidos com plano de original se necessário |
| Import/push | lotes falhos, tokens revogados | SLA interno de reprocesso |
| Isolamento | cross-tenant | **zero tolerância** |
| Segredos em log | amostragem | incidente P1 se SIM |
| Fila Horizon | `sync-sefaz-cte`, `sync-sefaz-cte-autxml` | sem backlog monotônico |

### Checklist de fechamento (preencher por mês/piloto)

| Campo | Valor |
|-------|--------|
| Mês de referência | |
| Office (id/slug) | |
| Streams ativos | |
| Docs CT-e capturados (contagem) | |
| Pendências abertas (contagem + tipos) | |
| Incidentes 656/593 | |
| Ações de import no mês | |
| Decisão | MANTER / AMPLIAR / REVERTER |
| Aprovador ops + produto | |

## Checklist de promoção coorte N → N+1

- [ ] Gates 15.x PASS para streams já em produção na coorte
- [ ] Duração mínima cumprida
- [ ] Fechamento mensal revisado (tabela acima)
- [ ] Nenhum P1/P2 aberto de isolamento, cursor ou credencial
- [ ] Ownership DistDFe revalidado se novos CNPJ-base entrarem
- [ ] Allowlist atualizada no ticket de release
- [ ] Plano de rollback: remover ids / `KILL_SWITCH` / flags false
- [ ] Aceite escritório atualizado se escopo mudar (`cte-pilot-acceptance.md`)

## Rollback

```env
SEFAZ_CTE_ENABLED=false
SEFAZ_CTE_AUTXML_DISTDFE_ENABLED=false
SEFAZ_CTE_AUTXML_KILL_SWITCH=true
SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST=
SEFAZ_CTE_AUTXML_ALLOW_ALL_OFFICES=false
SEFAZ_CTE_EMITTER_PUSH_ENABLED=false
```

- Cursores, documentos e quarentena **permanecem**.
- Não resetar NSU para zero para “recomeçar”.
- Reativação só com nova janela e análise de 656/ownership.

## Registro de ativações (produção)

| Data | Coorte | Office(s) | Streams | Resultado | Evidência |
|------|--------|-----------|---------|-----------|-----------|
| — | — | — | — | **nenhuma** | — |

## Ver também

- `docs/ops/cte-coverage-and-channels-runbook.md`
- `docs/ops/cte-pilot-gates-status.md`
- `docs/ops/fiscal-hub-cohort-rollout.md` (padrão de coortes da plataforma)
