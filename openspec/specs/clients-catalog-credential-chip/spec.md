## Purpose

Capability `clients-catalog-credential-chip` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Chip de certificado na lista usa termo certificado

Na lista de clientes, a coluna **Certificado digital** SHALL refletir a presença de credencial em `client_credentials` (via `credential_summary`) e SHALL usar o termo **certificado** no copy de ausência — MUST NOT exibir "Sem A1" como rótulo de produto.

#### Scenario: Cliente sem credencial

- **WHEN** o cliente não possui `credential_summary` (sem registro ativo/relevante em `client_credentials`)
- **THEN** o chip da coluna Certificado digital exibe exatamente `Sem certificado` com tom neutro

#### Scenario: Cliente com credencial ativa

- **WHEN** o cliente possui `credential_summary` com status ativo e validade futura sem alerta de vencimento
- **THEN** o chip continua indicando validade (ex.: `Válido até <data>`) e MUST NOT usar o jargão "A1" no rótulo do chip

### Requirement: Filtros KPI da lista alinham ao termo certificado

Os filtros/KPI operacionais da lista de clientes que se referem à presença ou ausência de credencial SHALL usar o termo **certificado** nos títulos e rótulos acessíveis visíveis ao usuário, sem alterar os códigos de filtro da API.

#### Scenario: KPI sem certificado

- **WHEN** o usuário vê o filtro de clientes sem credencial na lista
- **THEN** o título/aria visível usa `Sem certificado` (MUST NOT usar `Sem A1`)
