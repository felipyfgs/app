## Contexto

O backend já possui `HttpSefazCteDistDfeClient`, `SyncSefazCteDistDfeJob`, `CteDistDfePageProcessor` e `CteXmlProjectionParser`, protegidos pela flag `SEFAZ_CTE_ENABLED`. O fluxo atual consulta `distNSU` com A1 do cliente, persiste o `docZip` antes do cursor e projeta `kind=CTE`, porém reduz remetente/destinatário a `TAKER`, não extrai expedidor/recebedor, assume `TAKER/IN` quando não encontra papel, permite inferir `ISSUER/OUT` mesmo que o Ambiente Nacional não distribua o CT-e principal ao próprio emitente e não possui `consNSU` para reconciliar lacunas conhecidas.

A comunidade e a NT 2015.002 convergem: o XML completo é distribuído sem manifestação a remetente, destinatário, expedidor, recebedor e tomador; o CT-e principal não é distribuído ao próprio emitente; e um terceiro em `autXML` pode recebê-lo. Para `autXML`, o Ambiente Nacional substitui por `999...` as chaves de NF-e/CT-e referenciadas, portanto o artefato recebido pode não ser byte a byte idêntico ao original assinado. Não existe `consChCTe`; portais SVRS/Nacional são superfícies humanas e não serão automatizados.

A change `add-office-autxml-and-bulk-xml-import` já define identidade/A1 do escritório, cursor central de distribuição NF-e, batch seguro XML/ZIP, quarentena e proveniência. Esta change depende dessas fundações e as estende para CT-e sem criar outra credencial do escritório nem outro mecanismo de upload.

## Objetivos / Não-objetivos

**Objetivos:**

- capturar CT-e modelo 57 de interesse dos clientes por todos os cinco papéis oficiais não emitentes;
- capturar CT-e emitido por clientes quando o escritório estiver em `autXML`;
- cobrir emitidos ausentes por XML/ZIP ou entrega autenticada do ERP/emissor;
- preservar papel por estabelecimento, proveniência, qualidade do artefato, eventos e direção fiscal correta;
- consumir NSU sem salto, competição interna ou bloqueio evitável;
- tornar lacunas e limites visíveis, sem afirmar cobertura total quando a fonte não existe;
- validar com fixtures e smoke restrito de produção sem certificado real em CI.

**Não-objetivos:**

- scraping, automação de navegador, CAPTCHA, gov.br ou endpoint interno de portal;
- emissão, autorização, cancelamento, inutilização ou geração de eventos CT-e;
- descoberta nacional de CT-e próprio por chave, número, série ou data;
- recuperação retroativa garantida fora do stream disponível;
- catálogo escritural de MDF-e ou projeção completa de CT-e OS/GTV-e;
- dependência obrigatória de biblioteca comunitária ou API comercial.

## Decisões

### D1 — Dois streams oficiais e duas identidades de consulta

O canal `CTE_DISTDFE` continuará sendo um cursor por estabelecimento, ambiente e CNPJ completo consultado, autenticado pelo A1 da raiz do cliente. Ele aceita somente interesses em que o cliente aparece como remetente, destinatário, expedidor, recebedor ou tomador.

O novo canal `CTE_AUTXML_DISTDFE` terá um único cursor por `office_id`, CNPJ-base fiscal do escritório, ambiente e canal, conservando o CNPJ completo canônico usado no pedido e utilizando a credencial do escritório. Ele percorre uma fila única contendo CT-e de todos os emitentes que autorizaram o escritório e roteia cada documento depois da validação.

Os dois canais usam o mesmo contrato `SefazCteDistDfeClient`, parser SOAP e decoder, mas jobs/page processors próprios. A alternativa de usar o A1 de cada cliente também para CT-e emitido foi rejeitada porque o Ambiente Nacional não distribui o documento gerado pelo próprio CNPJ. A alternativa de criar um cursor do escritório por cliente foi rejeitada porque dividiria artificialmente uma única sequência do ator interessado.

### D2 — `distNSU` é o fluxo; `consNSU` é somente reparo conhecido

A interface CT-e ganhará `distByLastNsu` e `findByNsu`. O Scheduler usa exclusivamente `distNSU`; `consNSU` só pode ser acionado para um NSU conhecido, registrado como lacuna ou reprocessamento, nunca como varredura ou descoberta. Não será implementado `consChCTe`.

O cursor avança para o `ultNSU` da resposta somente depois que todos os itens da página estiverem em destino durável. NSU individual de `docZip` nunca substitui o cursor. `cStat=137` ou fila alcançada impõe quiet mínimo de uma hora; `656` abre circuito para a chave `serviço CT-e + CNPJ-base consultado + ambiente` e nova tentativa antes do prazo é proibida. Páginas pendentes usam intervalo configurável conservador e limite de 20 por job.

### D3 — Papéis CT-e são interesses múltiplos, não um único campo inferido

O parser extrairá `emit`, `rem`, `dest`, `exped`, `receb`, a identidade efetiva do tomador (`toma3`/`toma4`) e todos os `autXML`. Para cada estabelecimento do mesmo escritório que corresponda exatamente a uma dessas identidades, o sistema criará um `document_interest` tipado:

| Papel | Direção do CT-e para o cliente |
|---|---|
| `ISSUER` | `OUT` |
| `SENDER` | `IN` |
| `RECIPIENT` | `IN` |
| `EXPEDITOR` | `IN` |
| `RECEIVER` | `IN` |
| `TAKER` | `IN` |
| `AUTXML` | não define cliente; comprova autorização do escritório |

Um documento pode criar múltiplos interesses para o mesmo ou diferentes estabelecimentos sem duplicar os bytes. O sistema não atribuirá `TAKER` quando não houver correspondência: ausência ou ambiguidade vira quarentena. A direção global armazenada hoje em `cte_documents` será tratada como projeção compatível; a autoridade passa a ser o interesse por estabelecimento.

### D4 — O emitente é exclusão no stream do próprio cliente

Se `emit/CNPJ` for igual ao CNPJ consultado no canal `CTE_DISTDFE`, o item não será promovido como CT-e de saída. Ele será preservado em quarentena como `UNEXPECTED_OWN_ISSUER_DOCUMENT`, pois o contrato oficial diz que o XML principal não é distribuído ao gerador; isso evita transformar fixture, payload anômalo ou erro de parser em evidência de cobertura.

No canal do escritório, `emit/CNPJ` deve corresponder univocamente a um estabelecimento ativo do mesmo `office_id`, e o CNPJ completo canônico do escritório deve aparecer em `autXML`. Nesse contexto, cria-se interesse `ISSUER/OUT` para o cliente emitente e aquisição `CTE_AUTXML_DIST_NSU`.

### D5 — Original e derivado oficial têm qualidades distintas

Aquisições pelos cinco papéis do cliente e imports de `cteProc` íntegros usam qualidade `ORIGINAL`. Aquisições exclusivamente por `autXML` serão classificadas assim:

- `AUTXML_ORIGINAL`, se o XML não contém substituição oficial e a assinatura é válida;
- `AUTXML_REDACTED`, se referências nos grupos previstos vierem como 44 noves pelo canal oficial;
- `QUARANTINED`, se houver alteração incompatível, assinatura inválida sem padrão oficial, chave/protocolo divergente ou origem não comprovada.

Para `AUTXML_REDACTED`, os bytes recebidos do Ambiente Nacional são preservados sem “reconstrução”. O resultado da assinatura será registrado separadamente como válido, inválido ou não verificável por redação oficial; o sistema não chamará o derivado de original exato. A alternativa de substituir `999...` por chaves descobertas foi rejeitada porque criaria um XML que nunca foi entregue nem assinado naquela forma.

### D6 — Validação e persistência em duas passagens

Cada `docZip` será decodificado Base64+GZip, terá SHA-256 calculado e será classificado pelo atributo `schema`. A página será processada em duas passagens: CT-e principal antes de eventos. Para `cteProc`, serão validados XML bem-formado, namespace, modelo 57, chave/DV, identidade de `infCte`, protocolo, ambiente, cStat de autorização, assinatura conforme a qualidade aplicável e coerência do emitente.

Eventos protocolados serão preservados, relacionados por chave e sequência e projetados sem sobrescrever o documento principal. Evento sem pai conhecido fica em quarentena resolvível; como os bytes foram preservados, ele não bloqueia indefinidamente o cursor. Falha Base64/GZip, XML irrecuperável ou falha de cofre/transação impede a página inteira; cinco falhas consecutivas bloqueiam o stream.

### D7 — Import e entrega do emissor compartilham a mesma ingestão

O batch de `outbound-xml-ingestion` passará a aceitar XML direto e ZIP com `cteProc` modelo 57 e `procEventoCTe`. A associação principal usa `emit/CNPJ` completo dentro do `office_id`; um filtro opcional de cliente é apenas restrição de conferência. Mesmo hash reutiliza documento e acrescenta aquisição; mesma chave com bytes divergentes preserva o canônico e põe o candidato em quarentena.

ERPs/emissores poderão usar um endpoint autenticado de entrega que cria o mesmo batch ou item com origem `EMITTER_PUSH`. A autenticação será uma credencial de integração por escritório, com segredo exibido uma única vez, persistido somente por hash, escopo mínimo `cte:ingest`, expiração, rate limit e revogação por ADMIN com 2FA recente; o `office_id` será derivado do principal, nunca do payload. Não haverá adaptador comercial embutido: conectores futuros apenas enviam os bytes autorizados a esse contrato. Upload e push nunca chamam serviços fiscais mutantes.

### D8 — Cobertura é uma projeção honesta, não uma promessa binária

Por cliente e período, o backend derivará:

- `CAPTURED_ORIGINAL`: CT-e íntegro disponível;
- `CAPTURED_AUTXML_REDACTED`: derivado oficial disponível, com limitação explícita;
- `PENDING_IMPORT`: cliente emitente sem fonte automática suficiente;
- `HISTORICAL_GAP`: ativação tardia, inatividade ou janela perdida;
- `BLOCKED`: certificado, decode, `656`, conflito externo ou validação;
- `NO_ACTIVITY`: nenhuma evidência de CT-e no período, sem afirmar inexistência fiscal.

O cálculo usa aquisições, interesses, cursor, datas e pendências; chave descoberta ou resumo nunca equivale a XML capturado.

### D9 — Operação separa serviços e coordena consumidores

NF-e DistDFe e CT-e DistDFe possuem governadores separados porque são serviços distintos. Dentro do CT-e, todos os workers que consultam o mesmo CNPJ-base/ambiente compartilham lock e circuito. O onboarding exige declarar se outro software consome a distribuição do mesmo ator; conflito de cursor ou `656` recorrente bloqueia a automação até reconciliação.

Logs e métricas incluem IDs, canal, cStat, atraso, contagens, último/max NSU e código sanitizado. Nunca incluem XML, `docZip`, PFX, senha, PEM, chave privada, cabeçalho mTLS ou nomes de arquivo não sanitizados.

### D10 — UI segue o dashboard existente

No frontend Nuxt, a implementação usará o arquétipo do template já fixado. Configurações exibirá o checklist para incluir o CNPJ copiável do escritório em `autXML`; Sincronizações terá cards separados para CT-e do cliente e CT-e `autXML`; Documentos mostrará papel, origem, qualidade, cobertura, import em lote e quarentena. ADMIN com 2FA recente controla A1/flags e resolução sensível; OPERATOR importa e reprocessa itens elegíveis; VIEWER é somente leitura.

### D11 — Teste real é gate de ativação

CI usará fixtures SOAP/XML sanitizadas para todos os papéis, `autXML`, redação `999...`, eventos, duplicatas, lacunas, 137/138/593/656 e falhas de decode. Nenhum certificado real entra em fixture ou secret de CI.

Antes da produção, um smoke restrito usará A1 já custodiado para: recuperar pelo menos um CT-e recente em um dos cinco papéis; provar que o cursor avança pelo `ultNSU`; confirmar o comportamento de fila vazia; e, quando houver documento piloto, validar `autXML`, qualidade e assinatura. O canal permanece em feature flag/allowlist até o smoke ser registrado sem conteúdo fiscal bruto.

## Riscos / Trade-offs

- **[Cliente é transportadora emitente e não configurou `autXML`]** → marcar `PENDING_IMPORT` e aceitar XML/ZIP ou `EMITTER_PUSH`; não inventar recuperação nacional.
- **[Cópia `autXML` contém referências `999...`]** → preservar como `AUTXML_REDACTED`, exibir limitação e manter fallback por original do emissor quando necessário.
- **[Assinatura não valida após redação oficial]** → registrar `NOT_VERIFIABLE_OFFICIAL_REDACTION`, restringir essa exceção ao padrão oficial recebido diretamente e exigir smoke antes da ativação ampla.
- **[Outro software consome o mesmo ator]** → ownership único, lock distribuído, circuito por CNPJ-base e reconciliação manual; nunca resetar cursor cegamente.
- **[Ambiente Nacional atrasa ou deixa de compartilhar]** → histórico de sincronização, gap repair por `consNSU` conhecido, estado de cobertura e import assistido.
- **[Papel incorreto gera direção errada]** → igualdade exata por CNPJ, interesses múltiplos, sem fallback para `TAKER`, fixtures de todos os grupos e reprocessamento das projeções atuais.
- **[Evento chega antes do CT-e]** → custódia imutável e quarentena resolvível sem descartar o NSU.
- **[Change de fundação ainda não aplicada]** → tarefas dependentes aguardam `add-office-autxml-and-bulk-xml-import`; migrações e contratos compartilhados não serão duplicados.
- **[Volume central do escritório cresce]** → páginas limitadas, Horizon dedicado, backpressure, índices por stream/NSU e processamento em duas passagens.

## Plano de migração

1. Aplicar ou confirmar as fundações de identidade/A1 do escritório, aquisições, batch e quarentena da change dependente.
2. Congelar fixtures atuais, executar backup/restore e adicionar enums/campos/índices de papéis, qualidade e canal CT-e sem ativar Scheduler.
3. Evoluir parser, cliente (`consNSU`) e processador do canal do cliente; executar reprocessamento das projeções existentes e retirar `ISSUER/OUT` inferido indevidamente.
4. Estender ingestão XML/ZIP e criar o contrato `EMITTER_PUSH`, mantendo CT-e atrás de feature flag.
5. Criar cursor/job/processador `CTE_AUTXML_DISTDFE` reutilizando a credencial do escritório e validar com fixtures.
6. Entregar APIs e UI de onboarding, saúde, cobertura, proveniência e pendências.
7. Executar smoke restrito com um cliente não emitente e, depois, um emitente piloto com `autXML` configurado.
8. Ativar allowlist por escritório/cliente, observar ao menos um ciclo de fila e só então ampliar gradualmente.

Rollback: desligar flags e Scheduler dos canais CT-e, impedir novos pushes/uploads se necessário e preservar cursores, aquisições, quarentenas e documentos já custodiados. A reversão não apaga NSU nem XML; a retomada continua do cursor confirmado.

## Questões em aberto

- Qual será o resultado criptográfico real da assinatura no primeiro CT-e `AUTXML_REDACTED` recebido em produção? O smoke decidirá entre `VALID` e `NOT_VERIFIABLE_OFFICIAL_REDACTION`, sem alterar a regra de preservação.
- Existe hoje um cliente piloto com CT-e recente contendo o CNPJ do escritório em `autXML`? Se não, o gate dessa parte permanecerá pendente até uma emissão futura autorizada pelo cliente.
- A migração compartilhada de `document_acquisitions` da change dependente já terá sido aplicada quando esta change entrar em implementação? As tarefas devem conferir o estado real antes de criar qualquer coluna ou índice.
