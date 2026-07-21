## Purpose

Capability `monitoring-client-pgdasd-surface` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Histórico PGDAS-D do cliente sem chrome acessório

Na aba PGDAS-D de `/monitoring/clients/:id`, a UI SHALL apresentar o histórico local alinhado ao payload da API (estado, PA esperado, última consulta, períodos do ano filtrado) e MUST NOT exibir description de marketing, botão/modal duplicado “Histórico DAS” nesta aba, nem pills de atalho de outros módulos no rodapé do detalhe.

#### Scenario: Superfície enxuta

- **WHEN** o usuário abre a aba PGDAS-D do cliente
- **THEN** vê o seletor de ano e o conteúdo derivado do histórico local, sem description “armazenados localmente” e sem botão “Histórico DAS” acima da view

#### Scenario: Empty curto

- **WHEN** não há períodos para o ano selecionado
- **THEN** a UI informa a ausência de forma breve, sem parágrafo longo de orientação
