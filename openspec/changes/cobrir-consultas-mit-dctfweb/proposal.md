## Why

A operação oficial `mit.listaapuracoes` (`LISTAAPURACOES317`) está marcada como
produtiva no catálogo SERPRO, mas ainda não possui contrato tipado, projeção nem
exibição no monitoramento DCTFWeb/MIT. Isso cria uma lacuna entre o catálogo
oficial e a capacidade do escritório de consultar apurações já autorizadas sem
usar a rede em testes.

## What Changes

- Implementar a consulta não mutante `MIT.LISTAAPURACOES317` com request
  validado, adapter fail-closed, projeção tenant-scoped e evidência sanitizada.
- Expor o resultado local na cápsula MIT de `/monitoring/dctfweb`, preservando a
  interface existente e sem habilitar encerramento de apuração.
- Cobrir autenticação, parâmetros, respostas, erros, isolamento por
  `CurrentOffice`, UI e downloads somente com fixtures/fake/simulated.
- Corrigir a listagem/download de documentos locais DCTFWeb quando a correção for
  necessária para representar a evidência da consulta, preservando o MIME seguro.

Não são objetivos desta change executar SERPRO real, emitir guia, transmitir
DCTFWeb, encerrar apuração MIT, expor XML/bytes do cofre, alterar credenciais ou
habilitar flags de produção.

## Capabilities

### New Capabilities

- `mit-listing-monitoring`: consulta e apresentação local, tenant-scoped e
  testável das apurações MIT retornadas por `LISTAAPURACOES317`.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: mapa de operações, caller/adapters MIT, projeções de monitoramento,
  controller/DTOs e testes Laravel.
- Frontend: tipos, renderer/cápsula MIT e modal de histórico DCTFWeb, com testes
  Nuxt.
- Segurança: somente `CurrentOffice`, metadados públicos sanitizados e cofre
  autorizado; nenhuma chamada HTTP em testes.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `schema-conventions` e o catálogo SERPRO versionado local.
- Depende de: `integrar-monitoramento-dctfweb` — capability/contrato de
  monitoramento DCTFWeb/MIT já aplicado; marco exigido `apply`; relação
  `coordenada`.
- Desbloqueia: cobertura contratual e UI da consulta MIT 317 sem depender de
  operação fiscal mutante.
- Paralelismo: pode avançar em paralelo com changes que não alterem adapters,
  controller, tipos ou UI DCTFWeb/MIT; mudanças na mesma superfície são
  serializadas.
