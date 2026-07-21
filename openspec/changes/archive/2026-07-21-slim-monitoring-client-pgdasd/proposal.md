## Why

A aba PGDAS-D em `/monitoring/clients/:id` mistura chrome de marketing (descrições longas), atalho duplicado “Histórico DAS”, pills de navegação cruzada e empty states verbosos — o usuário precisa ver só o que a API de histórico local entrega (períodos, estado, PA, consulta, ano).

## What Changes

- Enxugar `PgdasdHistoryView`: título curto, sem description de “armazenados localmente”; resumo só com campos da API; empty curto.
- Remover botão/modal duplicado “Histórico DAS” da seção `pgdasd` em `[clientId].vue` (a própria view já lista DAS/declarações).
- Remover pills de atalho no rodapé do detalhe (Caixa postal / DCTFWeb / Simples/MEI / Cadastro completo) — navegação já existe no rail/aside.
- Manter: seletor de ano, lista de períodos da API, coleta explícita de documentos, loading/error.

## Capabilities

### New Capabilities

- `monitoring-client-pgdasd-surface`: superfície enxuta do histórico PGDAS-D no detalhe do cliente, alinhada ao payload da API.

### Modified Capabilities

- (nenhuma)

## Impact

- Web: `PgdasdHistoryView.vue`, `pages/monitoring/clients/[clientId].vue`.
- API: nenhuma.
- Non-goals: redesign por PA (`pgdasd-history-period-layout`); não remover seções fiscais do rail; sem SERPRO ao abrir.

### Dependências entre changes

- Nível: `C0`
- Coordenada com: `pgdasd-history-year-selector` / `pgdasd-history-period-layout` (mesmo arquivo view) — preservar seletor de ano
- Depende de: nenhuma
