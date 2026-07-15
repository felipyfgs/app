# Fiscal Document Catalog

## Purpose

Armazenamento imutável de XML, interesses/papéis fiscais, projeções (NFS-e e demais kinds) e consulta filtrável do catálogo unificado de documentos.

## Requirements

### Requirement: Documento original imutável
O sistema MUST armazenar os bytes XML originais criptografados, seu SHA-256 e metadados de identificação sem permitir alteração posterior do conteúdo.

#### Scenario: Documento persistido
- **WHEN** um XML distribuído é aceito
- **THEN** o conteúdo recuperado após descriptografia é byte a byte igual ao conteúdo recebido

### Requirement: Interesses e papéis fiscais
O sistema SHALL relacionar cada documento aos estabelecimentos interessados e aos papéis `ISSUER`, `TAKER` ou `INTERMEDIARY`, preservando NSUs independentes.

#### Scenario: Nota entre dois clientes do escritório
- **WHEN** a mesma NFS-e é distribuída ao prestador e ao tomador cadastrados no mesmo escritório
- **THEN** o sistema mantém um documento lógico e dois interesses com seus respectivos NSUs e papéis

### Requirement: Projeções de NFS-e e eventos
O sistema SHALL manter projeções de NFS-e e eventos a partir do XML capturado, incluindo número, partes, valor, competência, papel, locais quando parseados, **situação operacional alinhada ao cStat nacional e eventos**, e código de situação oficial quando existir.

#### Scenario: Evento de cancelamento
- **WHEN** um evento de cancelamento válido é vinculado a uma chave de acesso existente
- **THEN** a projeção da nota passa a cancelada e ambos os XMLs permanecem imutáveis

#### Scenario: Parse bem-sucedido com projeção
- **WHEN** o XML da NFS-e é bem-formado e o parse extrai campos conhecidos
- **THEN** a projeção é atualizada com os campos extraídos e o status derivado do cStat (e eventos já aplicados)

#### Scenario: Evento posterior altera situação
- **WHEN** um evento de cancelamento ou substituição é processado após a nota
- **THEN** a projeção de status da nota é atualizada conforme o tipo de evento e o evento permanece no histórico

### Requirement: Evolução de leiaute tolerante
O sistema SHALL registrar o resultado da validação por versão de XSD e SHALL conservar XML bem-formado mesmo quando a versão ou um campo ainda não for reconhecido.

#### Scenario: Versão nova de XML
- **WHEN** um documento bem-formado não corresponde aos XSD conhecidos
- **THEN** o sistema armazena o original, marca a projeção para revisão e permite o avanço seguro da página

### Requirement: Consulta paginada e filtrável
O sistema SHALL listar documentos fiscais do catálogo com paginação por cursor e filtros combináveis por tipo (`kind`), cliente, estabelecimento, papel, situação (incluindo **grupo operacional** Autorizada/Cancelada/Em revisão), competência e data de emissão, incluindo documentos capturados de fontes ADN e SEFAZ.

#### Scenario: Competência diferente da emissão
- **WHEN** o usuário filtra por competência sem informar data de emissão
- **THEN** o sistema aplica somente o período de competência e não confunde os dois campos

#### Scenario: Filtro por tipo NFS-e
- **WHEN** o cliente solicita o catálogo com `kind=NFSE` (ou omite kind)
- **THEN** o sistema retorna projeções NFS-e com `kind` e `source` preenchidos

#### Scenario: Filtro por tipo com captura SEFAZ
- **WHEN** o cliente solicita `kind=NFE` e existem NF-e capturadas via DistDFe
- **THEN** o sistema retorna as projeções NFE com `source=SEFAZ`

#### Scenario: Filtro por tipo sem captura
- **WHEN** o cliente solicita um kind ainda sem fonte habilitada
- **THEN** o sistema retorna lista vazia sem erro

#### Scenario: Busca textual de triagem
- **WHEN** o usuário informa `q` com trecho de número, nome de parte, CNPJ ou chave
- **THEN** a listagem restringe aos registros do office que casam com o critério sem retornar XML

#### Scenario: Situação operacional na listagem
- **WHEN** o usuário filtra por situação operacional Autorizada, Cancelada ou Em revisão
- **THEN** o sistema aplica o conjunto de `status` do grupo e mantém paginação por cursor com os demais filtros combináveis

### Requirement: Acesso restrito ao catálogo
O sistema MUST aplicar o escritório e o perfil do usuário em toda consulta e visualização de documento.

#### Scenario: Chave válida de outro escritório
- **WHEN** um usuário consulta diretamente uma chave pertencente a outro escritório
- **THEN** o sistema não retorna metadados nem conteúdo do documento

### Requirement: Identidade de tipo no catálogo
O sistema SHALL identificar cada item do catálogo com um `kind` de DF-e pertencente ao escopo escritural (`NFSE`, `NFE`, `NFCE`, `CTE`) e um `source` de captura quando conhecido (`ADN`, `SEFAZ`, etc.). O sistema MUST NOT listar MDF-e no catálogo operacional.

#### Scenario: Item NFS-e serializado
- **WHEN** uma projeção NFS-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFSE`, `kind_label` legível e `source=ADN`

#### Scenario: Item NF-e serializado
- **WHEN** uma projeção NF-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFE`, `kind_label` legível e `source=SEFAZ`

#### Scenario: Compatibilidade com filtro MDF-e legado
- **WHEN** o cliente solicita o catálogo com `kind=MDFE`
- **THEN** a API retorna coleção vazia e cursor nulo sem consultar tabela ou projeção MDF-e

### Requirement: Índice de catálogo multi-fonte
O sistema SHALL permitir consulta unificada de documentos de múltiplas fontes (ADN e SEFAZ) na API canônica `/api/v1/documents` sem exigir que o cliente conheça a tabela de projeção interna.

#### Scenario: Mescla de kinds na listagem
- **WHEN** o escritório possui NFS-e e NF-e capturadas
- **THEN** a listagem sem filtro de kind inclui ambas as famílias ordenadas de forma estável (ex.: por id ou issued_at desc)

### Requirement: API canônica de documentos
O sistema SHALL expor o catálogo em `/api/v1/documents` (listagem, by-client, insights, detalhe, XML) e MAY manter `/api/v1/notes` como alias compatível com o mesmo comportamento.

#### Scenario: Alias notes
- **WHEN** um cliente autenticado chama `GET /api/v1/notes` com os mesmos filtros de documents
- **THEN** a resposta é equivalente à de `GET /api/v1/documents` para o mesmo escopo

### Requirement: Projeção legível na listagem e no detalhe
O sistema SHALL expor na API de catálogo e na interface os campos de projeção necessários à triagem sem download do XML: número da NFS-e, nomes de emitente e tomador quando parseados, valor do serviço, competência, papel fiscal, situação, locais de emissão/prestação quando disponíveis e código de situação oficial quando existir.

#### Scenario: Listagem com projeção enriquecida
- **WHEN** o cliente autentica e lista notas do escritório
- **THEN** cada item inclui os campos de projeção persistidos (incluindo `number`, `issuer_name`, `taker_name`, `service_amount`, `competence`, `fiscal_role`, `status`) sem o corpo XML

#### Scenario: Detalhe sem XML embutido
- **WHEN** o usuário abre o detalhe por chave de acesso
- **THEN** a resposta inclui a projeção completa da nota, eventos e metadados do documento, e MUST NOT incluir bytes XML no JSON

#### Scenario: Fallback quando nome ausente
- **WHEN** o parse não obteve razão social de uma parte
- **THEN** a interface e a API ainda expõem o CNPJ da parte e não inventam nome

### Requirement: Agregação por cliente do escritório
O sistema SHALL oferecer consulta agregada de notas por cliente do escritório ativo (identidade do cliente e contagem de notas no escopo filtrado), aplicando o mesmo isolamento por `office_id` e os filtros de catálogo aplicáveis, sem exigir que o cliente baixe todas as páginas do cursor para montar a aba Por empresa.

#### Scenario: Resumo por cliente
- **WHEN** o usuário autorizado solicita a visão por empresa com filtros de competência ou status
- **THEN** a API devolve uma linha por cliente do escritório que possua notas no escopo, com contagem coerente e sem dados de outro office

#### Scenario: Cliente sem notas no filtro
- **WHEN** um cliente do escritório não possui notas no escopo filtrado
- **THEN** ele não aparece na agregação (ou aparece com contagem zero somente se o contrato da API documentar inclusão explícita — padrão: omitir)

#### Scenario: Sem vazamento
- **WHEN** a resposta de agregação é inspecionada
- **THEN** não há XML, vault_object_id, PFX ou material sensível

### Requirement: Situação da NFS-e alinhada ao padrão nacional
O sistema SHALL projetar a situação operacional da NFS-e a partir do `cStat` do XML nacional e dos eventos de cancelamento/substituição, e SHALL persistir o código oficial em `official_status_code` sem inventar situação.

#### Scenario: NFS-e gerada (cStat 100)
- **WHEN** o parse obtém cStat `100`
- **THEN** a projeção grava `official_status_code=100` e `status=ACTIVE` (label de UI: Gerada)

#### Scenario: NFS-e de substituição gerada (cStat 101)
- **WHEN** o parse obtém cStat `101`
- **THEN** a projeção grava `official_status_code=101` e `status=SUBSTITUTE` (label: Substituta), e MUST NOT gravar `CANCELLED` só por esse cStat

#### Scenario: cStat ausente ou desconhecido
- **WHEN** o parse não obtém cStat reconhecido
- **THEN** `status=UNKNOWN` e o XML bem-formado continua preservado

#### Scenario: Cancelamento por evento
- **WHEN** um evento de cancelamento de NFS-e é persistido para a chave
- **THEN** a projeção da nota passa a `status=CANCELLED` sem apagar o XML original

#### Scenario: Cancelamento por substituição
- **WHEN** um evento de cancelamento por substituição é persistido na nota original
- **THEN** a projeção da original passa a `status=SUPERSEDED` (label: Substituída)

### Requirement: Listagem e detalhe expõem situação legível e cStat
O sistema SHALL expor na API de catálogo e detalhe o `status` operacional e o `official_status_code`, de modo que a interface possa mostrar situação legível e o código oficial sem baixar o XML.

#### Scenario: Item de listagem
- **WHEN** o cliente lista notas
- **THEN** cada item inclui `status` e `official_status_code` (quando conhecido) sem corpo XML

#### Scenario: Detalhe
- **WHEN** o usuário abre o detalhe por chave
- **THEN** a resposta permite apresentar situação + cStat e eventos relacionados

### Requirement: Labels operacionais da situação da NFS-e
O sistema SHALL expor a situação da nota com **dois níveis de vocabulário**: (1) valor de domínio `status` estável e granular (`ACTIVE`, `SUBSTITUTE`, `CANCELLED`, `SUPERSEDED`, `JUDICIAL`, `UNKNOWN`); (2) **label operacional** de apresentação em pt-BR limitado a **Autorizada**, **Cancelada** e **Em revisão**, conforme o agrupamento definido abaixo. O sistema MUST NOT alterar o mapeamento cStat→enum nem a atualização por eventos já estabelecidos.

Agrupamento obrigatório do label operacional:

| Label | Valores de `status` |
|-------|---------------------|
| Autorizada | `ACTIVE`, `SUBSTITUTE`, `JUDICIAL` |
| Cancelada | `CANCELLED`, `SUPERSEDED` |
| Em revisão | `UNKNOWN` |

#### Scenario: Nota gerada e substituta
- **WHEN** uma nota tem `status=ACTIVE` ou `status=SUBSTITUTE`
- **THEN** o label operacional apresentado ou retornado para UI é **Autorizada**

#### Scenario: Nota cancelada ou substituída
- **WHEN** uma nota tem `status=CANCELLED` ou `status=SUPERSEDED`
- **THEN** o label operacional é **Cancelada**

#### Scenario: Nota em revisão
- **WHEN** uma nota tem `status=UNKNOWN`
- **THEN** o label operacional é **Em revisão**

#### Scenario: Enums permanecem na API
- **WHEN** o cliente consulta listagem ou detalhe
- **THEN** o payload continua incluindo o `status` granular e o `official_status_code` quando conhecido, permitindo auditoria e detalhe além do label operacional

### Requirement: Filtro de situação por grupo operacional
O sistema SHALL permitir filtrar o catálogo e a exportação de notas pelo **grupo operacional** (Autorizada, Cancelada, Em revisão), expandindo internamente para o conjunto de valores de `status` do grupo. Filtro por valor de enum único MAY permanecer para compatibilidade, mas a UI principal do produto usa o grupo.

#### Scenario: Filtrar autorizadas
- **WHEN** o usuário ou cliente filtra por situação operacional Autorizada
- **THEN** o resultado inclui notas com `status` em `ACTIVE`, `SUBSTITUTE` e `JUDICIAL` e exclui canceladas e em revisão

#### Scenario: Filtrar canceladas
- **WHEN** o usuário filtra por situação operacional Cancelada
- **THEN** o resultado inclui `CANCELLED` e `SUPERSEDED`

### Requirement: Detalhe preserva situação oficial e eventos
O sistema SHALL, no detalhe da nota, permitir apresentar em conjunto: label operacional, `status` granular, `official_status_code` (cStat) com descrição oficial curta quando aplicável, e indicação de eventos de cancelamento ou substituição que justifiquem o grupo Cancelada (ex.: substituída por outra chave), sem apagar o XML original.

#### Scenario: Detalhe de cStat 101
- **WHEN** o usuário abre o detalhe de uma nota com `official_status_code=101` e `status=SUBSTITUTE`
- **THEN** a resposta/UI permite mostrar label **Autorizada** e situação oficial de substituição gerada (cStat 101)

#### Scenario: Detalhe de nota supersedida
- **WHEN** o usuário abre o detalhe de uma nota com `status=SUPERSEDED`
- **THEN** a resposta/UI permite mostrar label **Cancelada** e texto de que a nota foi substituída (não apenas “cancelamento genérico”), quando a projeção/eventos contiverem essa informação

### Requirement: Catálogo unificado entrada e saída
O sistema SHALL listar documentos de todas as fontes habilitadas (ADN, DistDFe, import e SEFAZ-MA outbound) com kind, direction, source, channel, modo de captura e disponibilidade de XML completo, filtráveis por kind e direction.

#### Scenario: Filtro combinação NF-e
- **WHEN** `kind=NFE` e `direction=OUT`
- **THEN** retorna apenas saídas NF-e modelo 55, incluindo import e canal MA com XML completo

#### Scenario: Filtro combinação NFC-e
- **WHEN** `kind=NFCE` e `direction=OUT`
- **THEN** retorna apenas saídas NFC-e modelo 65 capturadas por import ou canal MA

#### Scenario: Descoberta sem XML
- **WHEN** uma chave MA está em recuperação pendente sem bytes originais
- **THEN** ela não aparece como documento completo nem é contabilizada como XML entregue

### Requirement: Projeção NF-e orientada a entrega
O sistema SHALL expor em listagem/detalhe de NF-e se o XML completo está disponível (`is_summary` / `has_full_xml`), status de obtenção do full e, se houver, status de manifestação opcional — sem exigir conclusiva para a nota ser “válida” no catálogo.

#### Scenario: Lista NFE
- **WHEN** GET `/documents?kind=NFE`
- **THEN** cada item indica se é resumo ou full e se o download completo é possível

### Requirement: MDF-e fora do escopo escritural
O sistema MUST NOT capturar, sincronizar, listar, detalhar, baixar ou exportar MDF-e e MUST manter qualquer estrutura legada correspondente inerte.

#### Scenario: Catálogo sem MDF-e
- **WHEN** o catálogo é solicitado sem filtro de tipo
- **THEN** nenhum item MDF-e integra a consulta ou a resposta

### Requirement: Proveniência de aquisição de saídas MA
O sistema SHALL manter aquisições múltiplas por documento com source, channel, estabelecimento, ambiente, referência externa permitida e horário, sem inventar NSU para importação, pacote MA ou consulta por sequência. A proveniência MUST ser derivada no backend e MUST NOT sobrescrever origem anterior.

#### Scenario: Import seguido de pacote MA
- **WHEN** o mesmo XML já importado é reencontrado em pacote oficial MA
- **THEN** permanece um conteúdo imutável no vault com duas aquisições auditáveis, sem duplicação ou troca de direção

#### Scenario: Aquisição de outro escritório
- **WHEN** usuário consulta proveniência por chave pertencente a outro escritório
- **THEN** o sistema não retorna existência, source, canal ou referência externa

### Requirement: Chave descoberta sem XML permanece fora do catálogo baixável
O sistema MUST manter chave/protocolo descobertos sem XML completo na operação de recuperação e MUST NOT criar `dfe_document`, projeção baixável ou sucesso de captura até persistir os bytes originais autorizados/protocolados.

#### Scenario: Recuperação pendente
- **WHEN** existe `KEY_DISCOVERED` sem XML validado
- **THEN** a chave aparece somente como pendência operacional e `has_full_xml=false`, sem endpoint de download fictício

#### Scenario: XML chega depois
- **WHEN** o XML original é validado e persistido
- **THEN** o catálogo passa a expor a projeção e o download do conteúdo real

### Requirement: Documento técnico autorizado é registro fiscal visível
Se uma operação experimental autorizar documento real, o sistema MUST armazenar XML, protocolo e eventos subsequentes, SHALL marcá-lo explicitamente como finalidade técnica e MUST NOT apagá-lo, ocultá-lo do catálogo ou tratá-lo como rollback após cancelamento.

#### Scenario: Autorização inesperada e cancelamento confirmado
- **WHEN** uma sonda resulta em autorização e depois evento de cancelamento válido
- **THEN** documento e evento permanecem imutáveis, a projeção mostra saída cancelada e a finalidade técnica é visível

#### Scenario: Cancelamento não confirmado
- **WHEN** existe documento técnico autorizado sem protocolo de cancelamento
- **THEN** o catálogo mostra a situação fiscal real e a operação mantém incidente crítico aberto

### Requirement: Divergência de bytes para a mesma chave
O sistema MUST preservar e colocar em quarentena novo XML com a mesma chave e SHA-256 diferente do canônico, sem substituir silenciosamente projeção ou conteúdo disponível.

#### Scenario: XML divergente
- **WHEN** pacote MA contém a mesma chave com bytes diferentes do documento existente
- **THEN** ambos os hashes são preservados, o novo artefato fica em revisão e nenhuma troca automática de canônico ocorre
