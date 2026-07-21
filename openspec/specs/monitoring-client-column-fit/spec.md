## Purpose

Capability `monitoring-client-column-fit` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Coluna Cliente encolhe sem forçar scroll horizontal

Nas grades desktop das carteiras de monitoramento que exibem identidade de cliente (`FiscalClientCell`), a coluna Cliente SHALL usar o par `w-full max-w-0` (não `min-w-48`), de modo que o nome truncar com ellipsis CSS conforme a largura disponível, sem forçar barra de rolagem horizontal pelo nome do cliente. As carteiras Simples Nacional / MEI MUST manter `horizontalScroll` desligado (caber na viewport).

#### Scenario: Meta canônica nas carteiras com FiscalClientCell

- **WHEN** a grade desktop de PGDAS-D, PGMEI, DCTFWeb, SITFIS, Declarações, FGTS ou Parcelamentos renderiza a coluna Cliente
- **THEN** a meta da coluna MUST usar `w-full max-w-0` (th) e `w-full max-w-0 overflow-hidden` (td), proveniente do contrato compartilhado de colunas de monitoramento

#### Scenario: Nome longo truncado com título completo

- **WHEN** o nome do cliente excede a largura disponível da célula
- **THEN** o texto visível MUST truncar com ellipsis CSS e o nome completo MUST permanecer disponível no atributo `title` do link ou rótulo

#### Scenario: Simples Nacional sem barra horizontal por overflow da grade

- **WHEN** a carteira Simples Nacional / MEI renderiza a grade desktop
- **THEN** o wrapper da tabela MUST NÃO ativar `horizontalScroll` (escape hatch off)
