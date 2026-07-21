## Why

A coluna Pagamento e o popover de pendências PGDAS-D ainda se apoiam em `dasPago` (CONSDECLARACAO13), que só indica se **aquele número de DAS** consta pago no índice PGDAS. Isso diverge da Receita (certidão/SITFIS limpa) e da fonte oficial de arrecadação: PAGTOWEB (`PAGAMENTOS71`), que confirma documento pago com data contábil. Escritórios precisam de quitação confiável sem inventar débito.

## What Changes

- Tornar `pagtoweb.pagamentos` (`PAGAMENTOS71`, procuração `00004`) a autoridade canônica para “este DAS foi pago?”.
- Após MONITOR PGDAS produtivo, enfileirar conciliação automática por lote de `numeroDocumento` (máx. 100/página), persistindo evidência local fail-closed.
- Badge e `payment_open_competencies` passam a decidir só com cobertura PAGTOWEB local: pago permanente; não encontrado só com cobertura completa e fresca (TTL); caso contrário `UNVERIFIED`.
- `dasPago` permanece auxiliar/auditoria; SITFIS não substitui prova por documento; `COMPARRECADACAO72` só sob demanda (fora da automação).
- Portfolio GET continua sem live SERPRO.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-das-payment-column`: precedência do `payment_state` do PA esperado passa a depender da evidência PAGTOWEB local (não mais de `dasPago` sozinho).
- `pgdasd-payment-popover-cliente`: `payment_open_competencies` lista só PAs com cobertura PAGTOWEB negativa completa e fresca; valor preferencial da evidência PAGTOWEB quando houver.

## Impact

- API: codec/adaptador PAGTOWEB (filtro `numeroDocumentoLista`), pós-MONITOR enqueue, colunas/estado em `pgdasd_operations`, `PgdasdDasPaymentStateResolver`, `openPaymentCompetencies`, testes.
- Web: contrato atual preservado; copy/descrição humana MAY distinguir Confirmado / Não verificado / Pendência confirmada.
- SERPRO: bilhetagem CONSULTA; poder `00004`; fail-closed sem autorização/erro/cobertura parcial.
- Non-goals: live SERPRO no GET; SITFIS na badge; emissão em massa de comprovante PDF; parecer jurídico; mutações fiscais; flags ON; mei no Compose; ops backup/restore.

### Dependências entre changes

- Nível: **C1**
- Bases estáveis: specs `pgdasd-das-payment-column`, `pgdasd-payment-popover-cliente`; catálogo `pagtoweb.pagamentos` já IMPLEMENTED
- Depende de:
  - `pgdasd-pa-pago-qualquer-das` — capability `pgdasd-das-payment-column` / popover — marco `apply` — relação `bloqueante` (regra any-paid e exclusão de PA quitado)
  - `persist-pgdasd-operation-das-amount` — amount em `pgdasd_operations` — marco `apply` — relação `coordenada`
  - `enrich-pgdasd-payment-open-amounts` — fallbacks de valor — marco `apply` — relação `coordenada`
- Desbloqueia: badge/pendências alinhadas à arrecadação RFB; reduz falso positivo vs certidão
- Paralelismo: ownership = resolver + open competencies + ingest PAGTOWEB→PGDAS; não editar artefatos das changes irmãs
