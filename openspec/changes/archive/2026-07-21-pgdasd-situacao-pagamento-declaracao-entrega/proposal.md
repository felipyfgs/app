## Why

A carteira PGDAS-D duplica o eixo de entrega (Situação texto + Últ. Declaração colorida) e mantém Pagamento à parte, gerando grade densa e colisão de rótulos “Em dia/Pendências”. O operador lê melhor com Situação = pagamento DAS e entrega só na competência colorida.

## What Changes

- **BREAKING (UI PGDAS-D):** coluna Situação passa a exibir estado de pagamento DAS (Em dia / Pendências / Sem DAS), não mais entrega do PA.
- Entrega do PA fica só na coluna Declaração (MM/YYYY colorido); header `Últ. Declaração` → `Declaração`.
- **BREAKING (UI):** coluna Pagamento removida; badge + popover de competências em aberto migram para Situação.
- UI nunca exibe flags de máquina nem o rótulo “Não verificado”; sem evidência → Sem procuração ou `—` / pendente de consulta.
- KPIs do topo permanecem derivados da entrega (`row.situation`); tooltip do header Situação esclarece que a coluna é pagamento.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-das-payment-column`: pagamento exibido na coluna Situação; coluna Pagamento removida da spine.
- `pgdasd-pa-delivery-situation`: entrega visível na Declaração colorida, não na badge Situação.
- `monitoring-portfolio-columns`: ordem PGDAS-D sem coluna Pagamento; header Declaração.
- `pgdasd-payment-popover-cliente`: popover de pagamento abre a partir da coluna Situação (não mais Pagamento).

Exceção transversal (4 capabilities): uma redesign de grade PGDAS-D toca contrato de coluna, entrega, spine e popover no mesmo resultado implantável — dividir quebraria a coesão UI.

## Impact

- Web: `pgdasd-table.ts`, `DeclarationIndicator.vue`, `PaymentValue.vue`, testes de ordem/labels.
- Specs: deltas nas três capabilities acima.
- API/domínio: sem mudança de enums; eixos `declaration_state` e `payment_state` permanecem separados.
- Non-goals: redefinir KPIs; alterar resolver PagtoWeb; SERPRO live; flags ON; DCTFWeb/Declarações.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs `pgdasd-das-payment-column`, `pgdasd-pa-delivery-situation`, `monitoring-portfolio-columns`
- Depende de: nenhuma
- Capability/contrato: UI carteira PGDAS-D
- Marco exigido: `apply`
- Relação: coordenada
- Desbloqueia: nenhuma change ativa
- Paralelismo: livre vs changes que não toquem as mesmas specs de colunas PGDAS
