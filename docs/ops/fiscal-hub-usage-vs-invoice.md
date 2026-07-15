# Comparação de consumo (ledger) × fatura/relatório SERPRO e bloqueio de escala

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.7**  
**Atualizado:** 2026-07-15  
**Status operacional:** **PENDING_OPS** até existir fatura/relatório oficial do período piloto

## Objetivo

Garantir que o **ledger imutável** da plataforma concilia com a **fatura ou relatório oficial SERPRO** antes de ampliar coortes, desligar shadow mode ou ativar bloqueio comercial.

## Papéis dos dados

| Fonte | Escopo | Mutável? |
|-------|--------|----------|
| `serpro_api_usage_*` (ledger + reservas) | Por `office_id` (+ correlação) | **Não** reescrever valores |
| Agregados mensais recompute | Global e por tenant | Derivados — podem reprocessar |
| Reconciliação (`SerproUsageReconciliation`) | Controle | Registra MATCHED/ADJUSTED + diferença; **não** altera ledger |
| Fatura SERPRO | Externa (software house) | Oficial; importar referência, não clonar PDF no git |

## Pré-condições

- [ ] Piloto ou trial com consumo real ou homologação faturável em andamento
- [ ] Ledger com entradas no período (ano/mês)
- [ ] Acesso à fatura/relatório SERPRO do **mesmo** período e ambiente contratual
- [ ] Catálogo de preços versionado alinhado ao contrato (ou classe DESCONHECIDA rastreada)
- [ ] `SERPRO_USAGE_SHADOW_MODE=true` até MATCH estável

## Procedimento mensal (ou por ciclo de piloto)

### 1. Fechar janela de consumo

1. Definir `period_year` / `period_month` (fuso e corte iguais à fatura).
2. Garantir que não há reservas abertas órfãs materialmente relevantes.
3. Recomputar agregados (comando/API platform de consolidação — ver `SerproUsageApiTest` / services de Usage).

### 2. Importar totais oficiais

Registrar na reconciliação:

- Referência oficial (`official_reference`, ex. número da fatura)
- Total oficial em unidades e/ou valor (micros)
- Observações (créditos, estornos, pacotes)

**Não** colar planilha completa com dados sensíveis no repositório.

### 3. Comparar

| Métrica | Ledger plataforma | Oficial SERPRO | Δ |
|---------|-------------------|----------------|---|
| Unidades faturáveis | | | |
| Valor estimado (se houver preço) | | | |
| Por solução (se relatório granular) | | | |
| Por tenant (só interno) | rateio | n/a na fatura global | — |

### 4. Classificar resultado

| Resultado | Critério | Ação |
|-----------|----------|------|
| **MATCHED** | Δ dentro da tolerância acordada (ex.: 0 ou &lt; 1% e &lt; valor mínimo) | Pode manter piloto; candidatar redução de shadow |
| **ADJUSTED** | Δ explicável (retry faturável, fuso, classe DESCONHECIDA, pacote) | Documentar ajuste; **não** reescrever ledger; reavaliar catálogo |
| **MATERIAL_DIVERGENCE** | Δ sem explicação ou acima do limiar | **BLOQUEAR ESCALA** |

### 5. Gate de escala (obrigatório)

**Bloquear** enquanto houver divergência material sem explicação:

- [ ] Não adicionar offices à allowlist de novos módulos  
- [ ] Não promover coorte N → N+1 (doc 16.9)  
- [ ] Não setar `SERPRO_USAGE_SHADOW_MODE=false`  
- [ ] Não setar `SERPRO_USAGE_COMMERCIAL_BLOCKING=true` baseado em números errados  
- [ ] Não comunicar “custo SERPRO repassado” a clientes  

**Desbloqueio** exige:

1. MATCHED ou ADJUSTED com post-mortem escrito  
2. Aprovação ops + financeiro/comercial da software house  
3. Atualização deste registro  

## Limiares sugeridos (ajustar com financeiro)

| Símbolo | Default sugerido |
|---------|------------------|
| Tolerância absoluta | 0 unidades **ou** valor &lt; R$ X (definir) |
| Tolerância relativa | 1% do total do mês |
| Divergência material | Acima de ambos os limiares **e** sem linha de explicação |

## Causas comuns de Δ

- Chamadas com falha **possivelmente faturáveis** (ledger registra; fatura pode agregar diferente)
- Retry sem idempotência no lado SERPRO
- Classe de preço DESCONHECIDA / catálogo defasado
- Ambiente (homologação vs produção) misturado na análise
- Fuso horário de corte do relatório
- Créditos comerciais não modelados

## Proteções de runtime (complementares)

- Shadow mode: registra, não bloqueia por franquia.
- Com blocking on (pós-gate): `FRANCHISE_EXCEEDED`, `NOISY_TENANT_SHARE` (share default 40% do global).
- Orçamento global: `SERPRO_USAGE_GLOBAL_MONTHLY_BUDGET`.
- Alertas de franquia em 80% (`SERPRO_USAGE_FRANCHISE_ALERT_THRESHOLD`).

## Registro do ciclo (template)

| Campo | Valor |
|-------|--------|
| Período | YYYY-MM |
| Ambiente contratual | |
| Official reference | |
| Ledger total (unidades / micros) | |
| Oficial total | |
| Δ | |
| Resultado | MATCHED / ADJUSTED / MATERIAL_DIVERGENCE / **PENDING_OPS** |
| Explicação | |
| Escala liberada? | NÃO / SIM |
| Aprovadores | |
| Data | |

### Ciclo atual

| Campo | Valor |
|-------|--------|
| Resultado | **PENDING_OPS** |
| Motivo | Sem fatura/relatório SERPRO do piloto neste ambiente; consumo real não iniciado |

## Referências de código

- `backend/config/serpro_usage.php`
- `App\Services\Serpro\Usage\UsageLedgerService` (shadow, imutabilidade)
- Testes: `tests/Unit/Serpro/Usage/*`, `tests/Feature/Serpro/Usage/SerproUsageApiTest.php`
