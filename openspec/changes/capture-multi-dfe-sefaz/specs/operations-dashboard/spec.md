## ADDED Requirements

### Requirement: Inbox para falhas de canais SEFAZ
O sistema SHALL incluir na inbox operacional itens acionáveis para cursors SEFAZ bloqueados, consumo indevido (656), falhas consecutivas de decode e A1 impactando canais DistDFe/CT-e/MDF-e, com deep-link para sincronização do cliente.

#### Scenario: Consumo indevido DistDFe
- **WHEN** um cursor DistDFe registra cStat 656 ou bloqueio equivalente
- **THEN** a inbox contém item de severidade alta/crítica com canal DistDFe e sem envelope SOAP bruto

### Requirement: Resumo de saúde multi-canal
O sistema SHALL refletir no resumo de operações a existência de problemas em cursors não-ADN (além dos já cobertos para ADN).

#### Scenario: Health com DistDFe em erro
- **WHEN** há estabelecimento com cursor DistDFe em ERROR/BLOCKED
- **THEN** o resumo/inbox não ignora o problema por ser canal diferente do ADN
