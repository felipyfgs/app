## Contexto

O monorepo já possui importação de NF-e/NFC-e de saída, vault imutável, projeção `nfe_documents` para modelos 55/65, catálogo com `direction=OUT`, e-CNPJ A1 por raiz, transporte mTLS próprio e filas Horizon. A captura automática de emissão própria, porém, continua ausente: DistDFe não devolve ao emitente sua própria NF-e e não é canal nacional de NFC-e.

Desde 12/12/2025, a SEFAZ-MA oferece uma plataforma autenticada que prepara, de forma assíncrona, pacotes de NF-e e NFC-e por competência e tipo de operação, inclusive saída. A documentação pública não oferece contrato máquina-a-máquina; a SVRS declara que seus portais humanos não devem ser automatizados. Para consulta de situação, o Maranhão usa SVAN no modelo 55 e SVRS no modelo 65.

O MOC também permite uma reconciliação não mutante: `NFeConsultaProtocolo` pesquisa modelos 55/65 pela identidade natural emitente+modelo+série+número. Quando o `cNF` da chave candidata diverge, `cStat=562` pode concatenar a chave verdadeira para certificado da raiz autorizada. Essa devolução é opcional e a consulta não retorna o XML original. A rejeição 539 também pode revelar a chave, mas exige transmissão de documento, é facultativa e pode autorizar uma nota real se a numeração estiver livre.

Esta change, portanto, entrega primeiro o caminho oficial e não mutante, preserva o comportamento operacional de monitoramento por série e deixa qualquer mutação fiscal atrás de gates explícitos. O produto é interno do escritório contábil; o cliente não opera um portal próprio.

## Objetivos / Não-objetivos

**Objetivos:**

1. Capturar NF-e 55 e NFC-e 65 de saída de estabelecimentos MA, com XML original, assinatura e protocolo preservados no vault.
2. Configurar e reconciliar séries a partir de XML-semente autorizado, com posição por `nNF`, lacunas duráveis e retentativas a cada doze horas, até dez por número.
3. Consultar primeiro por protocolo/cStat 562, sem CSC e sem transmitir nota, validando a viabilidade em homologação e smoke de produção restrito.
4. Ingerir pacotes oficiais da plataforma SEFAZ-MA e preparar uma interface para automação somente se houver contrato oficial permitido.
5. Preservar tenancy, segredos, idempotência, trilha fiscal, rate limits, circuit breaker e visibilidade operacional.
6. Tornar o motor de sequência extensível, entregando nesta change apenas o adaptador MA.

**Não-objetivos:**

- Scraping/RPA de portal, automação de Gov.br/SEFAZNET, quebra de CAPTCHA/MFA ou armazenamento de cookie de sessão.
- Tratar consulta de protocolo, DANFE, QR Code, HTML ou XML remontado como XML de guarda.
- Usar CSC como canal de download; CSC só poderá existir para fallback ativo do modelo 65.
- Emitir notas comerciais, substituir ERP/PDV ou copiar cegamente itens/tributos do XML-semente para nova operação.
- Usar DistDFe para emissão própria ou reutilizar cursor NSU para `nNF`.
- Habilitar outro estado, portal do cliente, KMS cloud ou certificado real no CI.

## Decisões

### D1 — Recuperação oficial MA é a fonte primária

Será criada a interface `MaOutboundXmlRetrievalClient`, separada do motor de sequência. O contrato lógico terá operações de solicitar exportação de saída por competência/modelo, consultar o processamento e obter o pacote final. A primeira implementação utilizável será a ingestão assistida do ZIP solicitado pelo operador no portal oficial; um cliente automático só poderá ser ligado quando a SEFAZ-MA fornecer ou autorizar contrato máquina-a-máquina estável.

O pacote passará por um validador específico, mais estrito que o import genérico atual: somente `procNFe` ou equivalente oficial com XML de autorização/protocolo, assinatura, chave válida, `cUF=21`, ambiente, modelo 55/65 e emitente do estabelecimento será aceito. Bytes originais e SHA-256 serão persistidos antes da projeção. A mesma chave com bytes divergentes será preservada em quarentena e não substituirá silenciosamente o documento canônico.

**Alternativas rejeitadas:** automatizar a interface web; usar o download humano da SVRS como API; aceitar DANFE/HTML; prometer automação sem contrato oficial.

### D2 — Ordem de descoberta: pacote oficial, consulta 562 e somente depois fallback mutante

O motor reconciliará primeiro os XML já recuperados por modelo+série+número. Para lacunas, construirá uma chave candidata textual de 44 posições a partir de `cUF`, competência plausível e limitada, CNPJ completo, modelo, série, `nNF`, `tpEmis`, `cNF` determinístico e DV conforme o leiaute vigente. Cada candidata será persistida antes da chamada e nunca será trocada após timeout ambíguo.

Um `SefazOutboundProtocolQueryClient` próprio chamará `NFeConsultaProtocolo` com mTLS:

- modelo 55/MA: endpoint oficial SVAN resolvido por ambiente;
- modelo 65/MA: endpoint oficial SVRS resolvido por ambiente;
- `562` com `chNFe`: validar DV, UF 21, emitente, modelo, série, número e tipo de emissão antes de aceitar;
- `100`, `101`, `110` ou outro retorno com a própria chave consultada: registrar a candidata como descoberta conforme o estado fiscal retornado;
- `217`: manter `GAP_PENDING` e reagendar sem afirmar que o número nunca existiu;
- `561`, `613`, `526` ou resposta sem chave suficiente: registrar resultado limitado e não iniciar força bruta;
- `656`, timeout ambíguo ou divergência de identidade: bloquear série e acionar circuit breaker.

Consulta 562 usa A1, não CSC, não assina NF-e/NFC-e e não autoriza documento. O serviço retorna protocolo/eventos, não o XML; a recuperação continua sendo uma etapa separada.

**Alternativas rejeitadas:** começar pela 539; variar milhões de `cNF`; avançar como sucesso quando 562 vier sem chave; sintetizar `procNFe` com o protocolo retornado.

### D3 — Fallback 539 é experimental, isolado e desligado

Será modelada uma saga mutante para que o sistema consiga auditar e interromper qualquer experimento aprovado, mas `SEFAZ_MA_MUTATING_PROBE_ENABLED` permanecerá desligada por padrão. Homologação exige série exclusiva e fixtures sem valor fiscal. Produção exige, cumulativamente, parecer fiscal/jurídico atual, mandato do contribuinte, ADMIN+2FA, allowlist de CNPJ, período/série fechados, coordenação com ERP/PDV e kill switch testado.

Para lacuna histórica, a inutilização preventiva é a barreira preferida antes de transmitir uma sonda: `241` comprova número já utilizado; `102` inutiliza um número livre e encerra o fluxo sem emissão. Essa operação também é fiscalmente mutante e não poderá atingir o próximo número de uma série ativa. Somente um número comprovadamente já utilizado poderá seguir para um spike 539.

Se ocorrer autorização inesperada (`100`/`150`), o sistema persistirá imediatamente o XML/protocolo como documento real, abrirá cancelamento emergencial quando juridicamente permitido, bloqueará a série e o canal global e gerará incidente crítico. Cancelamento não é rollback: documento e evento nunca serão ocultados ou apagados. Falha ou ambiguidade no cancelamento mantém o sistema bloqueado para intervenção humana.

O XML-semente fornecerá identidade, modelo, série e parâmetros de consulta. Ele não será clonado como suposta operação comercial. Qualquer template usado em homologação será versionado, explicitamente sem valor fiscal e aprovado para o ambiente.

**Alternativas rejeitadas:** nota fictícia mensal em produção; autorizar “valor zero” como operação normal; cancelar e apagar o rastro; sondar o próximo número usado pelo PDV.

### D4 — Estado por série é separado de NSU

Não será reutilizado `channel_sync_cursors.last_nsu`. Serão introduzidas estruturas com `office_id` e isolamento por política:

- `outbound_capture_profiles`: estabelecimento, UF, ambiente, modelo, estado, consentimento/mandato, allowlist e referência opcional de CSC no vault;
- `outbound_series_cursors`: perfil, série, semente, `seed_nnf`, `discovery_position`, maior número confirmado, estado, próximo agendamento e lock;
- `outbound_number_states`: estado durável e único por perfil+série+`nNF`, candidata, chave descoberta, resultado, cStat, tentativas e horários;
- `ma_outbound_retrieval_requests`: competência, modelo, direção `OUT`, estado assíncrono, referência externa permitida e expiração;
- `outbound_capture_runs`: histórico com posição inicial/final, números consultados, chaves descobertas, XML persistidos, lacunas e resultado;
- `document_acquisitions`: proveniências múltiplas do mesmo documento, sem inventar NSU para import/portal/consulta.

A posição só avança depois que o resultado do número for persistido atomicamente. Uma lacuna pode permanecer em fila enquanto a posição segue, mas nunca desaparece: após dez tentativas vira `EXHAUSTED_VISIBLE`, continua visível e requer ação/revisão. Descoberta e recuperação são estados independentes; `KEY_DISCOVERED` não equivale a `XML_CAPTURED`.

Estados seguros principais:

```text
SEED_READY → CONSULT_QUEUED → CONSULTED
  ├─ KEY_DISCOVERED → XML_PENDING → XML_CAPTURED → COMPLETE
  ├─ GAP_PENDING → RETRY_SCHEDULED → EXHAUSTED_VISIBLE
  └─ BLOCKED
```

Saga mutante separada:

```text
MUTATION_APPROVED → INUTILIZATION_PENDING
  ├─ NUMBER_INUTILIZED → STOPPED
  └─ NUMBER_PROVEN_USED → PROBE_SENT
       ├─ REJECTED_539 → KEY_DISCOVERED
       └─ AUTHORIZED_UNEXPECTED → CANCEL_PENDING
            ├─ CANCELED
            └─ FISCAL_INCIDENT
```

### D5 — Validação da semente e credenciais por nível correto

Uma configuração de série exige XML autorizado recente, por padrão até 60 dias, com `tpNF=1`, modelo 55 ou 65, emitente exatamente igual ao CNPJ completo do estabelecimento MA, série/número válidos, assinatura e protocolo verificáveis. Upload genérico de `<NFe>` sem protocolo não habilita monitoramento.

O A1 permanece único por raiz do cliente no `SecureObjectStore` e será materializado somente em memória. Série, semente e perfil pertencem ao estabelecimento completo. CSC e ID CSC pertencem a estabelecimento+ambiente, são tipados e criptografados e só são exigidos se o fallback ativo do modelo 65 tiver sido aprovado. Modelo 55, consulta 562 e ingestão de pacote oficial nunca exigem CSC.

CNPJ e chaves permanecem texto maiúsculo, sem máscara e sem conversão numérica, incluindo leiautes alfanuméricos vigentes. API, logs, auditoria e exportação nunca devolvem PFX, senha, CSC, chave privada, PEM, cookie, token ou objeto de vault.

### D6 — Persistência documental e proveniência

O pipeline reutilizará `SecureObjectStore`, `dfe_documents`, `nfe_documents`, `nfe_events` e `NfeXmlProjectionParser`. Modelos 55 e 65 continuarão na mesma projeção, diferenciados por `model`; `kind=NFE|NFCE`, `fiscal_role=ISSUER`, `direction=OUT` e source/canal MA serão derivados no backend.

`document_acquisitions` registrará que um XML chegou por import, pacote oficial MA ou recuperação automatizada, evitando sobrescrever proveniência. Documento técnico autorizado será marcado explicitamente, aparecerá no catálogo e manterá seu evento de cancelamento. A UI e exportações não o ocultarão silenciosamente; sua finalidade e situação serão distinguíveis.

XML bem-formado de versão desconhecida será preservado com alerta de parse. XML sem assinatura/protocolo, com chave divergente ou emitente externo será rejeitado/quarentenado sem avançar estado de recuperação.

### D7 — Concorrência, limites e agendamento

- lock Redis e proteção no banco por estabelecimento+ambiente+modelo+série;
- uma chamada em voo por série e token bucket adicional por raiz/IP;
- início conservador de até 1 rps global para MA e no máximo 10 números por execução;
- retentativa padrão a cada 12 horas, no máximo 10 por número;
- spread determinístico entre estabelecimentos e fila Horizon dedicada `capture-outbound-ma`;
- circuit breaker em `656`, autorização inesperada, cancelamento pendente/falho, resposta divergente ou sequência concorrente;
- nenhum retry cego após timeout de operação mutante; primeiro reconciliar protocolo/idempotência.

Locks internos não impedem o ERP/PDV do cliente de usar uma série. Consulta não mutante pode coexistir; qualquer mutação exige série/período fechado e coordenação externa registrada.

### D8 — Autorização humana e superfície operacional

ADMIN com 2FA recente cadastra CSC, registra referência do mandato, ativa perfil, altera allowlist, reseta posição ou opera kill switch. OPERATOR pode enviar pacote oficial, disparar consulta somente leitura e acompanhar/reprocessar recuperação quando elegível. VIEWER permanece somente leitura.

O frontend seguirá o template Nuxt UI do repositório e adicionará, no detalhe do cliente/estabelecimento, configuração por modelo/série, estado do A1/CSC sem valores, semente, posição, lacunas, tentativas, última captura e bloqueios. Sincronizações mostrarão `nNF`, nunca “NSU”, para esse canal. A inbox terá itens específicos para lacuna esgotada, 562 sem chave, 656, recuperação vencida, divergência de XML, autorização inesperada e cancelamento falho.

Feature flags independentes e desligadas por padrão:

- `SEFAZ_MA_OUTBOUND_ENABLED`;
- `SEFAZ_MA_PROTOCOL_QUERY_ENABLED`;
- `SEFAZ_MA_M2M_RETRIEVAL_ENABLED`;
- `SEFAZ_MA_MUTATING_PROBE_ENABLED`.

O kill switch operacional bloqueia novos jobs; não apaga cursores, solicitações, XML ou trilha de auditoria.

### D9 — Entrega em gates

| Gate | Evidência obrigatória | Resultado |
|---|---|---|
| G0 — segurança | backup+restore testado; schema/interfaces/fixtures; flags off | nenhum acesso fiscal externo |
| G1 — pacote oficial | piloto manual de NF-e e NFC-e OUT; ZIP contém XML completo, assinatura e protocolo | ingestão oficial assistida |
| G2 — consulta homolog | 562 testado nos dois modelos e autorizadores; inexistente sem mutação; limites observados | cliente de consulta pronto |
| G3 — produção leitura | uma raiz allowlisted, uma série por modelo, máximo 10 consultas, sem CSC | captura/reconciliação restrita |
| G4 — M2M | contrato/documentação ou autorização escrita da SEFAZ-MA | recuperação automática habilitável |
| G5 — mutação | parecer jurídico/fiscal, mandato, série fechada, inutilização segura, resposta a incidente | 539 experimental habilitável |

Falhar em G1, G2 ou G4 não autoriza RPA. A feature correspondente permanece desligada e o canal continua com pacote oficial manual/importação.

## Riscos / Trade-offs

| Risco | Mitigação |
|---|---|
| Plataforma MA sem API pública | Interface M2M permanece desligada; ingestão assistida do pacote; solicitação formal à SEFAZ-MA; sem RPA. |
| 562 não concatena a chave | Registrar limitação, bloquear a estratégia para aquele modelo/autorizador e não variar `cNF` por força bruta. |
| Consulta antiga/competência incorreta | Janela de candidatas limitada e derivada da semente/pacote; lacuna visível; sem prometer cobertura ilimitada. |
| 539 facultativa ou número livre | Fallback desligado; inutilização preventiva apenas em lacuna histórica fechada; circuit breaker e incidente se autorizar. |
| Cancelamento não é rollback ou falha | Persistir documento/evento, bloquear série e canal, alerta crítico e intervenção humana. |
| Consumo indevido/bloqueio do autorizador | 1 rps inicial, lotes pequenos, doze horas entre tentativas, circuit breaker em 656 e smoke allowlisted. |
| Concorrência com ERP/PDV | Consulta read-only por padrão; mutação somente em série/período fechado com coordenação registrada. |
| CSC/PFX vazado | Envelope crypto, materialização somente em memória, respostas por estado, testes anti-segredo e sem rota de recuperação. |
| Mesmo documento com bytes diferentes | Preservar ambos, colocar divergência em quarentena e manter canônico sem sobrescrita silenciosa. |
| CNPJ/chave alfanuméricos | Texto uppercase, schemas/algoritmos versionados e fixtures da NT vigente; nunca inteiro. |
| Dois autorizadores no mesmo estado | Resolver endpoint por modelo: SVAN/55 e SVRS/65; smoke separado. |
| Documento técnico polui escrituração | Identificação explícita, retenção obrigatória, status/evento visíveis e nenhuma ocultação automática. |

## Plano de migração

1. Executar e registrar backup e restore drill antes do novo schema.
2. Criar tabelas, enums, interfaces, clients fake, métricas e flags desligadas; migrar sem enfileirar jobs.
3. Entregar validador/ingestão de pacote oficial e testar NF-e/NFC-e MA com fixtures sanitizadas.
4. Executar G1 manualmente com uma raiz piloto e comparar XML com cópia do emissor.
5. Implementar consulta 562 e executar G2 em homologação, separado por modelo/autorizador.
6. Executar G3 em produção, somente leitura e allowlisted; ampliar séries/raízes gradualmente.
7. Habilitar G4 apenas após documento formal da SEFAZ-MA; manter ingestão manual se não houver contrato.
8. Considerar G5 somente após todos os gates e aprovação; qualquer incidente desliga globalmente o fallback.

Rollback: desligar as quatro flags e o kill switch, interromper scheduler/filas, preservar estados e XML já capturados, revogar CSC se comprometido e manter auditoria. Não retroceder `nNF`, apagar documento autorizado nem reverter evento fiscal.

## Questões em aberto

- A SEFAZ-MA oferece contrato/API M2M não publicado para solicitar e baixar os pacotes assíncronos?
- O contador autorizado consegue obter, na nova plataforma, os mesmos pacotes de cada cliente sem credencial humana compartilhada?
- Os pacotes dos dois modelos contêm sempre o `procNFe` original assinado e protocolado, inclusive cancelados?
- SVAN/55 e SVRS/65 concatenam `chNFe` no cStat 562 de modo repetível para o certificado do emitente MA?
- Qual retenção temporal efetiva existe na consulta e na plataforma para documentos antigos?
- Há parecer fiscal/jurídico que permita qualquer inutilização/sondagem em produção e em quais séries fechadas?

## Referências oficiais

- [SEFAZ-MA — download de documentos por período e operação](https://www.ma.gov.br/noticias/sefaz-ma-disponibiliza-novo-sistema-para-download-de-notas-fiscais-eletronicas)
- [Portal NF-e — relação oficial de Web Services e SVAN para MA](https://www.nfe.fazenda.gov.br/portal/webServices.aspx?tipoConteudo=OUC%2FYVNWZfo%3D)
- [SVRS — serviços NFC-e e autorizador MA](https://dfe-portal.svrs.rs.gov.br/Nfce/Servicos)
- [MOC 7.0 — consulta de protocolo e cStat 562](https://www.confaz.fazenda.gov.br/legislacao/arquivo-manuais/moc7-visao-geral.pdf)
- [MOC 7.0 — regra 539](https://www.confaz.fazenda.gov.br/legislacao/arquivo-manuais/moc7-anexo-i-leiaute-e-rv.pdf)
- [Ajuste SINIEF 19/16 — NFC-e, cancelamento e inutilização](https://www.confaz.fazenda.gov.br/legislacao/ajustes/2016/AJ_019_16)
