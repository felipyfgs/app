## Why

O monitor consulta os dados do CCMEI pelo serviço 122, mas ainda não acompanha a
situação cadastral retornada pelo serviço oficial 123. Essa lacuna impede que o
usuário enxergue, de forma segura, se os CNPJ MEI vinculados ao contribuinte
estão ativos e enquadrados como MEI.

## What Changes

- Implementar o fluxo de leitura `CCMEI/CCMEISITCADASTRAL123` no monitor.
- Normalizar apenas situação cadastral e indicador de enquadramento, descartando
  CNPJ, CPF e demais campos não necessários.
- Acrescentar histórico tenant-scoped, ação confirmada, interface e testes.

## Capabilities

### New Capabilities

- `ccmei-registration-status-monitoring`: consulta segura da situação cadastral CCMEI e sua projeção local.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: catálogo Simples/MEI, adapter, codec allowlist, projeção, API e testes Laravel.
- Frontend: tipos, cliente API, composable, painel do cliente e testes Nuxt.
- Segurança: `CurrentOffice`, `TenantAuthorization`, confirmações explícitas e logs por allowlist.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `schema-conventions` e a change arquivada de `cobrir-consulta-dados-ccmei`.
- Depende de: `cobrir-consulta-dados-ccmei` — contrato/projeção CCMEI 122 — marco `verify` — relação `coordenada`.
- Desbloqueia: cobertura de leitura de `ccmei.ccmeisitcadastral`.
- Paralelismo: pode avançar sem alterar os contratos DEFIS, DCTFWeb ou parcelamentos; compartilha somente o adapter e a superfície CCMEI.

### Non-goals

- Não chamar a SERPRO em ambiente real sem autorização operacional explícita.
- Não emitir CCMEI, alterar enquadramento, executar mutações fiscais ou disparar comunicação externa.
- Não expor CPF, CNPJ, tokens, certificados, payload bruto ou conteúdo do cofre.
