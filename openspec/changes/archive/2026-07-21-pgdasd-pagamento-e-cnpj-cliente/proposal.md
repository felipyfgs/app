## Why

O popover da coluna Pagamento PGDAS-D força o operador a interpretar contagens de DAS e códigos máquina (`DAS_PAYMENT_NOT_LOCATED`), quando a decisão operacional é só “o cliente pagou ou não?”. Na mesma carteira, a célula Cliente exibe CNPJ com máscara ocultadora (`2646******0151`), inadequada para uso diário de cópia/consulta.

## What Changes

- Popover da coluna **Pagamento**: sem contagens DAS nem reason codes crus; se `PAID`, sinal “Em dia”; se `UNPAID`, listinha de competências em aberto (com `amount_cents` opcional quando existir em guias).
- API do portfolio PGDAS enriquece `detail.pgdasd` com `payment_open_competencies`.
- Célula Cliente das carteiras: exibir CNPJ com máscara padrão Brasil; clique copia só os dígitos.
- API do portfolio inclui `cnpj` normalizado (14 chars) na linha do cliente; `cnpj_masked` permanece por compat.

Non-goals: extrair valor DAS do PDF/SERPRO; badge Pagamento agregando todos os PAs; KPI/filtro por pagamento; trocar `cnpj_masked` em modais/histórico/exports; live SERPRO; flags ON; mei no Compose.

## Capabilities

### New Capabilities

- `pgdasd-payment-popover-cliente`: contrato do popover Pagamento no nível do cliente (estado + competências em aberto).
- `monitoring-client-cnpj-display`: CNPJ com máscara BR na célula Cliente das carteiras e cópia dos dígitos.

### Modified Capabilities

- (nenhuma em `openspec/specs/` — main specs vazias; a coluna Pagamento já existe via change `pgdasd-coluna-pagamento-das`, coordenada)

## Impact

- API: `PgdasdMonitoringQueryService` / portfolio detail (`payment_open_competencies`); `ModuleClientRowDto` + `ModulePortfolioQueryService` (`cnpj`); testes unitários.
- Web: `PaymentValue.vue`, `pgdasd.ts`, types; `FiscalClientCell.vue` + builders de colunas das carteiras; testes unitários.
- DB: sem migration obrigatória (usa `pgdasd_operations.payment_located` e opcionalmente `tax_guides.amount_cents`).

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: `pgdasd_operations.payment_located`, `FiscalClientCell`, `formatCnpj` / `normalizeCnpj`
- Depende de: `pgdasd-coluna-pagamento-das` (capability `pgdasd-das-payment-column`, marco `apply`, relação `coordenada` — badge/estado de pagamento já na spine)
- Capability/contrato desta change: `pgdasd-payment-popover-cliente`, `monitoring-client-cnpj-display`
- Desbloqueia: nenhuma
- Paralelismo: não editar artefatos/código da change irmã além de ler o contrato da coluna Pagamento; CNPJ é ortogonal e pode avançar em paralelo interno (N0)
