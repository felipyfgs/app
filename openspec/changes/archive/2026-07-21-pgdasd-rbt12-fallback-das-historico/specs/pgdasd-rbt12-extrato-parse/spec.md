## ADDED Requirements

### Requirement: Parser RBT12 aceita PDF de declaração ou recibo do PA

Além do extrato DAS (`CONSEXTRATO16`), o sistema SHALL poder extrair RBT12 do PDF de declaração ou recibo obtido via `CONSDECREC15` / `CONSULTIMADECREC14` para o mesmo PA, inclusive quando não houver DAS (período sem movimento). O parser MUST permanecer fail-closed e MUST NOT inventar valores.

#### Scenario: Declaração sem DAS com RBT12 legível

- **WHEN** a reserva RBT12 do PA esperado aponta para declaração sem DAS e o PDF da declaração/recibo contém RBT12 inequívoco
- **THEN** o status da projeção RBT12 torna-se `PARSED` com `total_cents` preenchido

#### Scenario: Layout de declaração com rótulo quebrado em duas linhas

- **WHEN** o texto do PDF traz "Receita bruta acumulada nos doze meses anteriores" com valores na mesma linha e a continuação "ao PA (RBT12)" na linha seguinte
- **THEN** o parser (`pgdasd-rbt12-v4` ou superior) extrai o total inequívoco e NÃO confunde com RBT12p / RPA
