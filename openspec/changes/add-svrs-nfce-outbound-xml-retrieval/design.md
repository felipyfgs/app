## Contexto

A change `build-ma-outbound-nfe-nfce-capture` já entrega perfis de captura, monitoramento por série/`nNF`, descoberta de chave por consulta de protocolo, estado `KEY_DISCOVERED`/`XML_PENDING`, ingestão assistida, vault imutável, projeção de NF-e/NFC-e e controles operacionais. O gap remanescente é obter o XML original depois que a chave da NFC-e foi descoberta.

Em 2026-07-15, um smoke restrito com NFC-e modelo 65 do MA comprovou que o portal oficial da SVRS aceita o A1 do emitente por mTLS e devolve um HTML contendo o `nfeProc` em literal JavaScript materializado pelo navegador como `Blob`. Os bytes extraídos passaram por validação de chave, modelo, ambiente, protocolo, digest e assinatura XMLDSig. A SVRS documenta a funcionalidade para usuário com certificado relacionado, mas não publica WSDL/OpenAPI, contrato de estabilidade, SLA ou limites de automação.

O produto é interno do escritório contábil. Todo dado pertence ao `office_id` da sessão; o A1 é único por raiz do cliente, fica no `SecureObjectStore` e só pode ser materializado em memória. A implementação precisa ser útil em produção sem transformar o sistema em navegador automatizado nem ampliar a exceção para outros portais ou para NF-e 55.

## Objetivos / Não-objetivos

**Objetivos:**

1. Fechar automaticamente `KEY_DISCOVERED -> XML_CAPTURED` para NFC-e 65 de saída do MA usando chave conhecida e A1 relacionado.
2. Persistir exatamente os bytes que o `Blob` oficial produziria, após validação fiscal e criptográfica, com SHA-256, proveniência e idempotência.
3. Falhar de modo seguro quando o HTML, os campos, a autenticação ou a assinatura divergirem.
4. Entregar operação completa: filas, locks, limites, circuit breaker, kill switch, métricas, auditoria, inbox, API e UI.
5. Preservar o modo assistido e o upload em massa como fallback independente.
6. Permitir rollout controlado por instância, escritório, raiz e perfil.

**Não-objetivos:**

- Recuperar NF-e 55, usar SVAN para download ou habilitar outras UFs nesta change.
- Automatizar sessão Gov.br/SEFAZNET, CAPTCHA, MFA, cookie humano ou navegação genérica.
- Executar JavaScript, usar Chrome/Selenium ou salvar certificado/PEM/cookie em arquivo.
- Descobrir chaves, remontar XML, emitir/inutilizar/cancelar documento ou consultar DistDFe.
- Inferir que a HubStrom usa este mecanismo ou copiar backend proprietário.
- Garantir SLA/retensão da SVRS ou testar limites por carga em produção.

## Decisões

### D1 — Adapter HTTP dedicado, não RPA

Será criada a interface `SvrsNfceOutboundXmlRetrievalClient`, com implementação libcurl que reproduz somente o protocolo observado:

1. `GET https://dfe-portal.svrs.rs.gov.br/NFCESSL/DownloadXMLDFe` com mTLS;
2. validação da página/formulário esperados;
3. `POST /NfceSSL/DownloadXmlDfe` com `sistema=Nfce`, `OrigemSite=0`, ambiente e chave;
4. mesma conexão lógica, cookie engine apenas em memória, `Referer`, `Accept` e timeout explícitos;
5. retorno de um resultado tipado, nunca de HTML bruto para camadas superiores.

O host, esquema HTTPS e paths serão allowlisted em código/configuração protegida. Redirecionamento para outro host, downgrade TLS, falha de hostname ou TLS abaixo de 1.2 serão rejeitados. O PFX será fornecido por BLOB em memória seguindo o transporte mTLS existente; senha, chave privada, PEM e cookie não serão gravados em disco ou logs.

**Alternativas rejeitadas:** Selenium/Chrome; extensão de navegador; shell com arquivos temporários; cliente HTTP genérico que aceita URL arbitrária.

### D2 — Parser estrito do wrapper sem executar JavaScript

`SvrsNfceDownloadResponseParser` localizará somente o script/função e o literal associados ao download oficial. O decoder implementará a gramática mínima de escapes JavaScript aceita pelo portal (`\\`, aspas, controles e `\uXXXX`), rejeitando expressão, concatenação, template string, entidade ambígua ou conteúdo executável. `eval`, motor JS e `stripcslashes` genérico são proibidos.

O parser terá assinatura de versão do wrapper e limites configuráveis de resposta/XML. Alteração estrutural, múltiplos candidatos, XML truncado ou ausência de marcador resulta em `RESPONSE_CONTRACT_CHANGED`, abre circuit breaker e preserva `XML_PENDING`. Fixtures sanitizadas cobrirão o HTML observado, pequenas variações inofensivas e casos maliciosos.

Os bytes decodificados do literal — equivalentes ao conteúdo do `Blob` — serão entregues sem pretty-print, conversão de encoding, alteração de quebra de linha ou serialização DOM.

**Alternativas rejeitadas:** extrair o primeiro trecho entre `<nfeProc>` e `</nfeProc>` sem contexto; normalizar/recriar XML; executar a função remota.

### D3 — Validação em camadas antes do vault

O pipeline validará, nesta ordem:

1. tamanho, encoding suportado, XML bem-formado e ausência de DTD/entidades externas;
2. raiz `nfeProc`, namespace e versão reconhecível;
3. chave de `infNFe/@Id`, DV, `cUF=21`, modelo `65`, ambiente e chave solicitada;
4. CNPJ completo do emitente igual ao estabelecimento e pertencente à raiz do A1;
5. `protNFe/infProt/chNFe` igual à chave e protocolo autorizado (`cStat=100` ou equivalente explicitamente permitido por versão);
6. digest de referências e assinatura XMLDSig com o X.509 embutido;
7. fingerprint/cadeia e período do certificado signatário registrados como metadados, sem expor PEM;
8. SHA-256 dos bytes exatos.

XML bem-formado de versão nova poderá ser preservado somente em quarentena para análise; nunca concluirá `XML_CAPTURED` sem as validações de identidade, protocolo, digest e assinatura. Uma chave já canônica com bytes divergentes gera `DIVERGENT_BYTES`, conserva ambos os objetos sob custódia e exige revisão.

**Alternativas rejeitadas:** confiar apenas no mTLS/HTTP 200; aceitar `<NFe>` sem protocolo; validar apenas chave; substituir documento canônico silenciosamente.

### D4 — Orquestração idempotente e estados duráveis

`OutboundXmlRecoveryOrchestrator` observará números `KEY_DISCOVERED`/`XML_PENDING` elegíveis e criará uma única recuperação lógica por `office_id + profile_id + access_key + source`. A tentativa usará estados:

```text
ELIGIBLE -> QUEUED -> RUNNING
  -> CAPTURED
  -> RETRY_SCHEDULED -> QUEUED
  -> NOT_AVAILABLE_VISIBLE
  -> BLOCKED
```

Motivos tipados incluirão `A1_UNAVAILABLE`, `A1_NOT_RELATED`, `HTTP_TRANSIENT`, `AUTH_FORBIDDEN`, `REMOTE_NOT_FOUND`, `RESPONSE_CONTRACT_CHANGED`, `INVALID_XML`, `IDENTITY_MISMATCH`, `INVALID_SIGNATURE`, `DIVERGENT_BYTES`, `RATE_LIMITED` e `KILL_SWITCH`.

A transação final persistirá primeiro o objeto seguro e `document_acquisition`, depois a projeção/catálogo e por último marcará recuperação/número como capturados. Falha intermediária será reconciliável por chave+hash e não duplicará documento. `KEY_DISCOVERED` nunca será rebaixado nem confundido com captura.

**Alternativas rejeitadas:** estado somente em Redis; marcar capturado antes do vault; retry sem registro; usar NSU como cursor.

### D5 — Dados e proveniência

Será preferido estender as tabelas já criadas pela change MA, sem duplicar perfis/séries:

- `ma_outbound_retrieval_requests`: novo modo/origem `SVRS_PORTAL_BY_KEY`, chave, estado, motivo sanitizado, contadores e agendamento;
- `outbound_xml_recovery_attempts`: uma linha imutável por tentativa com correlação, tempos, classe HTTP, versão do parser e resultado, sem HTML/XML bruto;
- `document_acquisitions`: proveniência `SVRS_NFCE_DOWNLOAD_XML_DFE`, chave, horário, hash e request correlacionada;
- `outbound_number_states`: transição atômica de `XML_PENDING` para `XML_CAPTURED` somente após ingestão completa.

Chave e CNPJ continuam texto maiúsculo, sem máscara e nunca numéricos. Índices e unique constraints incluem `office_id`. Políticas e queries sempre derivam o escritório da sessão/job, nunca do payload do cliente.

### D6 — Rate limit, retentativa e circuit breaker conservadores

Valores iniciais:

- no máximo uma recuperação SVRS em voo por instância;
- intervalo mínimo global de cinco segundos entre chaves;
- intervalo mínimo de trinta segundos por raiz;
- máximo de vinte chaves por execução antes de requeue;
- timeout total por GET ou POST de trinta segundos;
- retentativas em 15 minutos, 1 hora, 6 horas e 12 horas; depois `NOT_AVAILABLE_VISIBLE`;
- `429`, `503` e falha de rede usam backoff com jitter e respeitam `Retry-After` válido;
- `403`, contrato alterado, identidade/assinatura inválida ou três falhas equivalentes abrem circuit breaker.

O circuit breaker terá escopo global para contrato/autenticação sistêmica e por raiz para credencial/identidade. Half-open executará uma única chave allowlisted. Nenhum teste procurará o limite da SVRS por estresse.

### D7 — Flags, autorização e papéis

Flags independentes e off por padrão:

- `SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED` — master da integração;
- `SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED` — enfileiramento automático;
- `SEFAZ_SVRS_NFCE_XML_PILOT_ALLOWLIST_ONLY` — obrigatório no primeiro rollout.

ADMIN com 2FA recente habilita perfil/raiz allowlisted, registra referência do mandato/decisão operacional, reseta breaker ou desliga o canal. OPERATOR pode enfileirar/reprocessar uma chave elegível e usar o fallback assistido. VIEWER somente consulta. Nenhum papel pode fornecer URL, cabeçalhos, cookie ou certificado arbitrários.

O kill switch impede novos GET/POST e novos jobs, mas não apaga tentativas, objetos, aquisições, cursores ou documentos capturados.

### D8 — API, UI e operação

A API same-origin exporá somente DTOs sanitizados para:

- estado agregado do recovery por perfil/série;
- lista paginada de pendências/tentativas;
- enfileirar ou reprocessar item elegível;
- consultar/resetar breaker conforme papel;
- acionar fallback de upload existente.

O frontend estenderá a seção `Sincronização` do cliente/estabelecimento com card “XML NFC-e via SVRS”, estado do canal, elegibilidade, última tentativa, próximo retry e pendências. A inbox distinguirá indisponibilidade transitória, contrato alterado, A1 inválido, assinatura/identidade divergente e backlog. Nunca renderizará HTML remoto, XML, PFX, senha, PEM, cookie ou `vault_object_id`.

Ao implementar Vue/Nuxt, o arquétipo `.reference/nuxt-dashboard-template` e as skills Nuxt/Nuxt UI permanecem obrigatórios.

### D9 — Observabilidade e testes anti-segredo

Métricas mínimas por canal/ambiente, sem CNPJ/chave como label:

- enfileiradas, capturadas, duplicadas, retry, bloqueadas e fallback;
- latência GET/POST/total;
- classes HTTP e motivos tipados;
- backlog e idade do item mais antigo;
- estado/abertura do breaker.

Auditoria registrará ator/job, escritório, identificadores internos, ação, resultado e correlação. Logs e traces não incluirão query/form com chave completa, HTML, XML ou material criptográfico. Testes automatizados varrerão respostas, logs, exceptions, jobs serializados e auditoria por marcadores proibidos.

### D10 — Relação com a decisão anterior

Esta change substitui parcialmente a decisão D1 de `build-ma-outbound-nfe-nfce-capture`: “usar o download humano da SVRS como API” deixa de ser alternativa rejeitada **somente** para NFC-e 65/MA, no endpoint e fluxo comprovados, por adapter HTTP isolado e controles desta change. Permanecem rejeitados RPA, navegação genérica, automação de portal MA/Gov.br, NF-e 55 e qualquer fluxo não comprovado.

## Riscos / Trade-offs

| Risco | Mitigação |
|---|---|
| HTML/JavaScript muda sem aviso | Parser versionado e fail-closed; breaker global; fallback assistido; fixture/smoke antes de reabrir. |
| SVRS bloqueia ou considera uso indevido | Limites conservadores, allowlist, backoff, identificação operacional e solicitação formal; desligamento imediato. |
| Endpoint não é contrato M2M | Flags off, rollout por gates e ausência de promessa de SLA; adapter substituível. |
| A1 vaza em arquivo/log/job | libcurl BLOB em memória, DTO de referência, redaction e testes anti-segredo. |
| Wrapper contém payload malicioso | Sem JS/eval; gramática mínima, limites de tamanho, XML parser sem rede/DTD. |
| XML não corresponde à chave/empresa | Validação de identidade, protocolo, digest e assinatura antes do vault canônico. |
| Documento já existe com bytes diferentes | Quarentena e alerta; nunca sobrescrever silenciosamente. |
| Backlog grande torna captura lenta | Rate seguro primeiro; métricas e ampliação somente após evidência, sem sacrificar a SVRS. |
| Cancelamento não aparece no `nfeProc` | Eventos permanecem captura separada; UI não infere situação final apenas do protocolo 100. |
| Dependência da change MA ainda ativa | Implementar após contratos/tabelas base estarem aplicados; migrations aditivas e checagem de pré-condição. |

## Plano de migração

1. Confirmar backup/restore recente e aplicar migrations aditivas sem enfileirar jobs.
2. Entregar interfaces, fakes, fixtures sanitizadas, parser, validação XMLDSig e testes anti-segredo com flags off.
3. Implementar transporte mTLS e smoke restrito de contrato, sem persistência fiscal automática.
4. Integrar orquestrador, vault, proveniência, projeção e reconciliação idempotente.
5. Entregar API, métricas, inbox, UI e runbooks; testar kill switch e rollback.
6. Habilitar uma raiz/uma série MA allowlisted com enfileiramento manual e poucas chaves.
7. Comparar amostra com XML original do PDV, validar duplicatas, divergências e canceladas.
8. Ativar auto-queue apenas após período piloto sem bloqueios; ampliar raízes gradualmente.

Rollback: desligar master/auto-queue, ativar kill switch, drenar/cancelar somente jobs não iniciados e preservar tentativas/documentos. Pendências voltam ao fallback assistido; não apagar objeto fiscal, aquisição, auditoria ou posição por `nNF`.

## Questões em aberto

- A SVRS/SEFAZ-MA fornecerá autorização escrita, limites e política de retenção para uso automatizado?
- Há endpoint equivalente de homologação que aceite documento/certificado de teste relacionado?
- O portal entrega XML de NFC-e cancelada sem os eventos, com eventos no mesmo pacote ou por ação separada?
- Qual é a igualdade byte a byte entre o conteúdo do `Blob` e a cópia originalmente preservada pelo PDV?
- Quais pequenas variações legítimas de wrapper existem entre produção, homologação e páginas de erro?
