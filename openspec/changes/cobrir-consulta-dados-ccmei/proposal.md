## Why

A operação oficial `CCMEI.DADOSCCMEI122` é uma consulta produtiva e somente de
leitura do certificado da condição de microempreendedor individual, mas o hub
ainda não a oferece de forma tipada, rastreável e isolada por escritório. A
lacuna impede que o usuário consulte uma evidência autorizada do CCMEI sem
recorrer a fluxos externos e sem a proteção uniforme exigida para a Integra
Contador.

## What Changes

- Implementar a consulta não mutante `CCMEI.DADOSCCMEI122` com parâmetros
  validados, adapter fail-closed, decodificação segura e projeção local
  limitada ao `CurrentOffice`.
- Expor uma ação explícita e o histórico sanitizado na superfície de cliente
  apropriada, sem devolver documento bruto, Base64, credenciais ou tokens.
- Cobrir contrato HTTP, tipagens, erros, logs seguros, fixture/fake/simulated e
  os testes backend e Nuxt que representem sucesso, vazio, falha e isolamento
  entre escritórios.
- Registrar na matriz de cobertura a fonte oficial, a evidência de testes
  locais e que a homologação SERPRO real permanece pendente de credenciais e
  autorização operacional.

Não são objetivos desta change habilitar SERPRO live, criar ou alterar MEI,
emitir documentos fiscais, consultar produção sem autorização explícita,
alterar regras de negócio de clientes, expor bytes do cofre ou introduzir novas
dependências.

## Capabilities

### New Capabilities

- `ccmei-certificate-consultation`: consulta local, tenant-scoped e testável
  dos dados do CCMEI retornados por `DADOSCCMEI122`.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend Laravel: catálogo de operações, DTO/codec, adapter, serviço de
  evidência, endpoint autenticado, autorização por `CurrentOffice` e testes.
- Frontend Nuxt: tipos, composable e componente de consulta/histórico no
  contexto do cliente, com estados de carregamento, vazio, erro e sucesso.
- Operação SERPRO: somente fake/simulated nos ambientes de desenvolvimento e
  teste; a integração externa continua sob flags default OFF, kill switch e
  allowlist.
- Documentação: matriz de cobertura Integra Contador e artefatos OpenSpec em
  pt-BR.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `schema-conventions`, catálogo SERPRO versionado local e os
  contratos atuais de `CurrentOffice` e feature flags.
- Depende de: nenhuma.
- Capability/contrato: a capability nova consome apenas as abstrações estáveis
  de tenancy e de integração SERPRO já presentes no repositório.
- Marco exigido: não se aplica.
- Relação: não se aplica.
- Desbloqueia: cobertura contratual da consulta oficial de certificado CCMEI e
  sua validação local sem operação fiscal mutante.
- Paralelismo: pode avançar com changes que não modifiquem os mesmos adapters,
  rotas, tipos ou a mesma superfície de cliente; qualquer alteração concorrente
  nesses paths deve ser serializada.
