## Why

A consulta produtiva e não mutante `defis.consdeclaracao`
(`CONSDECLARACAO142`) ainda não possui uma superfície de monitoramento
dedicada. O monitor precisa registrar com segurança quais anos e tipos de
DEFIS já foram transmitidos, sem reter identificadores fiscais retornados pela
fonte.

## What Changes

- Implementar o contrato tipado, codec por allowlist, projeção tenant-scoped e
  rotas locais para a operação 142.
- Exibir histórico local e uma ação explícita de coleta no monitor de
  declarações, sem consulta automática ou payload bruto.
- Cobrir falhas de contrato, isolamento de escritório, confirmação de cobrança
  e fake/simulated com logs e evidências sanitizados.

Não serão feitas chamadas SERPRO de negócio, transmissão de DEFIS, leitura de
credenciais, retenção de `idDefis` nem alteração das operações 141, 143 e 144.

## Capabilities

### New Capabilities

- `defis-declarations-monitoring`: consulta explícita e histórico local seguro
  das declarações DEFIS transmitidas pelo serviço 142.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: adapter Simples/MEI, codec, projeção, migração, rotas e testes.
- Frontend: tipos, composable e modal no monitor de declarações.
- Segurança: `CurrentOffice`, flags fail-closed, confirmação explícita e sem
  `idDefis`, identificador fiscal, token, Base64 ou payload bruto em API/log.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `schema-conventions`, catálogo SERPRO versionado e as
  changes já verificadas de Simples/MEI.
- Depende de: nenhuma change ativa; contrato `DEFIS/CONSDECLARACAO142` no
  marco `specs`, relação `coordenada`.
- Desbloqueia: a leitura de lista DEFIS no monitor e base segura para avaliações
  futuras, independentes, dos serviços 143 e 144.
- Paralelismo: pode avançar em paralelo somente com changes que não alterem o
  adapter Simples/MEI, o controller, a página de declarações ou a matriz.
