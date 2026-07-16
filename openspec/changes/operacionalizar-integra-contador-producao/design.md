## Context

O change `integrar-serpro-monitoramento-completo` entregou catálogo, gateway, adapters, ledger e telas, mas declarou produção real, smoke mTLS e evidência jurídico-comercial como fora de escopo. A evidência operacional disponível comprova contrato e emissão do par OAuth2 por mTLS; não comprova Termo aceito, token de procurador, poderes de cliente, chamada fiscal ou conciliação de cobrança. A autorização do escritório configurado permanece `PENDING_TERM`.

A pesquisa usou como fonte primária a documentação oficial do Integra Contador, em especial a [API Reference de produção de 13/03/2026](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/chamadas/api_reference/), o [guia de autenticação](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/quick_start/), o [layout/assinatura do Termo](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-contador-gerenciador/autenticaprocurador/padroes_tecnicos_assinatura_xml/), o [ENVIOXMLASSINADO81](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-contador-gerenciador/autenticaprocurador/servicos/envio_de_xml_assinado/), os [códigos de retorno e bilhetagem](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/codigos_retorno/), a [matriz de serviços x procurações](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/servicos_vs_procuracoes/) e os [limites dos Eventos](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-contador-gerenciador/eventosatualizacao/limites/).

O contrato local permite o arranjo de software house em nome do escritório/procurador mediante Termo XML, mas não substitui a procuração/autorização RFB do cliente nem constitui certificação jurídica da plataforma. A capa e o corpo também apresentam informação divergente de vigência, o que exige confirmação documental.

### Limites de confiança

```text
SERPRO contrato + e-CNPJ da software house (global/platform)
        │
        ├── OAuth mTLS → Bearer + JWT (global e efêmero)
        │
        └── Office contador/autor (tenant)
              │
              ├── Termo XML assinado → token do procurador
              │
              └── Cliente/contribuinte
                    └── autorização RFB + poderes por serviço
```

O contrato/credencial é global; Termo, A1 do autor e token são por `Office`; procuração e poder são por autor + contribuinte + serviço + ambiente. Misturar essas camadas permite acesso cruzado ou uma autorização falsa.

### Gaps confirmados no código atual

| Área | Estado atual | Comportamento necessário |
|---|---|---|
| Termo | `termo-autorizacao.v1.xsd` é lax, usa raiz incompatível; XPath global não vincula identidade ao nó assinado | Contrato derivado estrito, XMLDSig anti-wrapping e aceite SERPRO separado |
| Envio do Termo | `HttpAutenticarProcuradorClient` envia `xmlAssinado` com XML cru | `/Apoiar`, `AUTENTICAPROCURADOR/ENVIOXMLASSINADO81`, `dados={"xml":"<base64>"}` |
| Token/ETag | token em texto claro no Redis; ETag potencialmente sensível no banco; cache sem hash do Termo | token/ETag sensível no vault e cache ligado a contrato/Office/autor/hash |
| A1 gerenciado | certificado é armazenado, mas não existe fluxo que gere e assine o Termo | assinador dedicado ou fluxo externo A1/A3, ambos auditados |
| Doubles | fake é default e driver `disabled` pode resolver para sucesso simulado | produção falha no boot/readiness se qualquer fake/simulado contaminar fluxo real |
| Egress/ledger | vários adapters chamam o transporte diretamente; replay pode repetir HTTP; `serviceCode` recebe `id_sistema` | executor único, resultado durável e coordenadas corretas |
| Cobrança | tabela shadow parcial, mês calendário, shadow on, blocking off, budget nulo, unknown fail-open | tabela contratual completa, ciclo 21–20, orçamento positivo e deny-by-default |
| Catálogo | 98 operações aparecem implementadas embora codecs/adapters cubram subconjunto | readiness por operação com fonte + codec + fixture + poder + teste |
| Filas/flags | jobs usam fila sem consumidor e módulos novos não têm flags completas | Horizon/flags verificados no dispatch, job e readiness |
| Vault/backup | uma única chave efetiva; sem rewrap/catalogação; backups sensíveis não têm prova integral de restore | keyring, recriptografia, backup cifrado consistente e restore drill |
| Observabilidade | smoke é string de config; kill switch/breaker podem sumir com Redis | evidência durável, auditoria íntegra, métricas/alertas e estado persistente |
| Identidades | validações assumem CNPJ numérico em vários pontos | suporte alfanumérico antes da entrada produtiva prevista para julho de 2026 |

## Goals / Non-Goals

**Goals:**

- Tornar o caminho de produção real fail-closed do segredo ao ledger.
- Implementar corretamente Termo/Autentica Procurador e a cadeia contratante → autor → contribuinte.
- Validar a integração sem custo usando Trial, OAuth, `/Apoiar` e `/Monitorar` antes de qualquer canário faturável.
- Impedir sucesso fake, bypass de ledger, repetição HTTP não contabilizada, vazamento de tenant ou segredo e uso de operação sem cobertura comprovada.
- Dar a plataforma e ao escritório checklists acionáveis, evidência, alertas, reconciliação e rollback.
- Preparar CNPJ alfanumérico e bloquear campos oficiais ainda conflitantes até esclarecimento.

**Non-Goals:**

- Habilitar `/Declarar`, `/Emitir`, transmissões ou outras mutações fiscais.
- Alterar NFS-e ADN, SEFAZ MA/SVRS/autXML ou tornar seus defaults `ON`.
- Executar chamada faturável como parte de testes, CI, deploy, health ou preflight.
- Substituir aceite/procurações da Receita, parecer jurídico/LGPD ou confirmação oficial do SERPRO.
- Declarar as 98 operações produtivas implementadas sem evidência individual.

## Decisions

### 1. Um readiness hierárquico, não um booleano global

Adotar uma máquina de estados durável:

`CONFIGURED → CREDENTIALS_ROTATED → TLS_OK → OAUTH_OK → TERM_LOCAL_VALID → TERM_SERPRO_ACCEPTED → POWERS_VERIFIED → FREE_SMOKE_OK → CANARY_READY → PRODUCTION_READY`.

Os quatro primeiros gates pertencem ao contrato/ambiente global; Termo e token pertencem ao `Office`; poderes pertencem a `Office` + autor + cliente + operação. Um gate pai inválido retira todos os descendentes. Evidência terá emissor, timestamps, validade, fingerprints, versões documentais e resultado sanitizado.

Alternativa rejeitada: usar `health=OK` no contrato ou uma string `.env`. Isso não diferencia evidência local/live nem invalida dependências quando certificado, Termo, preço ou poder muda.

### 2. A documentação técnica atual é o protocolo canônico

OAuth continuará em `https://autenticacao.sapi.serpro.gov.br/authenticate` com mTLS, Basic, `role-type: TERCEIROS` e form-urlencoded. A raiz `/integra-contador/v1/` será usada somente com uma das cinco rotas de negócio. O curl divergente gerado pela Área do Cliente será anexado a um chamado, mas não cria um segundo fluxo especulativo.

Cada snapshot documental guardará URL, data, hash e revisão. Mudança de hash abre revisão; protocolo ou cobrança não mudam automaticamente.

Alternativa rejeitada: aceitar os dois endpoints até “ver qual funciona”. Isso mascara erro de portal, amplia superfície de segredo e pode chamar endpoint de negócio de maneira indefinida.

### 3. Credenciais versionadas e rotação em duas fases

Consumer Key/Secret e PFX do contratante serão versões imutáveis no vault: `PENDING → VERIFIED → ACTIVE → RETIRED/COMPROMISED`. O teste da versão pendente emite somente OAuth; dois `PLATFORM_ADMIN` com TOTP promovem o cutover e os tokens da versão anterior são invalidados. A versão exposta nesta configuração não satisfará readiness.

O vault ganhará keyring real, catálogo/journal de objetos e rewrap retomável. A chave mestra fica fora do backup; o backup DB + vault precisa ser consistente, cifrado/autenticado e testado com chave recuperada em ambiente isolado.

Alternativa rejeitada: sobrescrever segredo no mesmo registro. Isso elimina proveniência, dificulta rollback e pode associar token antigo à credencial nova.

### 4. Dois fluxos de assinatura do Termo

Haverá um gerador canônico e dois assinadores:

1. **Externo A1/A3 (baseline do primeiro piloto):** gera XML não assinado, recebe XML final e valida localmente. Reduz custódia contínua da chave do escritório.
2. **A1 gerenciado (opt-in):** requer consentimento versionado, `Office ADMIN` + 2FA e job dedicado; PFX é aberto apenas durante assinatura, com temporários protegidos e apagados.

O schema local será estrito e explicitamente “derivado da documentação”, pois não há XSD oficial baixável confirmado. O validador exigirá uma única assinatura/referência sobre o documento, transforms permitidos, RSA-SHA256/SHA-256/C14N, certificado final, identidade, destinatário, sistema e vigência. Cadeia/revogação local será verificada na capacidade disponível, mas somente o SERPRO produz `SERPRO_ACCEPTED`.

Alternativa rejeitada: endurecer o XSD lax sem mudar o parser. Isso ainda permitiria XML Signature Wrapping e não corrigiria o envelope Base64.

### 5. Token do procurador é segredo de vault

O request de `/Apoiar` codificará o XML validado em Base64 dentro de `pedidoDados.dados`. Token e ETag potencialmente sensível nunca ficam em texto claro no Redis/DB. O cache guarda referência opaca e é indexado por ambiente, contrato, Office, autor e hash do Termo. `304` só é sucesso com cache íntegro; `Expires`/expiração oficial prevalece e o fallback termina à meia-noite do dia seguinte em Brasília.

Mudar contrato, ambiente, autor, A1 ou Termo invalida atomicamente token, ETag e poderes derivados.

### 6. Um único executor controla todo egress

`SerproOperationService` evoluirá para o único entrypoint produtivo. O transporte HTTP ficará em infraestrutura e um teste de arquitetura impedirá injeção direta em controllers/adapters/jobs. O executor revalida imediatamente antes do HTTP:

- contrato/credencial/ambiente e driver real;
- feature flag, allowlist e kill switches;
- catálogo/codec/rota/auth/poder;
- Termo/token/procuração;
- orçamento, rate limit, breaker e idempotência;
- reserva de ledger com coordenadas canônicas.

A tentativa terá estado durável `reserved → dispatched → acknowledged|uncertain → reconciled`. Replays finalizados retornam resultado persistido; concorrentes não repetem HTTP. `X-Request-Tag` é só correlação, nunca garantia oficial de idempotência.

Alternativa rejeitada: pedir que cada adapter “lembre” de reservar ledger. A auditoria mostrou vários bypasses e isso não pode ser garantido por convenção.

### 7. Produção faturável é deny-by-default

A regra de cobrança será avaliada antes do transporte:

| Sinal oficial | Tratamento |
|---|---|
| rota `/Apoiar` ou `/Monitorar` | não bilhetada, mas sempre registrada |
| HTTP 204/304/400/401/404/429/500/503 | não bilhetado na conciliação atual |
| HTTP 200/202/403 em rota faturável | bilhetável |
| rota/status/preço desconhecido | bloqueado ou `POSSIBLY_BILLABLE` após transporte incerto |

Todas as faixas do contrato serão importadas em micros de BRL com vigência/hash e ciclo 21–20. Produção requer teto monetário positivo global, por Office e por canário. `shadow`, orçamento nulo, rate limit zero e unknown fail-open não liberam egress real.

Alternativa rejeitada: contar somente respostas 2xx ou tratar 403 como smoke gratuito. A documentação inclui 403 entre respostas bilhetáveis em rota faturável.

### 8. A escada padrão termina sem cobrança

O fluxo operacional obrigatório será:

1. fixtures/contract tests offline;
2. Trial oficial com dados simulados;
3. validação TLS/cadeia, inclusive `gateway-val` sem assumir que ele é sandbox;
4. OAuth mTLS real;
5. Termo real por `/Apoiar`;
6. Eventos por `/Monitorar`, com lote/cota/frescor controlados;
7. `FREE_SMOKE_OK`.

O canário `/Consultar` é uma mudança operacional posterior, opcional, com duas aprovações, uma operação read-only, um Office/cliente, quantidade e custo máximos, janela curta e reconciliação. Não será disparado automaticamente pela implementação desta change.

### 9. Catálogo publicado não equivale a adapter implementado

Cada operação terá uma matriz de readiness: snapshot oficial, ambiente, `idSistema`, `idServico`, versão, rota, auth, poder, classe de cobrança, codec de request/response, driver e fixtures/testes. `IMPLEMENTED` exigirá todos os itens. Falha de banco não poderá cair silenciosamente para default/manifesto em produção.

Procurações serão avaliadas por ambiente, authorization, autor, contribuinte, serviço/poder, status, origem e vigência. Sync completo encerra poderes ausentes. Sucesso simulado nunca satisfaz driver real.

### 10. CNPJ alfanumérico é mudança transversal

A Receita prevê entrada dos sistemas em produção em 27/07/2026 e emissão dos primeiros CNPJs alfanuméricos a partir de 31/07/2026. Identidades serão strings uppercase em migrations, domínio, validação, cache, XML/JSON e UI; nenhuma coerção numérica será permitida.

Existe conflito: a FAQ do Integra Contador declara suporte, enquanto páginas do Termo/Eventos ainda descrevem campos numéricos de 14 posições. Esses campos terão gate `OFFICIAL_CLARIFICATION_REQUIRED` e contract tests; não se “resolverá” o conflito por regex permissiva.

### 11. Estado operacional crítico é durável

Readiness, kill switch, rollout, aprovação e auditoria não dependerão apenas de Redis. Redis continua para locks/cache/rate limit, usando primitivas atômicas; fonte de verdade persistente fica no banco/audit store. Breakers serão por dependência/solução, half-open terá limite real de probes e somente falhas técnicas contam.

Jobs Horizon terão run/cursor/tentativa/erro e todas as filas nomeadas possuirão supervisor. Flags serão verificadas no dispatch e de novo antes do HTTP. O scheduler cobrirá PFX do contratante, A1 do autor, Termo, token, procuração, catálogo, preço, reconciliação e snapshots Horizon.

### 12. API global e API tenant permanecem separadas

Rotas globais `/api/v1/platform/serpro/*` usarão autenticação, usuário ativo, `PLATFORM_ADMIN` e TOTP; ações de cutover/kill switch usam aprovação dupla. Elas cuidam de contrato, credencial, catálogo, preços, orçamento global, readiness e rollout.

Rotas tenant `/api/v1/serpro/*` usam Sanctum, `EnsureActiveUser`, `EnsureOfficeContext`, 2FA e capacidades de `OfficeRole`; `office_id` do request nunca define escopo. Elas cuidam de autor, A1/Termo, token, procurações, clientes, orçamento tenant e readiness próprio. `PLATFORM_ADMIN` sem membership não ganha acesso fiscal.

O frontend estenderá `frontend/app/pages/admin/` para o console global e `frontend/app/pages/settings/` para onboarding tenant, usando `panel-ui` → `ui-archetype` e o template fixado. Nenhuma tela reexibe segredo, XML bruto ou ID do vault.

## Component Map

### Backend

- **Credencial global:** evoluir `SerproContractService`, `HttpSerproContractAuthenticator`, `SerproTokenCache`, `SerproContractController` e comando de contrato para versões/approvals/readiness.
- **Termo/autorização:** substituir o schema lax e evoluir `TermoXmlValidator`, `OfficeSerproAuthorizationService` e `HttpAutenticarProcuradorClient`; adicionar gerador/assinador e codec contratual.
- **Procurações:** endurecer `TaxProxyPowerService`, `HttpIntegraProcuracoesClient`, modelos/migrations e eligibility; registrar ambiente, autorização, origem e freshness.
- **Egress:** centralizar em `SerproOperationService`, corrigir coordenadas, replay e resultado incerto; restringir `HttpIntegraContadorClient` à infraestrutura.
- **Cobrança:** evoluir `UsageLedgerService`, `UsageBudgetGate`, pricing, agregação e reconciliação para contrato/ciclo/linhas oficiais.
- **Operação:** novos readiness runs, approvals, document snapshots e audit integrity; jobs Horizon/scheduler para expiração, drift, agregação e reconciliação.
- **Infra:** keyring/rewrap e catálogo do `SecureObjectStore`; backup/restore unificado; Redis atômico para limiter/breaker.

### Frontend

- `frontend/app/pages/admin/`: contrato, versões de credencial, readiness, kill switch, catálogo/cobertura, preços/orçamento, reconciliação e rollout.
- `frontend/app/pages/settings/`: checklist contrato → autor → certificado/Termo → token → procuração/poder → cliente/operação.
- `frontend/app/pages/settings/usage.vue` e inbox: diferenciar simulado, real, estimado, bilhetável e conciliado; deep-links válidos.
- Composables/types: estados tipados, ambiente explícito, motivos de bloqueio e identidades alfanuméricas.

### Persistência proposta

- Versionar credenciais/approvals sem duplicar segredos em colunas relacionais.
- Criar `serpro_readiness_runs`, `serpro_document_snapshots`, `serpro_rollout_approvals` e integridade de auditoria.
- Versionar Termos e consentimentos; relacionar token/ETag por referência opaca de vault.
- Adicionar ambiente, authorization, autor, origem, freshness e status aos poderes.
- Adicionar ambiente, contrato, versão de catálogo/preço, tentativa e resultado durável ao ledger.
- Modelar schedules/faixas e ciclos de cobrança, linhas de conciliação e incidentes.
- Criar journal de objetos do vault e versão criptográfica real para rewrap/GC seguro.

## Verification Strategy

- Unit/contract tests: envelope OAuth; codec Base64; layout/assinatura; XML wrapping; identidades; cache 304; billing/status; CNPJ alfanumérico.
- Feature tests: isolamento de Office, papéis/2FA, invalidação em cascata, fake em produção, flags no job, respostas sanitizadas.
- PostgreSQL + Redis reais: locks, orçamento, replay/idempotência, limiter, half-open, cursores e unique indexes.
- Architecture tests: nenhum egress fora do executor e nenhum controller tenant importa plano global indevidamente.
- Frontend: lint, typecheck, generate, unit e E2E de onboarding/readiness/deep-links usando arquétipo do painel.
- Ops: `prod-check`, inventário de filas, secret scan, backup/restore e `./docker/ops/verify.sh --full`.
- Live smoke: comando separado, opt-in, fora de CI e limitado às etapas oficialmente não bilhetadas.

## Risks / Trade-offs

- **[Credenciais já expostas]** → rotação é P0; nenhuma versão atual exposta satisfaz readiness e tokens derivados são invalidados.
- **[XML Signature Wrapping ou parser permissivo]** → validação vinculada à referência assinada, fixtures maliciosas e aceite remoto distinto.
- **[Ausência de XSD oficial baixável]** → schema derivado identificado como tal, chamado SERPRO e contract test em rota não bilhetada.
- **[Custódia do A1 do escritório]** → fluxo externo como baseline; A1 gerenciado somente com consentimento, key lifecycle e restore provados.
- **[Cobrança inesperada, inclusive 403]** → deny-by-default, preços/ciclo oficiais, budget atômico, escada gratuita e reconciliação.
- **[Nova chamada em replay]** → attempt/result durável e executor central; mutações permanecem desligadas.
- **[Fake mistura com real]** → boot/readiness bloqueiam bindings/proveniência simulada em produção.
- **[Vazamento entre Offices]** → `CurrentOffice`, índices compostos, policies, testes cross-tenant e nenhuma confiança em `office_id` HTTP.
- **[Segredo em Redis/log/API]** → referências opacas, vault, scanners e telemetria sem payload/PII.
- **[Mudança documental ou CNPJ alfanumérico]** → snapshots/hash, review gate e chamado oficial para conflitos.
- **[Dependência SERPRO]** → SLO interno não excede a dependência; breaker, backoff, runbooks e degradação por capacidade.
- **[Restore inviável após rotação]** → keyring, rewrap, escrow externo e drill DB↔vault antes do go-live.
- **[Escopo grande]** → rollout dark e fases independentes; nenhum driver real é ligado apenas porque o código foi implantado.

## Migration Plan

### Fase 0 — contenção

1. Manter kill switch/flags reais desligados e impedir chamadas faturáveis.
2. Marcar credenciais expostas como comprometidas; remover cópias locais após custódia aprovada.
3. Segregar dados demo e ledger shadow da linha de base produtiva.
4. Abrir chamados sobre curl OAuth, XSD e CNPJ alfanumérico; abrir revisão da vigência contratual.

### Fase 1 — fundação dark

1. Aplicar migrations compatíveis e backfills sem promover estados.
2. Implantar vault/keyring, Termo, executor, ledger, catálogo-readiness, jobs e UI com flags `OFF`.
3. Rodar suites offline, Postgres/Redis, secret scan e restore drill.
4. Tratar registros antigos/fake como `UNVERIFIED` e impedir sua promoção automática.

### Fase 2 — credencial e readiness global

1. Cadastrar nova Key/Secret/PFX como versão pendente pelo canal seguro.
2. Validar TLS/cadeia e OAuth, aprovar por quatro olhos e fazer cutover atômico.
3. Confirmar catálogo, matriz de poderes, preços, filas, budgets, alertas e runbooks.

### Fase 3 — um Office e smoke gratuito

1. Selecionar explicitamente um Office real não-demo e registrar consentimentos.
2. Gerar/assinar/validar o Termo e obter aceite em `/Apoiar`.
3. Verificar procuração/poder e executar canário `/Monitorar` dentro dos limites.
4. Encerrar em `FREE_SMOKE_OK`, revisar logs/ledger e testar kill switch/alertas.

### Fase 4 — canário faturável opcional

Somente mediante pedido e aprovação separados: uma chamada read-only, teto/quantidade 1, tag opaca, janela curta e dois aprovadores. Reconciliar o detalhamento oficial/fatura antes de qualquer expansão. Esta fase não é executada automaticamente ao aplicar a change.

### Fase 5 — expansão controlada

Promover uma capacidade e allowlist de Office por vez, com janela de observação e conciliação. `/Declarar`, `/Emitir` e mutações exigem change própria.

### Rollback

- Acionar kill switch global ou retirar a flag/allowlist afetada.
- Parar novos dispatches, marcar tentativas `uncertain` para reconciliação e não repetir cegamente.
- Reverter a versão ativa de credencial apenas para uma versão não comprometida ainda válida.
- Invalidar tokens/Termos dependentes quando contrato/autor muda.
- Preservar ledger/auditoria; migrations serão aditivas e a remoção de colunas ocorrerá somente em change posterior.

## Open Questions

1. **SERPRO/suporte:** o curl da Área do Cliente que usa a raiz do gateway é legado/erro ou fluxo suportado? Até resposta, somente `/authenticate` é aceito.
2. **SERPRO/suporte:** existe XSD oficial baixável e versionado para o Termo? Até obtê-lo, o schema derivado não será chamado oficial.
3. **SERPRO/RFB:** como CNPJ alfanumérico deve ser serializado nos campos do Termo e lotes que ainda documentam 14 dígitos? As capacidades afetadas ficam bloqueadas.
4. **Jurídico/comercial:** qual vigência prevalece entre capa e cláusula do contrato e qual tabela/faixa deve alimentar cada ciclo? Registrar confirmação antes de `PRODUCTION_READY`.
5. **Segurança/produto:** o A1 gerenciado será oferecido no primeiro piloto ou o baseline externo A1/A3 será obrigatório até concluir keyring/restore/RIPD?
6. **Operação:** qual provedor de métricas/alertas, on-call, RPO/RTO e mecanismo de escrow/KMS serão adotados?
7. **Produto:** qual Office e qual cliente real, não-demo, serão o canário gratuito? A seleção deverá ocorrer na aplicação, não em artefato versionado.
