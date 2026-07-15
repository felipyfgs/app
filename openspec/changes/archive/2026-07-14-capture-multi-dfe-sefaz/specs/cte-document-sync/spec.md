## ADDED Requirements

### Requirement: Captura de CT-e no catálogo
O sistema SHALL capturar documentos CT-e (modelo 57 e variantes do mesmo canal quando habilitadas, ex. OS) e eventos relevantes via **`CTeDistribuicaoDFe` / `cteDistDFeInteresse`** (Ambiente Nacional CT-e, NT 2015.002), autenticado com o A1 da raiz, e SHALL projetá-los no catálogo unificado com `kind=CTE` e `source=SEFAZ`. O NSU do canal CT-e MUST ser independente do DistDFe NF-e e do ADN.

#### Scenario: CT-e persistido
- **WHEN** um XML de CT-e bem-formado é aceito
- **THEN** o original fica imutável no vault e a listagem `/documents?kind=CTE` inclui o item com número, partes e valor quando parseados

### Requirement: Cursor e limites do canal CT-e
O sistema SHALL manter cursor independente do canal DistDFe NF-e para o canal CT-e e SHALL aplicar rate limit e bloqueio explícito em erros permanentes ou falhas consecutivas de decode.

#### Scenario: Cursor CT-e isolado
- **WHEN** o NSU/cursor de NF-e DistDFe avança
- **THEN** o cursor do canal CT-e não é alterado

### Requirement: Parse tolerante de leiaute CT-e
O sistema SHALL extrair campos de consulta conhecidos e, em schema desconhecido, preservar o XML e marcar revisão sem pular o identificador de distribuição.

#### Scenario: Schema CT-e desconhecido
- **WHEN** o lote DistDFe CT-e contém XML bem-formado com schema ainda não mapeado
- **THEN** o XML original é persistido no vault com `parse_status=REVIEW` (ou equivalente) e o NSU do lote avança sem salto silencioso
