## Why

A consulta produtiva e não mutante `regimeapuracao.consultaropcaoregime`
(`CONSULTAROPCAOREGIME103`) ainda não tem uma superfície de monitoramento
dedicada. Isso deixa a opção de regime apurada sem histórico local verificável,
apesar de as consultas 102 e 104 já estarem protegidas e visíveis.

## What Changes

- Implementar contrato, codec por allowlist, projeção tenant-scoped e rotas
  locais para a operação 103.
- Exibir o histórico local e uma ação de coleta explícita na superfície de
  Simples Nacional, sem dados brutos ou consulta automática.
- Cobrir respostas inválidas, autorização, isolamento de office, logs seguros
  e fake/simulated.

Não serão feitas chamadas SERPRO de negócio, mutações de regime, ampliação de
allowlist, leitura de credenciais ou alterações nas operações 101, 102 e 104.

## Capabilities

### New Capabilities

- `regime-option-monitoring`: consulta explícita e histórico local seguro da
  opção de regime de apuração 103.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: catálogo/adaptador Simples MEI, codec, projeção, rotas e testes.
- Frontend: tipos, composable e detalhe de monitoramento PGDAS-D.
- Segurança: `CurrentOffice`, feature flags, cofre somente se o contrato
  oficial exigir artefato, e logs sem identificadores fiscais ou payload bruto.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `schema-conventions` e o catálogo SERPRO versionado.
- Depende de: `cobrir-regime-anos-calendario` e
  `cobrir-regime-resolucao-104`, contrato `REGIMEAPURACAO` no marco `verify`,
  relação `coordenada`.
- Desbloqueia: cobertura local completa das três consultas não mutantes de
  Regime de Apuração já catalogadas.
- Paralelismo: não edita os paths exclusivos de 102/104; só pode avançar em
  paralelo com changes que não alterem o mesmo adaptador, controller, página
  PGDAS-D ou matriz.
