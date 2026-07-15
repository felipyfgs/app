# Plano de liberação por coortes — módulos somente leitura

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.9**  
**Atualizado:** 2026-07-15

## Princípio

Liberar **somente leitura** de forma gradual: um office → poucos offices → coortes maiores. Mutações e guias assistidas **não** entram neste plano (gate 16.10 separado). Escala bloqueada se 16.7 estiver em divergência material.

## Pré-requisitos globais (antes da coorte 0→1)

- [ ] 16.1 suite relevante PASS  
- [ ] 16.4 trial mock+shadow PASS  
- [ ] 16.5 aceite piloto assinado  
- [ ] 16.6 smoke RO PASS (ou homologação explícita)  
- [ ] 16.7 sem MATERIAL_DIVERGENCE aberta  
- [ ] 16.8 drills críticos D2/D3/D11 PASS  
- [ ] Evidência comercial compatível com o ambiente  
- [ ] Shadow mode ON  
- [ ] Mutações OFF  

## Mecanismo técnico de liberação

Flags por módulo (`config/features.php`):

```env
FEATURES_GLOBAL_ENABLED=true
FEATURES_KILL_SWITCH=false
FEATURES_MUTATING_ENABLED=false

FEATURE_SITFIS_ENABLED=true
FEATURE_SITFIS_MUTATING_ENABLED=false
FEATURE_SITFIS_OFFICE_ALLOWLIST=12
# FEATURE_SITFIS_ALLOW_ALL_OFFICES=false
```

Regras:

- Allowlist **vazia** + `allow_all_offices=false` ⇒ ninguém.  
- Preferir allowlist explícita até coorte estável.  
- `FEATURES_KILL_SWITCH` vence qualquer enable.  
- Rate limits: `SERPRO_RATE_LIMIT_*`, `FISCAL_MONITORING_*_RPS`, concorrência global/tenant.

## Coortes

| Coorte | Offices | Contribuintes/office | Módulos | Duração mínima | Critério de avanço |
|--------|---------|----------------------|---------|----------------|--------------------|
| **C0** Shadow interno | 0–1 (staff) | fixtures | 1 módulo RO | 3–5 dias | Estabilidade mock + métricas |
| **C1** Piloto | **1** | 1–5 | 1–2 módulos RO | ≥ 1 ciclo de fatura ou 14 dias | 16.5–16.7 OK |
| **C2** Early | 2–5 | ≤ 20 cada | módulos C1 + 1 novo | ≥ 14 dias | Erro &lt; limiar; sem cross-tenant; custo previsível |
| **C3** Expand | 10–30 | plano | RO consolidados | ≥ 30 dias | Conciliação 2 ciclos; suporte absorvível |
| **C4** GA RO | allow_all ou catálogo comercial | plano | RO GA | — | Docs 16.11 + on-call |

Nunca pular C1 se for a primeira carga **faturável** real.

## Ordem sugerida de módulos RO

1. Sitfis / situação (alto valor, risco mutante baixo)  
2. Caixa postal (sensível — treinar suporte antes)  
3. Simples/MEI consultas  
4. DCTFWeb/MIT consultas  
5. Parcelamentos consultas  
6. Declarações  
7. Guias **consulta** (emissão = 16.10)  
8. FGTS/eSocial parcial (sempre rotular cobertura parcial)

Um módulo novo por vez na mesma coorte, salvo aceite explícito.

## Observabilidade por coorte

| Sinal | Alerta sugerido |
|-------|-----------------|
| Taxa de erro 5xx / elegibilidade | &gt; 5% sustentado 1h |
| Latência p95 chamada Integra | &gt; SLA interno (definir) |
| Fila Horizon `fiscal` / default | crescimento monotônico |
| Breaker open | qualquer open em produção |
| Franquia office &gt; 80% | alerta; revisar padrão de polling |
| Share tenant no global | próximo de `max_tenant_share` |
| Achados de isolamento | **zero tolerância** — kill + incidente |
| Vazamento em log | incidente P1 |

Comandos úteis:

```bash
docker compose exec -T php php artisan ops:preflight-tenant-isolation --fail-on-issues
docker compose exec -T php php artisan fiscal:dispatch-due-monitoring
# health platform serpro + painel de consumo tenant
```

## Checklist de promoção coorte N → N+1

- [ ] Métricas da duração mínima revisadas  
- [ ] Nenhum incidente P1/P2 aberto de isolamento ou credencial  
- [ ] Conciliação 16.7 MATCHED ou ADJUSTED explicado  
- [ ] Suporte treinado no módulo (16.11)  
- [ ] Allowlist atualizada e documentada no ticket de release  
- [ ] Plano de rollback: remover ids da allowlist / kill switch  
- [ ] Aprovação ops + produto  

## Rollback de coorte

1. Remover offices da allowlist **ou** `FEATURES_KILL_SWITCH=true` se multi-módulo.  
2. Drenar filas sem apagar snapshots.  
3. Comunicar escritórios (indisponibilidade de sync, não detalhes de cert).  
4. Preservar evidências para forense.  
5. Post-mortem antes de reentrar.

## Registro de coortes (template)

| Coorte | Início | Offices (ids) | Módulos | Resultado | Avanço? |
|--------|--------|---------------|---------|-----------|---------|
| C0 | | | | | |
| C1 | | | | | |
| C2 | | | | | |
