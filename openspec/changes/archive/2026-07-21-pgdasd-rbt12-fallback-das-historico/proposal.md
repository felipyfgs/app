## Why

Quando o PA esperado está Sem DAS (sem movimento / sem guia), a carteira mostra RBT12 `—`. Porém a declaração (e o extrato/recibo do período) **já trazem** RBT12/RB12 para aquele PA. O operador precisa ver esse valor sem depender de DAS.

## What Changes

- **BREAKING (reserva RBT12):** deixar de tratar “PA sem DAS” como terminal `NO_DAS` sem leitura, quando houver declaração local do PA esperado com artefato/documento parseável.
- Extrair RBT12 do **documento da declaração do PA esperado** (mesmo período, inclusive sem movimento), fail-closed.
- Manter o caminho atual via extrato DAS (`CONSEXTRATO16`) quando houver DAS.
- UI: coluna RBT12 passa a exibir o valor parseado desse PA; popover indica origem (declaração vs extrato DAS).

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-rbt12-extract-retry`: reserva pós-MONITOR sem DAS no PA esperado usa declaração do PA.
- `pgdasd-rbt12-extrato-parse`: parser/origem aceita documento de declaração do PA (além do extrato DAS), sem inventar valores.

## Impact

- API: `PgdasdRbt12Service`, pós-consult, possível parser de declaração, testes.
- Non-goals: estimar RBT12; fallback para DAS de outro mês como fonte primária; flags ON; SERPRO live na grade.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `pgdasd-rbt12-extract-retry`, `pgdasd-rbt12-extrato-parse`
- Depende de: nenhuma
- Marco: `apply`
- Relação: coordenada
