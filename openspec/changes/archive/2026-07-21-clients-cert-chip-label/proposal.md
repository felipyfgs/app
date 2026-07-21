## Why

Na lista de clientes, a coluna **Certificado digital** usa o jargão interno "Sem A1" quando não há registro em `client_credentials`. "A1" não é o termo do produto para o usuário; o painel já fala em certificado digital, e o detalhe do cliente já usa "Sem certificado".

## What Changes

- Chip de ausência na coluna **Certificado digital** passa a exibir literalmente **Sem certificado** (em vez de "Sem A1").
- Critério permanece o resumo de credencial do cliente (`credential_summary` / tabela `client_credentials`): presença = certificado cadastrado; ausência = sem certificado — sem rótulo "A1" no chip da lista.
- Alinhar filtros/KPI da mesma lista de clientes que ainda dizem "Com A1" / "Sem A1" / "A1 vencido" para a linguagem **certificado**.
- Atualizar testes unitários do chip.

## Capabilities

### New Capabilities

- `clients-catalog-credential-chip`: copy e estados do chip/filtros de certificado digital na lista de clientes (ausência, validade, vencimento), alinhados ao domínio `client_credentials` e ao termo "certificado".

### Modified Capabilities

- (nenhuma — `openspec/specs/` sem capabilities arquivadas para este contrato)

## Impact

- Frontend: `apps/web/app/utils/clients-credential.ts`, filtros em `ClientCatalogList.vue`, badges correlatos em filiais se usarem o mesmo copy, testes em `clients-table.test.ts`.
- API/banco: sem mudança de schema ou payload; `credential_summary` já deriva de `client_credentials`.
- Non-goals: não renomear fluxos técnicos SERPRO/office "A1" em settings/onboarding; não alterar vault, upload PFX nem status machine; não ligar flags SEFAZ/SERPRO/MEI; sem mei no Compose; sem ops backup/restore.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias / archive (fora do DAG ativo)
- Depende de: nenhuma
- Capability/contrato: `clients-catalog-credential-chip` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação web do chip/filtros
- Paralelismo: pode rodar em paralelo com changes ativas que não toquem os mesmos arquivos de lista de clientes (`ClientCatalogList.vue`, `clients-credential.ts`)
