## ADDED Requirements

### Requirement: Captura de MDF-e no catálogo
O sistema SHALL capturar documentos MDF-e (modelo 58) e eventos relevantes quando o CNPJ for ator de interesse (contratante, autXML, etc.) via **`MDFeDistribuicaoDFe` / `mdfeDistDFeInteresse`**, autenticado com o A1 da raiz, e SHALL projetá-los com `kind=MDFE` e `source=SEFAZ`. Captura MDF-e MUST ser opt-in por estabelecimento/cliente (interesse contábil tipicamente menor que NF-e/CT-e).

#### Scenario: MDF-e persistido
- **WHEN** um XML de MDF-e bem-formado é aceito
- **THEN** o original fica imutável no vault e a listagem `/documents?kind=MDFE` inclui o item

### Requirement: Cursor e limites do canal MDF-e
O sistema SHALL manter cursor independente dos canais NF-e DistDFe e CT-e e SHALL aplicar rate limit e tratamento de falhas análogos (sem salto silencioso de sequência).

#### Scenario: Cursor MDF-e isolado
- **WHEN** o cursor de CT-e avança
- **THEN** o cursor do canal MDF-e não é alterado

### Requirement: Parse tolerante de leiaute MDF-e
O sistema SHALL extrair campos de consulta conhecidos e, em schema desconhecido, preservar o XML e marcar revisão.

#### Scenario: Schema MDF-e desconhecido
- **WHEN** o lote DistDFe MDF-e contém XML bem-formado com schema ainda não mapeado
- **THEN** o XML original é persistido no vault com alerta de parse e o NSU do lote não é avançado de forma silenciosa em falha de decode
