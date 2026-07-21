## Purpose

Capability `monitoring-url-canonical` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: URL Nuxt de monitoramento path-only
As superfícies sob `/monitoring/*` SHALL manter a URL Nuxt apenas no path canônico do recurso. Filtros de lista, ordenação, paginação, submódulo/aba e demais controles de UI MUST NOT ser serializados na query string da rota Nuxt. Isso inclui, no mínimo: `/monitoring/simples`, `/monitoring/mei`, `/monitoring/dctfweb`, `/monitoring/fgts`, `/monitoring/installments`, `/monitoring/sitfis`, `/monitoring/declarations`, `/monitoring/guides` e `/monitoring/mailbox`. Params de filtro/sort enviados à API HTTP permanecem válidos no client da API e MUST NOT aparecer na barra de endereço do painel.

#### Scenario: Ordenar carteira Simples Nacional
- **WHEN** o operador ordena a grade em `/monitoring/simples` por RBT12 (ou outra coluna server-side)
- **THEN** a URL permanece `/monitoring/simples` sem query
- **AND** a lista recarrega com o sort aplicado via request à API

#### Scenario: Bookmark legado com query
- **WHEN** o operador abre `/monitoring/simples?sort=rbt12` (ou qualquer query residual)
- **THEN** o sistema faz replace para `/monitoring/simples` sem query
- **AND** a carteira carrega com defaults locais de filtro/sort

#### Scenario: Demais carteiras via portfolio
- **WHEN** o operador aplica filtro, sort ou paginação em `/monitoring/mei`, `/monitoring/dctfweb`, `/monitoring/fgts`, `/monitoring/installments`, `/monitoring/sitfis` ou `/monitoring/declarations`
- **THEN** a URL Nuxt de cada superfície permanece sem query

#### Scenario: Guias sem query na URL
- **WHEN** o operador aplica sort, filtro de pagamento ou troca de página em `/monitoring/guides`
- **THEN** a URL permanece `/monitoring/guides` sem query
