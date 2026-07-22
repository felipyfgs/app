## Purpose

Capability `client-fiscal-rail` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Rail canônico espelhando Monitoramento

O produto SHALL exibir no rail do detalhe `/monitoring/clients/:id` os mesmos labels e a mesma ordem dos contextos de Monitoramento: Dashboard, Simples Nacional, MEI (condicional), DCTFWeb, FGTS Digital, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias, Cadastro e Vínculos, Processos Fiscais. O produto MUST NOT inventar itens de navegação fora dessa lista canônica no rail fiscal.

#### Scenario: Ordem e labels canônicos (Simples sem MEI)

- **WHEN** o usuário abre o detalhe fiscal de um cliente que não é MEI
- **THEN** o rail lista, nesta ordem: Dashboard, Simples Nacional, DCTFWeb, FGTS Digital, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias, Cadastro e Vínculos, Processos Fiscais
- **AND** o rail NÃO inclui MEI, Pendências, Execuções, Achados nem Renúncias

#### Scenario: MEI aparece só para cliente MEI

- **WHEN** o cliente é identificado como MEI
- **THEN** o rail inclui MEI imediatamente após Simples Nacional
- **AND** WHEN o cliente não é MEI
- **THEN** MEI NÃO aparece no rail

### Requirement: Seções internas ocultas com deep-link seguro

O produto SHALL ocultar no rail as seções Pendências, Execuções, Achados e Renúncias. O produto MUST NOT apagar dados dessas seções no backend só por ocultá-las na UI. Deep-link para seção oculta MUST redirecionar ao Dashboard (overview) do mesmo cliente.

#### Scenario: Deep-link de Pendências

- **WHEN** o usuário navega para `/monitoring/clients/:id/pending`
- **THEN** o produto redireciona para o overview do mesmo cliente

### Requirement: Seletor de empresa no header do rail

O produto SHALL oferecer no header do sidebar do detalhe fiscal um combobox/select com busca para trocar de empresa. Ao selecionar outro cliente, o produto SHALL navegar para o detalhe desse cliente preservando a seção atual quando ela for visível no destino; caso contrário, SHALL abrir o Dashboard (overview) do destino.

#### Scenario: Troca preservando seção

- **WHEN** o usuário está em `/monitoring/clients/1/guides` e seleciona o cliente 2 no seletor
- **THEN** a navegação vai para `/monitoring/clients/2/guides`

#### Scenario: Troca com fallback de seção MEI

- **WHEN** o usuário está na seção MEI de um cliente MEI e seleciona um cliente que não é MEI
- **THEN** a navegação vai para o overview do cliente selecionado
