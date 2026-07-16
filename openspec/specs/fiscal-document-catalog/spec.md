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
O sistema SHALL manter um único documento lógico por conteúdo/identidade fiscal no escritório e SHALL relacioná-lo a cada estabelecimento interessado com seu próprio papel `ISSUER`, `TAKER` ou `INTERMEDIARY`, direção e proveniência aplicáveis, preservando NSUs reais independentes por canal. Papel e direção MUST ser derivados dos participantes do XML e do estabelecimento, não da origem da aquisição nem de parâmetro do navegador; o CNPJ do escritório presente em `autXML` representa autorização de acesso e MUST NOT criar papel fiscal de Cliente.

#### Scenario: Nota entre dois clientes do escritório
- **WHEN** a mesma NFS-e é distribuída ao prestador e ao tomador cadastrados no mesmo escritório
- **THEN** o sistema mantém um documento lógico e dois interesses com seus respectivos NSUs e papéis

#### Scenario: NF-e entre dois clientes do escritório
- **WHEN** uma NF-e tem como emitente um estabelecimento A e como destinatário um estabelecimento B do mesmo escritório
- **THEN** o sistema mantém um XML canônico, interesse A com `ISSUER`/`OUT` e interesse B com `TAKER`/`IN`, sem uma aquisição sobrescrever a outra

#### Scenario: Autorização autXML do escritório
- **WHEN** o CNPJ do escritório aparece em `autXML`, mas não é emitente, destinatário ou intermediário da operação
- **THEN** a autorização fica registrada na aquisição e não cria estabelecimento fictício, Cliente ou interesse fiscal em nome do escritório

#### Scenario: Mesmo estabelecimento reencontrado por outra origem
- **WHEN** um interesse `ISSUER` já existe por import e o mesmo XML chega por autXML
- **THEN** o sistema preserva um único interesse do estabelecimento e adiciona a nova aquisição sem duplicar a nota

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
O sistema SHALL listar documentos de todas as fontes habilitadas, incluindo ADN, DistDFe de clientes, `AUTXML_DIST_NSU`, `MANUAL_XML` e `MANUAL_ZIP`, com kind, interesses/direções no escopo consultado, origem, canal e disponibilidade de XML completo, filtráveis por kind, cliente, estabelecimento e direção. Uma mesma chave MUST NOT ser duplicada na mesma página apenas por possuir múltiplas aquisições ou interesses.

#### Scenario: Filtro combinação de saída
- **WHEN** a consulta usa `kind=NFE`, `direction=OUT` e um cliente/estabelecimento emitente
- **THEN** retorna a NF-e modelo 55 vinculada ao interesse `ISSUER`, seja ela obtida por autXML, import ou outra fonte válida

#### Scenario: NFC-e importada
- **WHEN** a consulta usa `kind=NFCE` e `direction=OUT`
- **THEN** retorna NFC-e modelo 65 importada com XML completo e MUST NOT atribuir sua captura ao canal autXML

#### Scenario: Mesma chave com entrada e saída no escritório
- **WHEN** a visão do escritório inclui uma NF-e cujo emitente e destinatário são clientes distintos do mesmo office
- **THEN** a resposta mantém uma linha documental estável, expõe o resumo dos dois interesses/direções e permite navegar para cada contexto sem duplicar bytes

#### Scenario: Filtro de um cliente destinatário
- **WHEN** a mesma chave é consultada no escopo do cliente destinatário com `direction=IN`
- **THEN** a API serializa o interesse `TAKER` daquele cliente e não o papel `ISSUER` do outro cliente como se fosse seu

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

### Requirement: Separação entre documento, aquisição e interesse
O sistema MUST representar separadamente o documento fiscal canônico imutável, cada aquisição do artefato e o interesse semântico de cada Estabelecimento, sem usar uma dessas entidades como autoridade implícita das demais.

#### Scenario: Mesmo XML recebido por duas fontes
- **WHEN** bytes com o mesmo SHA-256 chegam por importação e por canal oficial
- **THEN** o sistema mantém um documento canônico, registra duas aquisições e preserva a origem de ambas

#### Scenario: Documento interessa a mais de um estabelecimento
- **WHEN** um documento canônico possui papéis fiscais válidos para dois Estabelecimentos autorizados
- **THEN** o sistema mantém interesses semânticos distintos sem duplicar os bytes nem misturar escritórios

### Requirement: Cada chegada possui idempotência específica da fonte
Toda chegada documental SHALL registrar fonte, método, instante, correlação de execução/importação, identificador oficial de transporte quando houver e resultado de validação; a chave idempotente MUST respeitar a semântica da fonte e MUST NOT colapsar chegadas legítimas apenas por `(documento, fonte, hash)`.

#### Scenario: Reprocessamento da mesma página e NSU
- **WHEN** o mesmo item da mesma página oficial é processado novamente
- **THEN** o sistema reconhece a mesma aquisição sem criar duplicata

#### Scenario: Nova captura posterior do mesmo documento
- **WHEN** o mesmo documento é legitimamente recebido em outra execução ou por outro método
- **THEN** uma nova aquisição é registrada e o documento canônico permanece o mesmo

### Requirement: Projeções tipadas com vínculo único ao canônico
Cada projeção tipada SHALL possuir vínculo inequívoco ao documento canônico e MUST ser reconstruível a partir dos bytes e eventos preservados; campos escalares legados MUST NOT concorrer como segunda autoridade após o corte.

#### Scenario: Reprojeção por parser atualizado
- **WHEN** uma nova versão de parser reprocessa XML bem-formado
- **THEN** o documento e seu SHA-256 não mudam, a projeção registra sua versão e nenhuma evidência anterior é perdida

#### Scenario: Versão oficial desconhecida
- **WHEN** o XML é bem-formado mas o XSD ou versão ainda não é reconhecido
- **THEN** o sistema preserva documento e aquisição, registra alerta de parse e não inventa projeção válida

### Requirement: Backfill documental reconciliado
A migração do catálogo MUST preservar todos os bytes, SHA-256, chaves, eventos, NSUs, papéis, direções, fontes e datas existentes e SHALL produzir mapa de correspondência e relatório de divergências.

#### Scenario: Unicidade legada ocultou proveniência
- **WHEN** uma linha legada não permite reconstruir com certeza quantas chegadas ocorreram
- **THEN** o backfill cria somente fatos comprováveis, marca a limitação de proveniência e não fabrica aquisições

#### Scenario: Mesma chave com hashes diferentes
- **WHEN** a base contém dois artefatos com a mesma identidade oficial e bytes divergentes
- **THEN** o canônico escolhido não sobrescreve o outro artefato, e a divergência permanece em custódia para revisão

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

### Requirement: Proveniência e qualidade do CT-e
O catálogo SHALL expor para CT-e a origem de aquisição (`CTE_DIST_NSU`, `CTE_AUTXML_DIST_NSU`, `MANUAL_XML`, `MANUAL_ZIP` ou `EMITTER_PUSH`) e a qualidade (`ORIGINAL`, `AUTXML_ORIGINAL` ou `AUTXML_REDACTED`) sem confundir origem com papel fiscal. XML em quarentena MUST NOT ser disponibilizado no catálogo comum.

#### Scenario: CT-e recebido pelo tomador
- **WHEN** o documento é capturado no DistDFe do cliente como tomador
- **THEN** o detalhe mostra origem `CTE_DIST_NSU`, qualidade `ORIGINAL` e papel `TAKER`

#### Scenario: CT-e emitido recebido pelo escritório
- **WHEN** o escritório captura como `autXML` uma cópia com referências substituídas por 44 noves
- **THEN** o detalhe mostra `CTE_AUTXML_DIST_NSU`, `AUTXML_REDACTED`, papel `ISSUER` do cliente e aviso textual da limitação

#### Scenario: Original posterior ao derivado
- **WHEN** um XML original do emissor é importado depois de existir cópia `AUTXML_REDACTED`
- **THEN** o original pode tornar-se o canônico baixável, preservando a aquisição derivada e sua auditoria sem apagar bytes

### Requirement: Interesses CT-e múltiplos no catálogo
Listagem, detalhe, filtro, exportação e download SHALL ser autorizados por `document_interests` do estabelecimento e MUST representar todos os papéis CT-e aplicáveis. O sistema MUST NOT armazenar uma única direção global como autoridade quando o mesmo documento pertence a mais de um cliente.

#### Scenario: Filtro por cliente e direção
- **WHEN** o mesmo CT-e tem `ISSUER/OUT` para o cliente A e `TAKER/IN` para o cliente B
- **THEN** o filtro de A por saída e o filtro de B por entrada encontram o mesmo documento sem vazamento entre clientes ou escritórios

#### Scenario: Visão ampla do escritório
- **WHEN** usuário autorizado abre o detalhe sem restringir a um cliente
- **THEN** a API apresenta todos os interesses pertencentes ao próprio `office_id` com cliente, estabelecimento, papel e direção

### Requirement: Cobertura CT-e honesta
O sistema SHALL derivar e expor cobertura CT-e por cliente e período usando estados `CAPTURED_ORIGINAL`, `CAPTURED_AUTXML_REDACTED`, `PENDING_IMPORT`, `HISTORICAL_GAP`, `BLOCKED` e `NO_ACTIVITY`. Ausência de NSU, chave ou XML MUST NOT ser apresentada como prova de inexistência de CT-e.

#### Scenario: Transportadora sem autXML
- **WHEN** o cliente emite CT-e, não configurou o escritório em `autXML` e não entregou XML
- **THEN** o período fica `PENDING_IMPORT` e a interface oferece XML/ZIP ou integração com emissor

#### Scenario: Período sem evidência
- **WHEN** os cursores estão saudáveis, mas nenhum CT-e ou sequência externa comprova atividade
- **THEN** o sistema mostra `NO_ACTIVITY` sem afirmar cobertura fiscal total

#### Scenario: Stream bloqueado por 656
- **WHEN** o circuito do CNPJ-base está aberto por consumo indevido
- **THEN** a cobertura operacional fica `BLOCKED` com próxima ação e horário sanitizados

### Requirement: Download respeita qualidade e canonicidade
O sistema SHALL disponibilizar o melhor artefato canônico autorizado para o interesse solicitado e SHALL informar sua qualidade no cabeçalho/metadado de download. Uma cópia `AUTXML_REDACTED` MUST continuar baixável quando for a única evidência preservada, mas MUST NOT ser rotulada como original exato.

#### Scenario: Somente cópia redigida disponível
- **WHEN** usuário autorizado baixa CT-e cuja única aquisição aceita é `AUTXML_REDACTED`
- **THEN** os bytes oficiais preservados são entregues com metadado e aviso de qualidade, sem reconstrução

#### Scenario: Original e redigido disponíveis
- **WHEN** as duas qualidades existem para a mesma chave
- **THEN** o catálogo oferece o original como canônico e mantém a proveniência do derivado no detalhe

### Requirement: Aquisições multi-origem sem sobrescrever o documento canônico
O sistema SHALL registrar cada obtenção de um XML como aquisição vinculada ao documento imutável, com `office_id`, origem, canal, ambiente, horário e referências legítimas de NSU ou item de lote quando existirem. As origens SHALL distinguir ao menos `AUTXML_DIST_NSU`, `MANUAL_XML` e `MANUAL_ZIP`; o sistema MUST NOT inventar NSU para importação e MUST permitir múltiplas aquisições do mesmo documento sem duplicar bytes no vault ou apagar proveniência anterior.

#### Scenario: Import seguido de autXML
- **WHEN** o mesmo XML, com o mesmo SHA-256, já importado manualmente é recebido depois por `AUTXML_DIST_NSU`
- **THEN** permanece um documento canônico no vault com aquisições distintas de importação e distribuição, incluindo o NSU real somente na aquisição autXML

#### Scenario: XML repetido em dois lotes de importação
- **WHEN** o mesmo XML aparece novamente em outro lote ou ZIP
- **THEN** o item é reportado como duplicado, o vault não duplica conteúdo e o histórico do novo lote permanece auditável

#### Scenario: Aquisição de outro tenant
- **WHEN** uma requisição tenta associar aquisição ou item de lote a documento de outro escritório
- **THEN** o sistema rejeita a associação sem revelar chave, hash, origem ou existência externa

### Requirement: Canônico por chave e quarentena de bytes divergentes
O sistema MUST manter no máximo um conteúdo canônico por `office_id`, tipo/modelo e chave de acesso. Quando novo XML possui a mesma chave e SHA-256 diferente, SHALL preservar os novos bytes criptografados como aquisição em quarentena, MUST NOT substituir `dfe_document_id`, projeção, eventos ou download canônicos e SHALL exigir resolução auditada antes de qualquer promoção.

#### Scenario: Mesma chave e bytes divergentes
- **WHEN** importação ou autXML entrega XML com chave já conhecida e SHA-256 diferente do canônico
- **THEN** o novo artefato recebe estado de quarentena e o download comum continua devolvendo exatamente os bytes canônicos anteriores

#### Scenario: Concorrência de duas origens
- **WHEN** import e autXML tentam criar simultaneamente o primeiro documento para a mesma chave e os mesmos bytes
- **THEN** a transação e as constraints produzem um único canônico e preservam as duas aquisições idempotentes

#### Scenario: Promoção após revisão
- **WHEN** usuário autorizado resolve uma divergência com motivo e evidência válidos
- **THEN** qualquer alteração de canônico é atômica, auditada e não apaga os bytes, hashes ou aquisições anteriores

### Requirement: Quarentena fora do catálogo operacional
O sistema SHALL preservar em quarentena XML íntegro que não possa ser vinculado inequivocamente a estabelecimento do escritório, que não contenha a autorização `autXML` esperada ou que falhe em invariantes de chave/protocolo/assinatura. Enquanto não resolvido, o artefato MUST NOT criar interesse fiscal, projeção de nota, contagem de documento entregue, exportação ou download pelo catálogo comum.

#### Scenario: Emitente não cadastrado
- **WHEN** XML válido recebido por autXML ou import possui emitente sem estabelecimento correspondente no escritório
- **THEN** os bytes são preservados em quarentena com motivo tipado e nenhuma empresa do escritório recebe a nota no catálogo

#### Scenario: Resolução após cadastro
- **WHEN** o estabelecimento correto é cadastrado e usuário autorizado resolve o item de quarentena
- **THEN** o sistema revalida os bytes preservados, cria o interesse no mesmo office e registra ator, motivo e horário sem reupload obrigatório

#### Scenario: Listagem comum
- **WHEN** usuário lista, agrega, exporta ou baixa documentos pelo catálogo normal
- **THEN** itens em quarentena não aparecem nem são contabilizados, independentemente de o usuário conhecer sua chave
