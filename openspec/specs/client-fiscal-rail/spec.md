## Purpose

Capability `client-fiscal-rail` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Rail enxuto no detalhe fiscal da empresa

O produto SHALL ocultar no rail e no overview do detalhe `/monitoring/clients/:id` as seções Execuções, Achados, Cadastro e Vínculos, Renúncias e Processos Fiscais. O produto MUST NOT apagar dados dessas seções no backend só por ocultá-las na UI.

#### Scenario: Seções internas ocultas

- **WHEN** o usuário abre o detalhe fiscal de um cliente
- **THEN** o rail NÃO lista Execuções, Achados, Cadastro e Vínculos, Renúncias nem Processos Fiscais
- **AND** o overview de processos NÃO inclui cards dessas seções ocultas

#### Scenario: Deep-link de seção oculta

- **WHEN** o usuário navega diretamente para o path de uma seção oculta
- **THEN** o produto redireciona para o overview do mesmo cliente

### Requirement: CCMEI apenas para MEI

O produto SHALL exibir a seção CCMEI no rail e no overview somente quando o cliente for identificado como MEI. Para cliente Simples Nacional que não for MEI, CCMEI MUST NOT aparecer.

#### Scenario: Cliente MEI vê CCMEI

- **WHEN** o cliente tem regime MEI (ou equivalência `clientIsMei`)
- **THEN** a seção CCMEI está disponível no rail e no overview

#### Scenario: Simples Nacional sem MEI não vê CCMEI

- **WHEN** o cliente é Simples Nacional e não é MEI
- **THEN** a seção CCMEI NÃO aparece no rail nem no overview
