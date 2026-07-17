## ADDED Requirements

### Requirement: Carteiras tornam proveniência e frescor inequívocos
Todo overview de módulo SHALL expor `data_origin`, rótulo sanitizado, `is_synthetic`, fonte sanitizada e `as_of` anulável. O painel MUST apresentar essa proveniência antes dos KPIs nas carteiras e por módulo no dashboard; conteúdo `DEMO` ou `SIMULATED` SHALL manter aviso persistente de que não possui validade fiscal.

#### Scenario: Carteira demonstrativa
- **WHEN** o overview retornar `data_origin=DEMO` ou `data_origin=SIMULATED`
- **THEN** a carteira SHALL exibir antes dos indicadores um aviso inequívoco de dados sintéticos e sem validade fiscal, preservado após filtro, ordenação e paginação

#### Scenario: Fonte produtiva sem observação oficial
- **WHEN** o overview retornar `data_origin=LIVE` e `as_of` nulo
- **THEN** o painel SHALL informar `Sem observação oficial` e MUST NOT usar o horário da requisição como horário do dado fiscal

#### Scenario: Origem ausente em resposta incompatível
- **WHEN** uma resposta não trouxer origem reconhecida
- **THEN** a UI SHALL apresentar `Origem não informada` e MUST NOT assumir fonte produtiva

### Requirement: Contadores formam partição completa da carteira
O overview SHALL retornar contadores para `UP_TO_DATE`, `PROCESSING`, `PENDING`, `ATTENTION`, `ERROR`, `BLOCKED`, `UNKNOWN`, `UNSUPPORTED` e `NOT_APPLICABLE`. Cada cliente no escopo SHALL contribuir para exatamente um desses estados, `total_clients` SHALL usar o mesmo escopo dos contadores e a soma dos nove contadores MUST ser igual a `total_clients`.

#### Scenario: Carteira integralmente bloqueada
- **WHEN** dez clientes no escopo forem classificados como `BLOCKED`
- **THEN** o overview SHALL retornar `total_clients=10`, `blocked=10` e zero nos demais contadores

#### Scenario: Todos os estados coexistem
- **WHEN** o escopo contiver clientes nos nove estados canônicos
- **THEN** cada cliente SHALL aparecer em um único contador e a soma dos contadores SHALL ser igual ao total

#### Scenario: Filtro de situação não distorce o overview
- **WHEN** o operador filtrar a lista por uma ou mais situações
- **THEN** somente a lista paginada SHALL aplicar esse eixo e o overview SHALL preservar os contadores do escopo formado pelos demais filtros

### Requirement: Ausência de evidência permanece desconhecida
Na ausência de evidência suficiente, a situação SHALL permanecer `UNKNOWN`. Estados `UNKNOWN`, `UNSUPPORTED`, `BLOCKED` e `ERROR` MUST NOT receber texto, ícone, KPI, link ou ação que indique sucesso, regularidade ou conclusão oficial.

#### Scenario: Cliente sem snapshot conclusivo
- **WHEN** não houver evidência suficiente para classificar o resultado fiscal do cliente
- **THEN** ele SHALL permanecer `UNKNOWN`, com a mensagem `Sem evidência oficial`, e MUST NOT contar como em dia, pendente, atenção ou erro

#### Scenario: Operação sem suporte
- **WHEN** a capacidade necessária não possuir suporte implementado ou estiver indisponível
- **THEN** a carteira SHALL apresentar `UNSUPPORTED` ou `BLOCKED` conforme a causa, sem oferecer resultado ou ação de sucesso

### Requirement: KPIs exibem somente informação operacional relevante
A faixa compartilhada SHALL manter `Total`, exibir estados com contagem maior que zero e preservar visível o estado selecionado mesmo quando seu contador for zero. Todos os nove estados SHALL continuar disponíveis no filtro estruturado, e agregados produtivos do dashboard MUST excluir módulos sintéticos.

#### Scenario: Estados zerados sem seleção
- **WHEN** uma carteira possuir dez bloqueados e zero nos demais estados
- **THEN** a faixa SHALL mostrar `Total 10` e `Bloqueados 10` sem renderizar uma sequência de KPIs zerados

#### Scenario: Filtro selecionado resulta em zero
- **WHEN** o operador selecionar um estado cujo contador seja zero
- **THEN** o KPI selecionado SHALL permanecer visível e a lista SHALL apresentar o vazio filtrado correspondente

#### Scenario: Dashboard mistura origens
- **WHEN** o dashboard receber módulos `LIVE` e módulos sintéticos
- **THEN** ele SHALL identificar a origem de cada módulo, mostrar aviso global de conteúdo sintético e MUST NOT somar módulos sintéticos em indicadores produtivos

### Requirement: Estados de loading, vazio e Office não se confundem
As superfícies compartilhadas MUST distinguir carregamento inicial, atualização, erro, carteira vazia e vazio filtrado. Valores default como `all` MUST NOT contar como filtros aplicados. O escopo SHALL vir exclusivamente do `CurrentOffice`, e a troca de Office MUST limpar origem, overview, linhas e seleção anteriores antes de renderizar a nova resposta.

#### Scenario: Carteira vazia com filtros default
- **WHEN** a carteira não possuir linhas e os filtros estiverem somente nos valores default
- **THEN** a UI SHALL exibir o vazio canônico e MUST NOT afirmar que nenhum resultado corresponde a filtros aplicados

#### Scenario: Carregamento inicial
- **WHEN** a primeira requisição da carteira estiver em andamento
- **THEN** a tabela SHALL exibir loading e MUST NOT renderizar estado vazio até a requisição concluir

#### Scenario: Troca de escritório com resposta atrasada
- **WHEN** o `CurrentOffice` mudar enquanto houver requisição do Office anterior em andamento
- **THEN** origem, contadores, linhas e seleção anteriores SHALL ser descartados, a resposta atrasada MUST NOT ser renderizada e nenhum `office_id` do cliente HTTP SHALL definir o novo escopo

### Requirement: Cada página possui responsabilidade e contrato de retorno próprios
O sistema SHALL manter um contrato backend para cada superfície abaixo, validado contra o catálogo SERPRO versionado e detalhado em `page-payload-matrix.md`. O contrato MUST declarar responsabilidade, `operation_keys`, estado oficial, `result_kind` e lugar de visualização. A página MUST NOT assumir que todo módulo retorna documento nem reutilizar ações de outra família.

| Superfície | Responsabilidade e fonte | Retorno e local de visualização |
|---|---|---|
| `/monitoring` | Priorizar problemas a partir dos overviews | `AGGREGATE`; card encaminha ao módulo originador |
| `/monitoring/simples-mei/pgdasd` | Declarações PGDAS-D por `consdeclaracao`, `consdecrec` e `consextrato` | Campos úteis na grade/detalhe; recibo, declaração, extrato ou DAS somente como artefato existente |
| `/monitoring/simples-mei/pgmei` | Dívida ativa e DAS MEI por `dividaativa` e documentos já emitidos | Débitos na grade/detalhe; DAS somente como artefato existente |
| `/monitoring/simples-mei/dasn-simei` | Informar a indisponibilidade das operações em prospecção | `UNAVAILABLE`; aviso explícito, sem dados ou documento sintético |
| `/monitoring/simples-mei/regime` | Opção e resolução do regime de apuração | Campos por ano na grade/detalhe; demonstrativo oficial somente quando persistido |
| `/monitoring/dctfweb/dctfweb` | Artefatos DCTFWeb de categoria/período conhecidos | Metadados normalizados no detalhe; recibo/declaração/DARF em PDF quando persistidos; XML bruto somente no cofre |
| `/monitoring/dctfweb/mit` | Lista, detalhe e situação de encerramento MIT | `STRUCTURED`; PA, situação, datas, avisos e total; nenhum PDF inferido |
| `/monitoring/fgts` | Fechamento e totalizações oriundas do eSocial | Metadados/recibo sanitizados; não é Integra Contador e o XML bruto não é público |
| `/monitoring/installments` | Pedidos, parcelamento, parcelas e pagamentos por modalidade | Campos estruturados no detalhe; documento de arrecadação somente quando emitido/persistido |
| `/monitoring/sitfis` | Acompanhar protocolo interno e relatório fiscal | `ASYNC_PDF`; estado na página e relatório somente após conclusão, sem protocolo público |
| `/monitoring/mailbox` | Mensagens e prazos por contribuinte | Lista estruturada que abre a mensagem pelo identificador interno autorizado |
| `/monitoring/mailbox/:id` | Conteúdo oficial da mensagem | Corpo/metadados sanitizados; a operação produtiva não anuncia anexo |
| `/monitoring/declarations` | Consolidar agenda/entrega sem criar evidência nova | `AGGREGATE`; deep-link para recibo/evidência do módulo de origem |
| `/monitoring/guides` | Consolidar guias e pagamento oficialmente correlacionado | Campos/estado na lista; guia ou comprovante somente como artefato originador |
| `/monitoring/registrations` | Vínculos cadastrais PNR | `STRUCTURED`; vínculo no detalhe, sem comprovante da consulta simples |
| `/monitoring/tax-processes` | Processos do contribuinte | `STRUCTURED`; documentos/comunicações indisponíveis enquanto as operações estiverem em prospecção |
| `/monitoring/clients/:clientId` | Consolidar os módulos de um cliente | `AGGREGATE`; cada seção encaminha ao detalhe/evidência originadora sem duplicar payload |

#### Scenario: Operação estruturada sem documento
- **WHEN** MIT, Caixa Postal, vínculo cadastral ou processo retornar campos estruturados e nenhuma operação produtiva documentar PDF
- **THEN** a página SHALL mostrar somente a projeção allowlisted no próprio detalhe e MUST NOT renderizar ação genérica de documento

#### Scenario: Operação assíncrona com documento
- **WHEN** SITFIS retornar espera/202 antes de produzir o relatório
- **THEN** a página SHALL mostrar fase e próxima tentativa e somente SHALL publicar `Ver relatório oficial` depois que o PDF estiver persistido e autorizado

#### Scenario: Capacidade não produtiva
- **WHEN** uma `operation_key` estiver fora de `PRODUCTION`, ausente do catálogo ou não implementada para a superfície
- **THEN** o contrato SHALL falhar fechado como `UNAVAILABLE`/`UNSUPPORTED`, sem fixture, linha ou ação com aparência oficial

#### Scenario: Superfície agregadora
- **WHEN** dashboard, Declarações, Guias ou detalhe do cliente apresentar um item originado em outro módulo
- **THEN** a superfície SHALL preservar a origem e encaminhar ao detalhe/evidência originadora, sem inventar uma operação SERPRO própria ou copiar payload bruto

### Requirement: Documento oficial é acessado como evidência tenant-scoped
Um item SHALL expor ação `Ver/Baixar documento oficial` somente quando houver `FiscalEvidenceArtifact` pertencente ao `CurrentOffice`. O backend SHALL produzir um descritor aditivo com disponibilidade, tipo, rótulo, MIME, observação, família/rótulo de origem sanitizados, `href` ou motivo de indisponibilidade. A UI MUST NOT construir o link por nome, ID ou convenção de módulo, e coordenadas SERPRO SHALL permanecer internas.

#### Scenario: PDF oficial disponível
- **WHEN** um PDF/recibo conclusivo estiver persistido e autorizado para o Office atual
- **THEN** o descritor SHALL trazer `available=true` e `href` tenant-scoped, e o download SHALL usar leitura autorizada, `Cache-Control: no-store`, nome/MIME sanitizados e nenhum path do cofre

#### Scenario: Artefato ausente
- **WHEN** a operação for somente estruturada, ainda estiver processando, não for produtiva ou não tiver sido coletada
- **THEN** o descritor SHALL trazer `available=false`, `href=null` e motivo público entre `STRUCTURED_ONLY`, `PROCESSING`, `NOT_SUPPORTED`, `NOT_PRODUCTION` e `NOT_COLLECTED`

#### Scenario: Tentativa de acesso cruzado
- **WHEN** um usuário tentar abrir evidência vinculada a outro Office
- **THEN** a autorização SHALL negar o conteúdo sem revelar existência, `vault_object_id`, path, hash ou outro identificador interno

### Requirement: Payload bruto nunca é superfície de produto
Respostas tenant públicas SHALL expor apenas campos de negócio tipados e allowlisted. Envelope SERPRO, `dados` bruto, Base64, XML bruto, cabeçalhos, tokens, coordenadas (`operation_key`, sistema, serviço, rota e versão), protocolos, hashes, `run_id`, `vault_object_id` e paths MUST NOT aparecer em lista, detalhe, exportação ou download público. Resposta integral/XML eventualmente retidos SHALL permanecer no `SecureObjectStore`.

#### Scenario: Mapper reconhece retorno estruturado
- **WHEN** o backend decodificar `response.dados` de uma operação estruturada
- **THEN** a API SHALL retornar somente a projeção permitida para aquela página e metadados sanitizados de origem/observação

#### Scenario: Mapper não reconhece o schema
- **WHEN** o retorno não puder ser convertido para a projeção tipada da superfície
- **THEN** o estado SHALL ser `UNKNOWN` ou `UNSUPPORTED` com mensagem pública sanitizada e MUST NOT usar o JSON bruto como fallback

#### Scenario: Retorno contém XML ou Base64
- **WHEN** DCTFWeb, eSocial ou outra fonte retornar XML/Base64 ou resposta integral sensível
- **THEN** esse conteúdo SHALL permanecer interno/cofre; a página SHALL mostrar somente campos normalizados e, para PDF autorizado, o descritor de evidência
