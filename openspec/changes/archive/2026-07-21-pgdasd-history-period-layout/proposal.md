## Why

A tela `/monitoring/clients/:id/pgdasd` (Histórico PGDAS-D) mistura declarações e DAS numa tabela plana com `rowspan` no PA, gerando muitas células vazias e leitura difícil. O portal oficial do PGDAS-D organiza por período de apuração e separa claramente declaração transmitida de geração de DAS — o painel deve aproximar-se dessa hierarquia sem sair do design system.

## What Changes

- Reorganizar `PgdasdHistoryView` em blocos por PA (período de apuração), alinhados ao fluxo oficial “consultar por PA → declaração → DAS”.
- Dentro de cada PA, separar visualmente **Declarações** e **Geração de DAS** (sem tabela única esparsa com colunas cruzadas e `—`).
- Manter resumo (situação, PA esperado, última consulta), coleta explícita de documentos, downloads autenticados e empty/loading/error.
- Unificar mobile e desktop no mesmo modelo mental (PA → seções), em vez de tabela densa vs cards desencontrados.
- Atualizar testes/fidelity da superfície quando existirem seletores ligados à tabela antiga.

Non-goals:
- Não alterar contratos de API / projeção SERPRO / coleta automática.
- Não ligar flags SERPRO/MEI nem disparar consulta ao só abrir a tela.
- Não redesenhar o shell do dashboard nem o modal “Histórico DAS” da carteira Declarações (exceto reuso pontual de padrões).
- Sem parecer jurídico, mutações fiscais ou targets ops indisponíveis.

## Capabilities

### New Capabilities
- `pgdasd-client-history-layout`: layout do histórico local PGDAS-D no detalhe do cliente, agrupado por PA com seções Declaração e DAS no espírito do portal oficial.

### Modified Capabilities
- (nenhuma — `openspec/specs/` sem capability canônica prévia para esta superfície)

## Impact

- Web: `apps/web/app/components/monitoring/PgdasdHistoryView.vue` (principal); possíveis utilitários de label/helpers já em `~/utils/pgdasd*`; testes unit/fidelity da área monitoring/pgdasd.
- API: nenhuma.
- Compose/OpenSpec: validação da change com delta.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: domínio PGDAS-D e downloads autenticados já existentes (main / archives)
- Depende de: nenhuma
- Capability/contrato: `pgdasd-client-history-layout`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação front do histórico no client detail
- Paralelismo: pode seguir em paralelo com changes SERPRO/admin sem overlap de ownership deste componente
