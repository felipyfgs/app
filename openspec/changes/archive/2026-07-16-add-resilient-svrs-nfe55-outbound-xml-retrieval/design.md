## Contexto

O sistema já descobre chaves de NF-e 55 de saída e possui ingestão canônica de XML, cofre, certificados A1 e contingência por upload. O `NFeDistribuicaoDFe` não entrega o XML completo ao próprio emitente (`cStat 641`), portanto ele não resolve sozinho a recuperação retroativa de saídas quando o emissor perdeu o arquivo.

Em 2026-07-15 foi validado, sem persistir conteúdo fiscal nem expor segredos, que `GET https://dfe-portal.svrs.rs.gov.br/NFESSL/DownloadXMLDFe` autentica por mTLS e apresenta formulário `POST /NfeSSL/DownloadXmlDfe` com `sistema=Nfe`, `OrigemSite=0`, `Ambiente=1` e `ChaveAcessoDfe`. A tentativa com uma NF-e relacionada ao A1 chegou ao contrato autenticado, mas o HTML respondeu `IP não autorizado devido múltiplas consultas`; portanto ainda não foi comprovado neste ambiente o retorno bem-sucedido do XML NF-e 55 nem o caso de emitente MA.

A SVRS publica a existência do download para certificado relacionado à NF-e, mas não publica WSDL/OpenAPI, SLA, retenção, limite de automação, escopo do bloqueio nem cooldown do formulário `NFESSL`. O limite oficial de vinte consultas por hora e bloqueio de uma hora é regra do `NFeDistribuicaoDFe`, não evidência de limite do `NFESSL`. A arquitetura deve tratar os limites deste design como proteção interna deliberadamente defensiva.

O host e o IP público são compartilhados pelos fluxos NF-e 55 e NFC-e 65. O limitador isolado previsto em `add-svrs-nfce-outbound-xml-retrieval` — 5 segundos globais, 30 segundos por raiz e 20 chaves por job — ainda não foi pilotado e precisa ser substituído antes de qualquer nova chamada real.

### Evidências e fontes

- [SVRS — Download Arquivo da NF-e com certificado relacionado](https://dfe-portal.svrs.rs.gov.br/Nfe/Noticias/1893).
- [Portal SVRS — serviço Download XML](https://dfe-portal.svrs.rs.gov.br/NFE/).
- [Portal Nacional — regras de uso indevido do NFeDistribuicaoDFe](https://www.nfe.fazenda.gov.br/portal/informe.aspx?Informe=0cu%2FyBLKrCs%3D&ehCTG=false), aplicáveis ao web service, não presumidas para o formulário.
- [NT 2014.002 — Distribuição de DF-e](https://www.nfe.fazenda.gov.br/Portal/exibirArquivo.aspx?conteudo=uWO2d%2FgTuWg%3D), incluindo consulta pontual, janela retroativa e `cStat 656`.
- [NT 2013.005 — download por certificado relacionado e `autXML`](https://www.nfe.fazenda.gov.br/Portal/exibirArquivo.aspx?conteudo=gtDBDppETfg%3D).
- [sped-nfe #534 — XML próprio, portal e `autXML`](https://github.com/nfephp-org/sped-nfe/issues/534).
- [sped-nfe #511 — recuperação pelo portal e risco de XML reconstruído](https://github.com/nfephp-org/sped-nfe/issues/511).
- [Java_NFe #205 — limitação do DistDFe para emitente](https://github.com/Samuel-Oliveira/Java_NFe/issues/205).
- [ACBr — download por certificado relacionado](https://www.projetoacbr.com.br/forum/topic/48084-autoriza%C3%A7%C3%A3o-de-donwload-de-xml-nfe/).

## Objetivos / Não-objetivos

**Objetivos:**

- Recuperar `nfeProc` original de NF-e 55 de saída por chave conhecida, com A1 relacionado e validação completa antes da ingestão.
- Evitar bloqueio por concorrência ou soma invisível de NF-e/NFC-e no mesmo host/IP.
- Parar de forma segura diante de bloqueio textual mesmo com HTTP 200 e encaminhar o trabalho à contingência.
- Permitir rollout mensurável, reversível e incapaz de ultrapassar limites por falha de Redis, retry ou ação manual.
- Reusar o cofre, catálogo, auditoria, papéis, tenancy e componentes implementados para o canal NFC-e.

**Não-objetivos:**

- Usar DistDFe como fonte de saída do emitente, varrer chaves, reconstruir XML ou automatizar portal com navegador/CAPTCHA.
- Descobrir o limite do `NFESSL` por carga, contornar bloqueio com IP/proxy/certificado ou prometer cobertura/retensão.
- Executar smoke com certificado real em CI ou registrar PFX, senha, PEM, cookie, HTML/XML bruto ou chave fiscal completa em telemetria.
- Alterar a autorização de NF-e, emitir, cancelar ou manifestar documentos.

## Decisões

### D1 — Canal pontual, não fonte de captura em massa

`SvrsNfe55DownloadClient` implementará somente o contrato allowlisted `NFESSL/DownloadXMLDFe`. A entrada será uma chave modelo 55 já descoberta e vinculada a escritório, raiz, ambiente e credencial elegíveis. Não haverá busca por intervalo, numeração ou lista no portal.

Alternativas rejeitadas: API comercial; DistDFe do emitente, por `641`; XML reconstruído, pois não preserva a assinatura/original; automação do Portal Nacional com CAPTCHA/token humano; e RPA genérico.

### D2 — Roteamento evita o portal sempre que possível

`OutboundXmlRecoveryRouter` aplicará, por chave, a seguinte ordem:

1. documento canônico ou objeto íntegro já presente no vault;
2. ingestão recebida por emissão/importação, XML/ZIP, pacote oficial ou `autXML`/DistDFe;
3. recuperação pontual SVRS, se todos os gates estiverem abertos;
4. contingência assistida, mantendo `XML_PENDING` e motivo acionável.

Uma ingestão válida em qualquer fonte anterior cancela de forma idempotente a tentativa SVRS ainda não iniciada. DistDFe continua com cursor e regras próprias; somente o tráfego do portal entra no governador desta change.

### D3 — Governador único por coorte de egress

`SvrsPortalEgressGovernor` será obrigatório para qualquer GET/POST ao host `dfe-portal.svrs.rs.gov.br`, independentemente de modelo, escritório, fila ou adapter. O estado durável do breaker fica no PostgreSQL; exclusão mútua e janelas atômicas usam Redis. Falha ou inconsistência do coordenador fecha o canal sem chamada remota.

`SVRS_EGRESS_COHORT_ID` identifica a coorte que compartilha o mesmo IP/NAT. Instâncias na mesma coorte precisam compartilhar o coordenador; caso isso não seja garantido, somente uma implantação poderá habilitar o canal. URL, host, path, DNS/IP de destino e coorte não podem vir da API do cliente.

Defaults do piloto, configuráveis somente por deploy e nunca eleváveis pela UI:

- master e auto-queue desligados;
- uma transação lógica em voo por coorte;
- uma chave por job e sem retry na mesma execução;
- intervalo mínimo de 120 segundos entre transações lógicas globais;
- intervalo mínimo de 15 minutos entre transações da mesma raiz;
- máximo de 10 exchanges HTTP por hora e 50 por dia por coorte;
- máximo de 6 chaves por dia por raiz;
- cada GET, POST, redirecionamento manual ou nova autenticação consome um exchange antes de sair;
- transação normal limitada a um GET e um POST, sem seguir redirect automaticamente;
- jitter determinístico para distribuir backlog.

Esses números não representam limites oficiais. Aumento exige nova decisão operacional versionada, período sem bloqueios, evidência observável e revisão desta especificação; o software não fará auto-ramp.

### D4 — Sessão efêmera e orçamento antes de materializar o A1

O job reserva todos os exchanges necessários antes de abrir o `SecureObjectStore`. O PFX será materializado somente em memória, entregue ao libcurl por BLOB, e descartado ao final. Cookies existirão apenas em memória durante uma transação e nunca serão compartilhados entre raízes ou gravados.

O fluxo inicialmente comprovado permanece GET + POST. O GET não poderá ser repetido para diagnóstico e o POST direto só poderá substituir o par após fixture e smoke específico demonstrarem equivalência. Redirect inesperado, host/path diferente ou pedido de interação humana encerra a tentativa sem seguir a navegação.

### D5 — Bloqueio HTTP 200 abre breaker global

O parser extrairá texto visível de forma limitada e normalizada, sem executar JavaScript. A frase observada `IP não autorizado devido múltiplas consultas`, variantes versionadas e templates equivalentes geram `SVRS_EGRESS_BLOCKED_MULTIPLE_QUERIES` mesmo em HTTP 200.

Ao detectar esse resultado:

- abrir breaker da coorte imediatamente para NF-e e NFC-e;
- cancelar reservas não iniciadas e impedir novos GET/POST;
- registrar somente código tipado, fingerprint do template, horários e correlação sanitizada;
- manter todo backlog e encaminhá-lo à contingência assistida;
- definir cooldown inicial de 24 horas;
- permitir depois apenas um canário allowlisted, de uma chave ainda pendente e não repetida;
- se o canário receber o mesmo bloqueio, ampliar para 48 h, 96 h e depois 168 h no máximo;
- fechar o breaker somente após canário válido; falha transitória não autoriza rajada.

`Retry-After` válido será respeitado quando maior que o cooldown local. ADMIN poderá desligar o canal, estender cooldown e selecionar a chave canário, mas não antecipar `next_probe_at` nem disparar chamadas paralelas. Não haverá rotação de egress, raiz ou A1 para testar o bloqueio.

`403`/`429` abrem proteção global compatível; mudança de contrato abre breaker global até revisão; falha de credencial/identidade abre bloqueio da raiz; `503`/rede aplica backoff sem loop e abre breaker após recorrência configurada.

### D6 — Contrato de transporte e parser mínimo

O cliente usará TLS 1.2+, validação de hostname/CA, allowlist exata e timeouts/limites de corpo. Ele enviará apenas os campos esperados do formulário e classificará respostas em tipos fechados. HTML remoto nunca será retornado por API ou log.

No sucesso, o parser localizará exclusivamente o literal associado à função oficial de download, decodificará somente a gramática mínima de escapes observada e rejeitará expressão, concatenação, template string, múltiplos candidatos ou conteúdo executável. `eval`, navegador e motor JavaScript são proibidos. Alteração do wrapper resulta em `RESPONSE_CONTRACT_CHANGED` e breaker, não em heurística permissiva.

### D7 — Validação antes da promoção canônica

Os bytes candidatos devem ser XML bem-formado sem rede/DTD e conter `nfeProc` modelo 55. Antes da ingestão, o sistema validará:

- chave solicitada versus `infNFe/@Id` e chave derivada;
- ambiente e emitente/raiz esperados;
- autorização/protocolo e vínculo do protocolo à chave;
- digest das referências e assinatura XMLDSig com cadeia aplicável;
- limites de tamanho e SHA-256 dos bytes originais.

Falha não altera o documento canônico nem marca `XML_CAPTURED`. Se a mesma chave já existe com hash diferente, o sistema preserva o canônico, bloqueia a recuperação e gera divergência crítica. XML bem-formado com XSD futuro pode ser preservado com alerta somente depois que identidade, protocolo, digest e assinatura forem válidos.

### D8 — Estado, auditoria e idempotência

Serão estendidos os registros já usados pela recuperação NFC-e:

- solicitação por chave/modelo com estado, fonte escolhida, agenda e motivo sanitizado;
- tentativa imutável com coorte, exchanges reservados/consumidos, classe de resultado, parser e timings;
- aquisição `SVRS_NFE55_DOWNLOAD_XML_DFE` com hash e correlação;
- breaker/coorte com estado, causa, `opened_at`, `next_probe_at`, escalonamento e canário;
- eventos de roteamento/fallback sem conteúdo fiscal bruto.

Unique constraints incluirão `office_id`, ambiente, chave, origem e correlação apropriados. O escritório sempre deriva da sessão/job autenticado. A chave completa fica restrita ao domínio e storage necessário, sendo mascarada em logs/métricas.

### D9 — Operação e interface

O dashboard mostrará por coorte: estado do breaker, próxima prova, exchanges usados/restantes nas janelas, último resultado, backlog NF-e/NFC-e e quantidade roteada para fallback. Por escritório, mostrará pendências e ações permitidas sem revelar dados de outros tenants.

ADMIN com 2FA recente gerencia flags, allowlist, desligamento, extensão de cooldown e canário; OPERATOR enfileira somente quando elegível e usa fallback; VIEWER consulta. Nenhum papel pode burlar orçamento ou antecipar cooldown. A interface explicará que o limite é preventivo e que o cooldown do `NFESSL` não é publicado.

### D10 — Evidência antes do auto-queue

O primeiro smoke NF-e 55 só ocorrerá depois de o bloqueio observado ter expirado naturalmente, com uma única chave de saída MA cujo XML original esteja disponível para comparação offline. Antes dele devem existir fake server, fixtures sanitizadas, kill switch, governador, detector do HTTP 200 bloqueado e backup/restore testado.

O smoke comprovará separadamente: relação do A1 do emitente, retorno do `nfeProc`, igualdade/hash ou justificativa de empacotamento, assinatura e protocolo. Resultado bloqueado não será repetido no mesmo período. Auto-queue continuará desligado até piloto explícito sem bloqueio.

## Riscos / Trade-offs

- **O formulário não é uma API pública estável** → adapter isolado, parser fechado, breaker por contrato e fallback assistido.
- **O limite pode somar tráfego fora desta implantação** → coorte por NAT, uma única implantação habilitada quando não houver coordenador comum e orçamento muito baixo.
- **O cooldown real é desconhecido** → 24 h iniciais, um único canário e escalonamento até 7 dias; sem override antecipado.
- **A taxa defensiva pode deixar backlog** → `autXML`/DistDFe e importação em massa são fontes preferenciais; SVRS é recuperação de lacunas.
- **Resposta 200 mascara bloqueio/erro** → classificar template e texto antes de procurar XML; sucesso exige validação integral.
- **O portal pode não fornecer NF-e própria MA** → gate de smoke e rollback sem perder pendência; não declarar cobertura antes da prova.
- **Redis indisponível poderia quebrar exclusão** → fail closed; breaker persistido no banco e nenhuma chamada sem reserva confirmada.
- **Alterar limites NFC-e atrasa rollout** → segurança compartilhada prevalece; a recuperação assistida permanece disponível.

## Plano de migração

1. Congelar chamadas reais SVRS e manter flags master/auto-queue desligadas.
2. Validar backup/restore e criar esquema durável de coorte, orçamento, breaker e tentativas.
3. Implementar fake portal e fixtures de sucesso, bloqueio HTTP 200, 403/429/503, contrato alterado e XML inválido.
4. Introduzir `SvrsPortalEgressGovernor` fail-closed e migrar o adapter NFC-e para ele, removendo efetivamente os defaults 5 s/30 s/20.
5. Implementar cliente/parser/validador NF-e 55 e roteador de fontes sem habilitar tráfego externo.
6. Implantar UI, métricas, inbox, kill switch e runbook; executar rollback drill.
7. Após `next_probe_at`, executar um único smoke restrito com chave própria MA e comparar com o XML original.
8. Se válido, pilotar uma raiz com auto-queue desligado e no máximo os budgets desta change; observar período aprovado sem bloqueio.
9. Habilitar auto-queue por allowlist; ampliar somente por decisão registrada e sem elevar taxa automaticamente.

Rollback: desligar master e auto-queue, abrir breaker administrativo sem prazo curto e drenar jobs sem iniciar rede. Dados, hashes, tentativas e pendências permanecem; XML/ZIP e pacote oficial continuam aceitos.

## Questões em aberto

- Qual limiar, janela, escopo e cooldown oficiais a SVRS aplica ao `NFESSL` por IP/certificado/chave?
- A SVRS autoriza formalmente uso automatizado de baixo volume desse formulário pelo escritório relacionado à NF-e?
- O contrato retorna toda NF-e 55 nacional relacionada ou somente documentos disponíveis na infraestrutura/retensão da SVRS?
- O POST direto é suportado de forma estável ou o GET autenticado é obrigatório por sessão?
- Qual período piloto sem bloqueio será exigido antes de ampliar a allowlist?

