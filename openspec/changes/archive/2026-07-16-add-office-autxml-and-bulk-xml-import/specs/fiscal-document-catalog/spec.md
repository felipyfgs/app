## ADDED Requirements

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

## MODIFIED Requirements

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

