## ADDED Requirements

### Requirement: Situações de NFS-e legíveis na UI
O sistema SHALL apresentar a situação da nota com labels do domínio NFS-e Nacional (Gerada, Substituta, Cancelada, Substituída, Decisão judicial, Em revisão), cores semânticas e, no detalhe, o cStat oficial quando existir.

#### Scenario: Chip na lista
- **WHEN** a listagem exibe uma nota com `status=SUBSTITUTE`
- **THEN** o chip mostra “Substituta” (ou equivalente pt-BR) e não “Cancelada”

#### Scenario: Detalhe modal
- **WHEN** o usuário abre o detalhe de uma nota com cStat 100
- **THEN** vê situação Gerada/Ativa e indicação `cStat 100` (ou texto oficial curto)

#### Scenario: Filtros e export
- **WHEN** o usuário filtra por situação no catálogo ou na exportação
- **THEN** as opções refletem situações NFS-e (sem “Autorizada” como sinônimo de nota de serviço nacional)

### Requirement: Triagem não confunde parse com situação fiscal
O sistema SHALL tratar a fila “Em revisão” como notas com situação indefinida ou parse problemático (`UNKNOWN`), e MUST NOT contar `AUTHORIZED` como revisão de NFS-e nacional.

#### Scenario: Chip Em revisão
- **WHEN** o insights calcula contagem de revisão
- **THEN** inclui apenas notas `UNKNOWN` (e critérios de parse documentados), não status de NF-e de mercadoria

## MODIFIED Requirements

### Requirement: Tabelas administrativas consistentes e server-side
O sistema SHALL apresentar Clientes, Notas, Exportações e Sincronizações com cabeçalho, bordas, densidade e paginação consistentes com o template, preservando o modelo server-side de cada API. Em Notas, chips de situação MUST usar o vocabulário NFS-e Nacional após o alinhamento de status.

#### Scenario: Lista paginada por cursor
- **WHEN** o usuário carrega mais Notas
- **THEN** o sistema usa o cursor da API e mantém filtros, inclusive filtro de situação operacional
