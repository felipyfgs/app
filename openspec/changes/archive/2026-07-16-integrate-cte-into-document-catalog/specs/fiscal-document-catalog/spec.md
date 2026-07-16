## MODIFIED Requirements

### Requirement: Identidade de tipo no catálogo
O sistema SHALL identificar cada item do catálogo com um `kind` de DF-e pertencente ao escopo escritural (`NFSE`, `NFE`, `NFCE`, `CTE`) e um `source` de captura quando conhecido (`ADN`, `SEFAZ`, etc.). NF-e, NFC-e e CT-e MUST compartilhar o mesmo contrato de catálogo e MUST NOT ser modelados como módulos documentais separados. O sistema MUST NOT listar MDF-e no catálogo operacional.

#### Scenario: Item NFS-e serializado
- **WHEN** uma projeção NFS-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFSE`, `kind_label` legível e `source=ADN`

#### Scenario: Item NF-e serializado
- **WHEN** uma projeção NF-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFE`, `kind_label` legível e `source=SEFAZ`

#### Scenario: Item NFC-e serializado
- **WHEN** uma projeção NFC-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFCE`, `kind_label` legível e a proveniência efetiva sem exigir um catálogo próprio

#### Scenario: Item CT-e serializado
- **WHEN** uma projeção CT-e é listada ou detalhada
- **THEN** a resposta inclui `kind=CTE`, `kind_label` legível e a proveniência efetiva no mesmo contrato usado pelos demais documentos

#### Scenario: Compatibilidade com filtro MDF-e legado
- **WHEN** o cliente solicita o catálogo com `kind=MDFE`
- **THEN** a API retorna coleção vazia e cursor nulo sem consultar tabela ou projeção MDF-e

### Requirement: Catálogo unificado entrada e saída
O sistema SHALL listar documentos de todas as fontes habilitadas (ADN, DistDFe, import e SEFAZ-MA outbound) com kind, direction, source, channel, modo de captura e disponibilidade de XML completo, filtráveis por kind e direction. CT-e MUST integrar a mesma consulta, ordenação, paginação, detalhe, exportação e autorização usadas por NF-e e NFC-e, preservando seus papéis e qualidades específicos como atributos.

#### Scenario: Filtro combinação NF-e
- **WHEN** `kind=NFE` e `direction=OUT`
- **THEN** retorna apenas saídas NF-e modelo 55, incluindo import e canal MA com XML completo

#### Scenario: Filtro combinação NFC-e
- **WHEN** `kind=NFCE` e `direction=OUT`
- **THEN** retorna apenas saídas NFC-e modelo 65 capturadas por import ou canal MA

#### Scenario: Filtro combinação CT-e
- **WHEN** `kind=CTE` e `direction=IN` ou `direction=OUT`
- **THEN** retorna apenas os interesses CT-e correspondentes do escritório ativo, sem consultar catálogo ou autorização paralelos

#### Scenario: Catálogo misto
- **WHEN** o escritório possui NF-e, NFC-e e CT-e capturados e não informa `kind`
- **THEN** a consulta inclui os três tipos na mesma ordenação estável e paginação, sem misturar documentos de outro `office_id`

#### Scenario: Descoberta sem XML
- **WHEN** uma chave MA está em recuperação pendente sem bytes originais
- **THEN** ela não aparece como documento completo nem é contabilizada como XML entregue

## ADDED Requirements

### Requirement: CT-e usa o fluxo documental unificado
O sistema MUST tratar CT-e capturado ou importado como documento do catálogo canônico, reutilizando listagem, detalhe, `document_interests`, importação XML/ZIP, pendências, exportação e download. Particularidades de `autXML`, origem, papel, qualidade ou cobertura SHALL ser metadados e filtros do CT-e, e MUST NOT criar um repositório ou contrato de acesso documental separado.

#### Scenario: Lote misto de documentos
- **WHEN** um ADMIN ou OPERATOR importa um ZIP autorizado contendo NF-e, NFC-e e CT-e
- **THEN** todos os itens válidos ingressam pelo mesmo fluxo de lote e aparecem no catálogo segundo seu `kind`, sem encaminhar CT-e a módulo próprio

#### Scenario: CT-e com cópia redigida
- **WHEN** o catálogo apresenta CT-e adquirido por `CTE_AUTXML_DIST_NSU` com qualidade `AUTXML_REDACTED`
- **THEN** o mesmo detalhe documental mostra a limitação textual e a proveniência sem retirar o item do catálogo nem tratá-lo como configuração

#### Scenario: Autorização por interesse
- **WHEN** o mesmo CT-e possui interesses diferentes para estabelecimentos ou clientes do mesmo escritório
- **THEN** listagem, detalhe, exportação e download aplicam `document_interests` e o `office_id` da sessão sem direção global única nem vazamento entre tenants
