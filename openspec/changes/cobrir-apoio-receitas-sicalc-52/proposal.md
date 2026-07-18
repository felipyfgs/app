## Why

O monitor ainda não expõe o serviço oficial `SICALC/CONSULTAAPOIORECEITAS52`,
que informa quais atributos são aceitos e obrigatórios para uma receita antes
da emissão assistida de DARF. Sem ele, a preparação de guias depende de dados
manuais e não há histórico auditável no tenant.

## What Changes

- Implementar a leitura não mutante `CONSULTAAPOIORECEITAS52` na rota `/Apoiar`.
- Validar `codigoReceita`, normalizar somente os metadados necessários para o
  monitor e descartar identificadores e payloads brutos.
- Expor histórico local e consulta explícita autorizada no detalhe do cliente.

## Capabilities

### New Capabilities

- `sicalc-revenue-support-monitoring`: consulta e projeção local segura dos atributos de receita SICALC.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend Laravel: adapter SICALC, codec, projeção, controller e rotas fiscais tenant-scoped.
- Frontend Nuxt: tipos, cliente API, composable e painel do detalhe do cliente.
- Documentação: matriz de cobertura e evidência local do piloto.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `schema-conventions` e changes concluídas de monitoramento fiscal.
- Depende de: nenhuma.
- Capability/contrato: coordenada catalogada `sicalc.consultaapoioreceitas`.
- Marco exigido: `apply`; relação: `coordenada`.
- Desbloqueia: preparação segura de emissão assistida de DARF.
- Paralelismo: não altera contratos ativos de PGDAS-D, DEFIS ou DCTFWeb; compartilha somente o executor SERPRO e a superfície de guias.

### Non-goals

- Não emitir, consolidar ou pagar DARF, nem habilitar mutações fiscais.
- Não realizar chamada SERPRO de negócio sem autorização operacional explícita.
- Não expor CNPJ, CPF, tokens, certificados ou payload bruto.
