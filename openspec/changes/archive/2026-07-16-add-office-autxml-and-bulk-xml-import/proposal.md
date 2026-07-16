## Por quê

O escritório precisa de um canal automático e lícito para receber as **NF-e modelo 55 de saída** quando seu CNPJ for autorizado pelo emitente na tag `autXML`, além de um fallback durável para importar em massa **NF-e 55 e NFC-e 65** por XML ou ZIP. O import atual já demonstra o fluxo básico, mas é síncrono e não oferece limites seguros de expansão, quarentena, retomada nem relatório item a item; o DistDFe atual, por sua vez, usa o A1 do cliente e não representa o fluxo do escritório como terceiro autorizado.

## O que muda

- Cadastrar a identidade fiscal e uma credencial e-CNPJ A1 própria do escritório, separada das credenciais dos clientes, protegida pelo mesmo cofre de criptografia de envelope e administrável somente por ADMIN com 2FA recente.
- Criar o canal nacional `NFE_AUTXML_DISTDFE` para consultar `NFeDistribuicaoDFe` com o CNPJ e A1 do escritório e receber **NF-e modelo 55 integral** quando o emitente tiver incluído previamente esse CNPJ em `autXML`.
- Manter um único cursor `distNSU` por escritório, CNPJ-base interessado e ambiente, conservando o CNPJ completo canônico do pedido, com lock exclusivo, consumo sequencial, persistência atômica da página, idempotência, backoff oficial e bloqueio seguro em falhas repetidas.
- Vincular cada NF-e recebida ao estabelecimento do escritório pelo CNPJ completo do emitente. Documento sem vínculo inequívoco ficará em quarentena criptografada e não entrará no catálogo de nenhum cliente até resolução autorizada.
- Transformar o import de saídas existente em processamento assíncrono por lote, aceitando uma seleção mista com múltiplos `.xml` e `.zip`, inclusive ZIP multiempresa, para **NF-e 55 e NFC-e 65**.
- Validar XML autorizado, chave, modelo, emitente, protocolo, assinatura e vínculo fiscal; preservar bytes e SHA-256; impedir sobrescrita por conflito de chave; e produzir resultado paginado por item (`importado`, `duplicado`, `sem vínculo`, `inválido`, `quarentena`).
- Proteger ZIPs contra expansão abusiva, arquivos aninhados ou criptografados, links, caminhos inseguros, entradas duplicadas e XML com DTD/entidades externas, usando limites configuráveis alinhados a Nginx/PHP e à capacidade dos workers.
- Registrar a aquisição separadamente por origem (`AUTXML_DIST_NSU`, `MANUAL_XML` ou `MANUAL_ZIP`) e permitir que o mesmo documento tenha interesses distintos de saída e entrada quando dois clientes do escritório participarem da mesma operação.
- Expor configuração, saúde do cursor, progresso dos lotes, resultados e ações de resolução na interface interna, sempre sob `office_id` derivado da sessão e sem XML bruto ou segredo em respostas comuns, logs e auditoria.

## Capacidades

### Novas capacidades

- `office-fiscal-credential-management`: identidade fiscal do escritório e ciclo de vida seguro do A1 usado exclusivamente nos canais autorizados do próprio escritório.
- `nfe-autxml-office-distribution`: captura nacional de NF-e 55 integral como terceiro informado em `autXML`, com cursor central por CNPJ-base do escritório, roteamento por emitente e quarentena.

### Capacidades modificadas

- `outbound-xml-ingestion`: passa de upload síncrono básico para importação em massa, assíncrona, retomável e protegida de XML/ZIP de NF-e 55 e NFC-e 65.
- `sefaz-distdfe-sync`: passa a distinguir explicitamente o DistDFe de interesse do cliente do novo stream `autXML` do escritório, sem compartilhar credenciais, cursor, classificação ou manifestação.
- `client-credential-management`: passa a impedir que credenciais de clientes sejam usadas no canal `autXML` e a esclarecer que o A1 do escritório não substitui o A1 do cliente nos canais existentes.
- `fiscal-document-catalog`: passa a conservar proveniência multi-origem, conflitos e documentos não vinculados sem duplicar ou sobrescrever o documento canônico.
- `fiscal-document-direction`: passa a derivar interesses e direção por estabelecimento, inclusive quando o mesmo XML representa saída para um cliente e entrada para outro.
- `frontend-dashboard-experience`: passa a oferecer gestão do A1 do escritório, onboarding `autXML`, saúde do canal e acompanhamento detalhado dos lotes de importação.
- `operations-dashboard`: passa a monitorar o cursor central `autXML`, consumo indevido, falhas de decodificação, documentos sem vínculo e lotes interrompidos.

## Impacto

- **Backend:** novos modelos/migrações para identidade e credencial fiscal do escritório, cursor e execuções `autXML`, lotes/itens de importação e quarentena; extensão das interfaces de transporte DistDFe, projeção e proveniência; novos jobs Horizon, Scheduler, políticas, APIs e auditoria.
- **Frontend:** superfícies internas de Configurações, Sincronizações e Documentos para A1 do escritório, checklist de ativação `autXML`, upload múltiplo, progresso e relatório por item.
- **Infraestrutura:** limites coerentes em Nginx, PHP-FPM e workers; armazenamento temporário criptografado ou privado com descarte garantido; métricas e alertas sem payload fiscal.
- **Integrações:** Ambiente Nacional da NF-e por SOAP 1.2/mTLS. A automação usa A1 por decisão operacional do produto; o serviço oficial autentica certificado ICP-Brasil compatível com o CNPJ-base consultado.
- **Compatibilidade:** não altera a change `build-ma-outbound-nfe-nfce-capture`, não substitui o DistDFe de entrada com A1 do cliente e não remove a rota atual de importação sem uma transição compatível.

## Não-objetivos

- Capturar NFC-e modelo 65 pelo `NFeDistribuicaoDFe`: esse serviço rejeita chave de modelo diferente de 55; NFC-e será coberta pelo import XML/ZIP e, separadamente, pelos canais definidos na change do Maranhão.
- Inserir ou alterar `autXML` depois da autorização, editar o ERP do cliente ou prometer recuperação retroativa. O emitente deve configurar previamente o CNPJ do escritório no XML assinado.
- Usar o A1 do escritório como substituto do A1 do cliente em manifestação, DistDFe de entrada, captura MA ou qualquer operação fiscal do emitente.
- Emitir, autorizar, cancelar, inutilizar ou manifestar NF-e/NFC-e; automatizar portal; fazer scraping; ou introduzir portal para clientes finais.
- Aceitar arquivo sem protocolo como documento fiscal autorizado, arquivo executável, arquivo aninhado ou ZIP sem limites.
