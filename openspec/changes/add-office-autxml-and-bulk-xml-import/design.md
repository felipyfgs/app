## Contexto

O produto já possui dois mecanismos próximos, mas com identidades fiscais diferentes:

1. `NFE_DISTDFE` consulta documentos de interesse do **cliente**, usa o A1 da raiz do cliente, mantém cursor por estabelecimento e hoje projeta a NF-e como entrada/destinatário.
2. `IMPORT_XML` recebe arquivos enviados por OPERATOR/ADMIN e projeta saídas. A API aceita vários uploads e a UI permite XML/ZIP, porém o processamento é síncrono, o ZIP expandido não tem cotas, faltam validações fiscais e um emitente desconhecido pode produzir documento sem interesse.

O novo canal `NFE_AUTXML_DISTDFE` usa o mesmo serviço nacional de distribuição, mas representa o **escritório como terceiro autorizado**. Um único stream do CNPJ interessado pode trazer NF-e de muitos clientes; portanto não pode reutilizar credencial, cursor, job, lock nem regra de direção do fluxo do destinatário.

### Evidência oficial que limita o desenho

| Fato oficial | Consequência de produto |
|---|---|
| O grupo `autXML` é informado pelo emitente, admite até dez CNPJ/CPF e a documentação cita o contador como exemplo. | O sistema fornece o CNPJ do escritório e um checklist; não modifica XML nem ERP do cliente. |
| A NF-e integral e eventos são distribuídos ao terceiro cujo CNPJ/CPF consta em `autXML`. | Não se exige manifestação do destinatário para o papel normal de terceiro. |
| O `NFeDistribuicaoDFe` rejeita chave de modelo diferente de 55 (`cStat=618`). | A captura automática cobre somente NF-e 55; NFC-e 65 entra pelo import em massa ou por change específica de UF. |
| Novo usuário `distNSU` começa a gerar NSU no primeiro acesso e não recebe NSU retroativo; inatividade superior a 60 dias também cria lacuna sem retroação. | Ativar o stream antes do onboarding dos clientes, manter heartbeat e usar XML/ZIP para histórico e lacunas. |
| O NSU é sequencial por interessado e consultas fora de sequência ou frequentes podem produzir `cStat=656`. | Um único cursor/lock por CNPJ-base do escritório e ambiente; consumidores externos concorrentes impedem ativação sem coordenação. |
| O CNPJ-base do pedido deve corresponder ao certificado (`cStat=593`), e a NF-e não é entregue ao próprio emitente (`cStat=641`). | O canal usa identidade e A1 próprios do escritório; nunca o A1 do cliente emitente. |
| NF-e autorizada não pode ter conteúdo alterado sem invalidar a assinatura. | A inclusão de `autXML` não é retroativa; XML sem protocolo não conta como documento autorizado. |

Fontes primárias detalhadas ficam em `research/fontes-oficiais.md`.

## Objetivos / Não-objetivos

**Objetivos:**

- Capturar continuamente NF-e 55 integral e seus eventos quando o CNPJ do escritório estiver em `autXML`.
- Manter identidade fiscal, A1, cursor e operação do escritório isolados dos agregados de clientes.
- Rotear um stream central para estabelecimentos do mesmo `office_id` sem perda silenciosa e sem vazamento entre escritórios.
- Tornar o upload de múltiplos XML/ZIP um processo assíncrono, durável, observável, idempotente e seguro para NF-e 55 e NFC-e 65.
- Preservar bytes originais, assinatura, protocolo, SHA-256, proveniência e todos os interesses fiscais pertinentes.
- Entregar histórico e lacunas por importação, sem prometer backfill do Ambiente Nacional.

**Não-objetivos:**

- Capturar NFC-e 65 via `NFeDistribuicaoDFe` ou inferir que a presença da tag no leiaute 65 cria distribuição nacional.
- Inserir `autXML`, operar o ERP do emitente ou alterar documento já autorizado.
- Usar `consChNFe` como descoberta ou backfill em massa; eventual reparo por chave conhecida fica para change posterior.
- Manifestar automaticamente quando o CNPJ do escritório também aparecer como destinatário.
- Emitir, autorizar, cancelar, inutilizar ou fazer scraping de portal.
- Cadastrar o escritório como Cliente para reaproveitar `client_credentials`.
- Alterar a estratégia de captura específica do Maranhão nesta change.

## Decisões

### D1 — Identidade fiscal e A1 do escritório formam agregado próprio

Serão criados `office_fiscal_identities` e `office_credentials`, ambos tenant-aware. O MVP terá uma identidade fiscal ativa por `office_id`, com CNPJ completo de 14 caracteres e sua raiz derivada, ambos armazenados como texto em maiúsculas, sem máscara e preparados para CNPJ alfanumérico.

`office_credentials` seguirá o mesmo contrato criptográfico das credenciais de cliente: PFX e senha em `SecureObjectStore`, chave de dados exclusiva, chave mestra fora do banco e materialização do PFX somente em memória por libcurl BLOB. A validação verificará senha, validade, fingerprint, titular, finalidade compatível e igualdade da raiz entre identidade e certificado. A automação aceita apenas A1 por decisão operacional do produto; a norma fiscal não será descrita como se proibisse A3.

Somente ADMIN com 2FA recente poderá cadastrar, substituir, ativar ou revogar essa credencial. A API devolverá somente metadados públicos e não terá rota de recuperação.

**Alternativa rejeitada:** criar um Cliente para o próprio escritório. Isso misturaria elegibilidade, raízes, bloqueios e cursores, além de facilitar o uso do certificado errado em operações de clientes.

### D2 — Onboarding ativa primeiro o interessado; clientes entram depois

O onboarding seguirá esta ordem:

1. cadastrar o CNPJ fiscal e o A1 do escritório;
2. declarar se outro ERP/aplicativo já consome `distNSU` para o mesmo CNPJ-base;
3. executar a primeira consulta sequencial, registrar `activated_at` mesmo com `cStat=137` e aguardar no mínimo uma hora;
4. validar que o stream permanece saudável;
5. criar `office_autxml_enrollments` em estado `PENDING` e orientar cada emitente a incluir o CNPJ completo do escritório em todas as NF-e futuras;
6. marcar o estabelecimento `CONFIRMED` somente após confirmação operacional e, preferencialmente, primeiro XML válido observado.

A interface exibirá de forma permanente que `autXML` não é retroativo. Um hiato ou histórico anterior será direcionado ao import XML/ZIP.

**Alternativa rejeitada:** orientar todos os clientes antes de ativar o stream. Como novos usuários não recebem NSU retroativo, isso criaria perda justamente entre a primeira emissão marcada e a ativação do consumidor.

### D3 — Um cursor central por CNPJ-base e ambiente

Será criada `office_distribution_cursors`, sem tornar `channel_sync_cursors.establishment_id` anulável. Como o Ambiente Nacional controla continuidade por CNPJ-base, a chave única lógica será `(office_id, interested_root_cnpj, environment, channel)`; o registro também guardará a identidade e o CNPJ completo canônico usados no pedido. O canal será `NFE_AUTXML_DISTDFE` e duas identidades da mesma raiz não poderão abrir sequências independentes.

O cursor guardará `last_nsu`, `max_nsu_seen`, estado, último cStat/motivo sanitizado, falhas consecutivas de decode, agenda, heartbeat, lock owner e horários. Todas as instâncias compartilharão lock distribuído e banco; nenhuma poderá consultar com NSU próprio.

O job fará no máximo 20 páginas por execução, respeitará intervalo entre chamadas e reenfileirará somente quando a resposta indicar fila pendente. `cStat=137` ou `ultNSU=maxNSU` agenda quiet de pelo menos uma hora. `cStat=656` abre circuito por pelo menos uma hora; retries antecipados são proibidos porque reiniciam a penalidade. O Scheduler manterá consultas regulares muito abaixo do limite de 60 dias.

Ativação será recusada enquanto um consumidor externo usar o mesmo CNPJ-base sem estratégia explícita de transferência de ownership/cursor. Reset para zero não será ação normal de UI.

### D4 — Transporte pode ser compartilhado; contexto e processador, não

O envelope SOAP, parser de resposta, `DocumentDecoder` e mTLS de `SefazDistDfeClient` poderão ser extraídos para componentes neutros. O novo `SyncOfficeAutXmlDistDfeJob` e seu page processor serão próprios porque precisam:

- carregar `OfficeCredential`, não `ClientCredential`;
- consultar o CNPJ da identidade fiscal do escritório;
- usar `office_distribution_cursors`, não cursor de estabelecimento;
- validar o CNPJ completo do escritório no conjunto `autXML` de cada `procNFe`;
- classificar pelo emitente e nunca assumir `TAKER/IN`;
- não disparar ciência, manifestação ou reconsulta de destinatário.

O canal terá feature flag desligada por padrão e allowlist de escritório no piloto.

**Alternativa rejeitada:** chamar diretamente `SyncSefazDistDfeJob`. O job existente carrega a credencial do cliente, roteia por estabelecimento e possui semântica de destinatário incompatível.

### D5 — Página atômica: promover, vincular ou quarentenar antes do NSU

Para `cStat=138`, todos os `docZip` serão decodificados de Base64+GZip, preservados byte a byte e classificados pelo atributo de schema. O processador fará duas passagens: notas antes de eventos.

Cada item terá um destes destinos antes do commit do cursor:

- **promovido:** `procNFe` 55 íntegro, com `autXML` exato e emitente associado a estabelecimento ativo/enrolled do escritório;
- **evento vinculado:** `procEventoNFe` ligado pela chave a documento já roteado no mesmo escritório;
- **quarentena criptografada:** XML bem-formado, mas sem estabelecimento inequívoco, enrollment, tag esperada, versão suportada ou documento pai;
- **falha da página:** Base64/GZip inválido, XML malformado ou falha de persistência que impede conservar o item.

Itens em quarentena serão armazenados no cofre com SHA-256 e metadados mínimos em `fiscal_document_quarantine`; não aparecerão no catálogo comum. A resolução por ADMIN/OPERATOR poderá promover o item somente após vínculo exato dentro do mesmo escritório. Assim a página pode avançar quando o conteúdo foi preservado, sem atribuir documento ao cliente errado.

Falha da página reverte o lote e não avança NSU. Após cinco falhas consecutivas de decode o cursor passa a `BLOCKED`. Reprocessamento do mesmo NSU será idempotente.

Se a resposta trouxer `resNFe`, ou se o CNPJ do escritório também for destinatário e o Ambiente Nacional condicionar o full à manifestação, o sistema preservará o resumo como evidência/quarentena, não afirmará que o XML completo foi capturado e não manifestará automaticamente.

### D6 — Roteamento usa CNPJ do emitente, nunca seleção manual ou `autXML`

O parser retornará CNPJ do emitente, destinatário, modelo, chave, protocolo e todas as ocorrências normalizadas de `autXML/CNPJ`. A associação principal exigirá igualdade exata entre `emit/CNPJ` e um estabelecimento ativo, enrolled e pertencente ao `office_id` do cursor.

O CNPJ do escritório em `autXML` prova a autorização de acesso, não a identidade do cliente. Para import manual, `client_id`/`establishment_id` opcional será somente uma restrição: divergência produz `CLIENT_MISMATCH`; ele nunca substitui o emitente do XML.

Se o destinatário também corresponder a outro estabelecimento do escritório, será criado interesse adicional `TAKER/IN`. Eventos sem emitente só herdarão interesses de documento-pai já roteado no mesmo office.

### D7 — Importação vira batch assíncrono e durável

Serão criados:

- `document_import_batches`: office, ator, restrição opcional, estado, contadores, cotas observadas, timestamps e erro sanitizado;
- `document_import_batch_items`: arquivo/entrada sanitizada, SHA-256, chave/modelo quando seguros, estabelecimento resolvido, estado e código de resultado;
- referência privada ao upload bruto no cofre/spool protegido, nunca exposta pela API.

O endpoint canônico aceitará multipart com um ou mais XML/ZIP, persistirá os uploads em armazenamento privado, criará o batch e responderá `202` com identificador. Workers processarão itens de forma idempotente; fechar modal ou perder conexão não cancela o lote.

Estados de batch: `UPLOADED`, `QUEUED`, `PROCESSING`, `COMPLETED`, `COMPLETED_WITH_ERRORS`, `FAILED`. Estados de item: `PENDING`, `IMPORTED`, `DUPLICATE`, `UNMATCHED`, `CLIENT_MISMATCH`, `INVALID`, `UNSUPPORTED`, `QUARANTINED`, `FAILED`.

APIs de consulta devolverão resumo e itens paginados; retry será permitido para `UNMATCHED` após cadastro do estabelecimento e para falha transitória. A rota atual `/api/v1/documents/import` permanecerá como alias de transição para criação do batch; a UI migrará no mesmo release. A remoção do formato de resposta síncrono só ocorrerá após confirmar ausência de consumidores externos.

### D8 — Formatos aceitos e validação fiscal

O batch aceitará:

- `nfeProc`/`procNFe` autorizado e protocolado, modelos 55 e 65;
- `procEventoNFe` conhecido de cancelamento ou CC-e, processado depois das notas do lote;
- versões XSD novas bem-formadas, desde que identidade, assinatura e protocolo possam ser verificados, preservadas com alerta de parse.

`<NFe>` sem protocolo, `resNFe`, PDF/DANFE, HTML, modelo ausente ou diferente de 55/65 e XML sem autorização não serão promovidos como full. DTD, entidade externa e acesso de rede pelo parser serão proibidos. A validação conferirá namespace, assinatura XML, chave e DV segundo o leiaute vigente, coerência chave/protocolo, cStat de autorização, ambiente, modelo, `tpNF=1` e emitente.

O import nunca carregará A1/CSC e nunca chamará SEFAZ.

### D9 — Limites de ZIP são aplicados no preflight e durante o stream

Valores iniciais, todos configuráveis e cobertos por testes:

| Limite | Valor inicial |
|---|---:|
| Arquivos top-level por requisição | 50 |
| Total compactado por requisição | 20 MiB |
| Entradas XML por batch | 5.000 |
| XML individual descompactado | 5 MiB |
| Total descompactado por batch | 250 MiB |
| Razão máxima de compressão | 100:1 |

Nginx, PHP-FPM e Laravel terão valores coerentes, com margem para o envelope multipart. O leitor não extrairá caminhos do arquivo: abrirá uma entrada por vez, limitará bytes lidos e descartará buffers logo após o item.

Serão rejeitados ZIP aninhado detectado por magic bytes, criptografado, multidisco, symlink, caminho absoluto, `..`, NUL, nome normalizado duplicado e metadado inconsistente. Toda entrada não diretório receberá um resultado; nenhuma será ignorada silenciosamente. O upload/spool será apagado após a retenção definida, inclusive em crash por job de limpeza.

### D10 — Documento canônico, aquisição e interesse são conceitos separados

`dfe_documents` continuará imutável e deduplicado por SHA-256 no escritório. `document_acquisitions` registrará cada chegada (`AUTXML_DIST_NSU`, `MANUAL_XML`, `MANUAL_ZIP` e fontes já existentes) sem inventar NSU para import manual.

- Mesmo SHA-256: reutiliza bytes, mas cria aquisição e interesse ausentes.
- Mesma chave/tipo com bytes diferentes: não troca o canônico; preserva o candidato em quarentena.
- Corrida de unique: relê o vencedor e conclui como duplicata idempotente.
- Documento já conhecido como entrada pode receber interesse `ISSUER/OUT` de outro cliente sem recusa nem sobrescrita.

A migration desta change estenderá `document_acquisitions` se a estrutura compartilhada da change MA já estiver aplicada. Se ainda não estiver, a extração da tabela comum será coordenada antes de aplicar qualquer das duas; não serão criadas duas tabelas concorrentes.

### D11 — Direção é relativa ao interesse fiscal

`document_interests` será a fonte de verdade para `(establishment, fiscal_role, direction, channel/acquisition)`. Campos escalares legados da projeção poderão permanecer como compatibilidade, mas consultas por cliente, estabelecimento ou direção não dependerão deles.

O catálogo filtrará por interesses: `ISSUER` resulta em `OUT`, `TAKER` em `IN`. Um único documento pode satisfazer ambos os filtros para estabelecimentos diferentes, mantendo um XML canônico e exibindo os interesses no detalhe. A visão ampla do escritório deverá sinalizar todos os papéis/direções aplicáveis, sem fingir que existe uma direção global única.

### D12 — Superfícies internas e observabilidade

Configurações terá identidade fiscal/A1 do escritório e onboarding `autXML`; Sincronizações terá um card de cursor central; Documentos terá upload múltiplo, progresso, histórico, tabela por item e resolução de `UNMATCHED`/quarentena.

Inbox e métricas incluirão expiração do A1 do escritório, cStat 593/618/640/641/656, cursor sem heartbeat, cinco falhas de decode, documento sem vínculo/tag divergente e batch interrompido. Logs terão códigos estáveis, correlação e contagens, nunca XML, PFX, senha, PEM, objeto de vault, stack trace ou nome de arquivo não sanitizado.

## Riscos / Trade-offs

- **[Outro aplicativo consome o mesmo CNPJ-base]** → exigir declaração no onboarding, ownership único e transferência controlada do cursor; bloquear reset cego e alertar em 656.
- **[Cliente configura `autXML` tarde ou incorretamente]** → checklist com CNPJ copiável, enrollment observado pelo primeiro XML e import XML/ZIP para o período perdido.
- **[Novo consumidor espera recuperar 90 dias]** → mensagem explícita de ausência de NSU retroativo e ativação do stream antes dos clientes; 90 dias é retenção, não promessa de backfill.
- **[ZIP bomb ou XML malicioso]** → cotas em duas etapas, leitura limitada por entrada, parser sem DTD/rede e rejeição de estruturas perigosas.
- **[Cursor avança sobre documento sem cliente]** → conservar o XML em quarentena no mesmo commit da página; só avançar depois de todos os itens estarem promovidos ou preservados.
- **[Cursor trava para sempre por emissor ainda não cadastrado]** → `UNMATCHED` é quarentena resolvível e não falha de decode; o stream continua sem exposição ao catálogo.
- **[Mesmo documento possui entrada e saída no escritório]** → interesse por estabelecimento é autoridade; canônico e aquisição ficam separados da direção.
- **[Validação criptográfica custa CPU]** → workers dedicados, limites de batch e backpressure do Horizon; nenhuma validação pesada no request web.
- **[Mudança de resposta do import]** → endpoint/alias de transição e frontend coordenado; observar consumidores antes de retirar resposta síncrona.
- **[A1 do escritório expira]** → alertas 30/7/1 dia, substituição atômica e bloqueio apenas do cursor `autXML`, sem afetar clientes.
- **[CNPJ alfanumérico]** → texto uppercase em schema, DTOs, índices e validação; nenhum cast numérico.

## Plano de migração

1. Congelar fixtures sanitizadas e executar backup/restore antes de novas tabelas fiscais.
2. Criar schemas, índices, policies e feature flags desligadas; coordenar a tabela compartilhada `document_acquisitions` com a change MA.
3. Entregar identidade/A1 do escritório e provar que não existe rota de recuperação nem material PEM em disco/log.
4. Migrar o import para batch, ativar limites de Nginx/PHP/workers e validar a matriz XML/ZIP em ambiente local.
5. Entregar transporte/processador `autXML` com fixtures de cStat 137/138/593/618/656 e falhas de decode; manter chamadas externas desligadas.
6. Fazer smoke restrito de produção com o A1 do escritório, sem certificado de homologação em CI: primeira `distNSU`, espera de uma hora e segunda consulta.
7. Ativar allowlist apenas para o escritório e um estabelecimento piloto que ainda não tenha sido orientado; validar cursor/heartbeat.
8. Orientar o piloto a incluir o CNPJ do escritório em `autXML`, observar o primeiro `procNFe`, roteamento, evento e download.
9. Importar XML/ZIP histórico do piloto e reconciliar duplicatas/proveniências.
10. Escalar gradualmente, monitorando 656, fila, quarentena, cotas e validade do certificado.

Rollback: desligar a feature flag e o Scheduler do canal, deixar de adquirir novos uploads, manter cursores/batches/quarentena para auditoria e preservar todo XML já canônico. Nunca apagar cursor, credencial anterior ou documento para “voltar” versão. A retomada reutiliza o último NSU persistido.

## Questões em aberto

- Existe hoje algum ERP, robô ou fornecedor consumindo `distNSU` com o CNPJ-base do escritório? Essa resposta é gate do piloto, não suposição de implementação.
- Qual retenção operacional será aprovada para uploads brutos concluídos e itens de quarentena? O default deverá equilibrar auditoria, LGPD e custo, sem remover o XML canônico.
- O primeiro release precisa suportar mais de uma identidade fiscal por escritório? O desenho mantém chave por identidade, mas a UI do MVP assume uma ativa.
- Quais eventos além de cancelamento e CC-e entrarão na allowlist inicial após fixtures oficiais?
