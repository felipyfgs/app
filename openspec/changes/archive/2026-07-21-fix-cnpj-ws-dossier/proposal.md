## Why

A consulta pública CNPJ.ws já mapeia CNAEs secundários, IEs e QSA no backend, mas o dossiê do cliente só exibe o CNAE principal — o usuário interpreta que “faltam atividades essenciais”. Além disso, o payload da fonte duplica sócios (versão mascarada + CPF em claro), inflando o QSA.

## What Changes

- Exibir no dossiê de cadastro (`ClientRegistration`) CNAE principal, lista de CNAEs secundários e inscrições estaduais (ativas em destaque; inativas visíveis).
- Deduplicar sócios no mapeamento do lookup (nome + data de entrada + qualificação), mantendo documento sempre mascarado.
- Reforçar testes do lookup com a fixture Globo: contagem de secundários e QSA sem duplicata.
- Sem alteração do contrato HTTP do lookup além do conteúdo sanitizado já existente; sem persistir hierarquia CNAE (`secao`/`divisao`/…).

## Capabilities

### New Capabilities

- `cnpj-registration-lookup`: consulta cadastral sanitizada (CNPJ.ws como fonte primária), snapshot essencial (CNAEs, IEs, QSA mascarado sem duplicata) e exibição completa no dossiê do cliente.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio)

## Impact

- API: `CnpjWsRegistrationLookup` (dedupe QSA); testes `CnpjRegistrationLookupApiTest` + fixture `publica_cnpj_ws_27865757000102.json`.
- Web: `ClientRegistration.vue` (atividades + IEs no dossiê somente-leitura); reuso do padrão visual já presente em `ClientForm.vue`.
- Persistência: colunas JSON já existentes (`secondary_cnaes`, `state_registrations`, `shareholders`) — sem migration nova.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: nenhuma
- Capability/contrato: `cnpj-registration-lookup` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma change ativa
- Paralelismo: independente de `declarations-obligation-tabs` e `reconstruir-build-deploy-docker`

### Non-goals

- Integração SERPRO live / parecer jurídico / mutações fiscais
- Ligar feature flags SERPRO/MEI/SEFAZ
- Persistência de taxonomia CNAE completa
- Serviços `mei`/`mei-worker` no Compose
- Targets Make de backup/restore/ops indisponíveis
