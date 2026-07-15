## MODIFIED Requirements

### Requirement: Inbox para falhas de canais SEFAZ
O sistema SHALL incluir na inbox operacional itens acionáveis para cursors SEFAZ bloqueados, consumo indevido (656), falhas consecutivas de decode e A1 impactando canais DistDFe e CT-e, com deep-link para sincronização do cliente. O sistema MUST NOT produzir item operacional para MDF-e.

#### Scenario: Consumo indevido DistDFe
- **WHEN** um cursor DistDFe registra cStat 656 ou bloqueio equivalente
- **THEN** a inbox contém item de severidade alta ou crítica com canal DistDFe e sem envelope SOAP bruto

#### Scenario: Cursor MDF-e legado
- **WHEN** existe cursor MDF-e legado em banco
- **THEN** ele não aparece na inbox nem nas contagens operacionais
