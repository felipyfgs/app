## Why

A tela `/monitoring/clients/:id/pgdasd` já agrupa por PA, mas ainda separa Declarações e DAS em duas subtabelas — diferente do portal oficial do Simples (“Consultar Declarações”), onde cada PA tem uma grade única com cabeçalhos agrupados Declaração/DAS e linhas por operação (Original, Retificadora, Geração de DAS). Operadores precisam reconhecer o mesmo modelo mental sem sair do design system do painel.

## What Changes

- Substituir as duas subtabelas por PA por uma grade desktop no espírito do portal: faixa do PA + cabeçalhos agrupados + linhas por operação.
- Associar artefatos (Recibo, Declaração, MAED, Extrato) à linha correta via `declaration_number` / `das_number` já presentes no payload; documentos sem vínculo ficam em “Outros documentos”.
- Em mobile, apresentar as mesmas operações como cards compactos (sem tabela horizontal inutilizável).
- Manter a grade oficial somente no detalhe do cliente e remover o modal aninhado `PgdasdDeclarationsHistoryModal` para evitar duas superfícies concorrentes.
- Atualizar testes/fidelity da superfície.

Non-goals:
- Não alterar contratos de API / projeção SERPRO / coleta automática.
- Não ligar flags SERPRO/MEI nem disparar consulta ao só abrir a tela.
- Não copiar CSS institucional do portal RFB (permanecer no tema Nuxt UI do painel).
- Sem parecer jurídico, mutações fiscais ou targets ops indisponíveis.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-client-history-layout`: layout do histórico local passa a exigir grade oficial agrupada por PA (em vez de duas subtabelas como layout principal no desktop).

## Impact

- Web: `PgdasdHistoryView.vue`, novo helper/modelo de linhas, `PgdasdHistoryPeriodGrid.vue`, remoção do modal aninhado em `PgdasdDasHistoryModal.vue`, tipos em `fiscal-modules.ts`, testes unit/fidelity.
- API: nenhuma.
- Compose/OpenSpec: validação da change com delta.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: `pgdasd-client-history-layout`, downloads autenticados, payload de histórico local
- Depende de: nenhuma
- Desbloqueia: UX alinhada ao portal do Simples no detalhe do cliente
- Paralelismo: ownership = superfície de histórico PGDAS-D no web; não editar resolver de pagamento das changes PAGTOWEB ativas
