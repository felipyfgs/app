## Purpose

Capability `pgdasd-pa-delivery-situation` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Situação PGDAS-D representa só a entrega do PA esperado

O sistema SHALL classificar a Situação operacional PGDAS-D (`PgdasdDeclarationState`) exclusivamente pela presença ou ausência da declaração do período de apuração esperado (mês anterior no fuso do escritório), prazo confiável e evidência de consulta produtiva. A Situação MUST NOT incorporar situação de malha SERPRO, MAED, tipo de operação ou pagamento de DAS.

#### Scenario: Declaração do PA esperado encontrada
- **WHEN** uma consulta produtiva localiza declaração para o PA esperado
- **THEN** o estado persistido SHALL ser `CURRENT` e a situação fiscal da projeção SHALL ser `UP_TO_DATE`

#### Scenario: Ausência ainda dentro do prazo
- **WHEN** a consulta produtiva não localiza declaração do PA esperado e a data corrente é anterior ou igual ao vencimento confiável
- **THEN** o estado persistido SHALL ser `DUE_WITHIN_DEADLINE` e a situação fiscal SHALL ser `PENDING`

#### Scenario: Ausência após prazo verificado
- **WHEN** a consulta produtiva posterior ao vencimento, com calendário verificado, confirma ausência da declaração do PA esperado
- **THEN** o estado persistido SHALL ser `OVERDUE_NOT_FOUND` e a situação fiscal SHALL ser `ATTENTION`

#### Scenario: Evidência insuficiente
- **WHEN** não há consulta produtiva válida, a resposta é incompleta/simulada, ou o calendário/prazo não permite classificar atraso
- **THEN** o estado persistido SHALL ser `UNVERIFIED` e a situação fiscal SHALL ser `UNKNOWN`

### Requirement: Labels da Situação PGDAS-D na carteira

A UI da carteira Simples/MEI (submódulo PGDASD) SHALL exibir labels pt_BR canônicas para o estado operacional: `CURRENT` → Em dia; `DUE_WITHIN_DEADLINE` → No prazo; `OVERDUE_NOT_FOUND` → Atrasado; `UNVERIFIED` → Não verificado. A precedência visual “Sem procuração” MAY continuar sobrescrevendo a badge quando o cliente não tem procuração e-CAC, sem alterar o estado persistido.

#### Scenario: Célula Situação no prazo
- **WHEN** a linha da carteira tem `declaration_state=DUE_WITHIN_DEADLINE` e procuração não está ausente
- **THEN** a badge Situação MUST exibir o texto “No prazo”

#### Scenario: Célula Situação atrasada
- **WHEN** a linha da carteira tem `declaration_state=OVERDUE_NOT_FOUND` e procuração não está ausente
- **THEN** a badge Situação MUST exibir o texto “Atrasado”

### Requirement: Projeções PGDAS_D persistem situation coerente com o estado

Para obrigações `PGDAS_D`, `tax_obligation_projections.situation` SHALL permanecer coerente com `pgdasd_declaration_state` segundo o mapeamento canônico (`CURRENT`→`UP_TO_DATE`, `DUE_WITHIN_DEADLINE`→`PENDING`, `OVERDUE_NOT_FOUND`→`ATTENTION`, `UNVERIFIED`→`UNKNOWN`). Uma migration de dados MUST reescrever linhas existentes incompatíveis sem alterar projeções de outros códigos de obrigação.

#### Scenario: Backfill de atraso legado
- **WHEN** a migration de compatibilidade encontra projeção `PGDAS_D` com `pgdasd_declaration_state=OVERDUE_NOT_FOUND` e `situation=PENDING`
- **THEN** a migration MUST atualizar `situation` para `ATTENTION`
