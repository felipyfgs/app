## Context

O domínio existente já possui `EsocialEventClient`, DTOs para S-5003/S-5013/S-1299, persistência cifrada, projeção de estados, job, adapter fiscal, endpoints e a página `/monitoring/fgts`. O único binding atual é `DisabledEsocialEventClient`, e a copy presume incorretamente que não existe fonte M2M oficial.

O Manual de Orientação do Desenvolvedor do eSocial v1.15 documenta o eSocial BX: SOAP 1.1 com mTLS, mensagens assinadas em XMLDSig, consulta de identificadores e download cirúrgico de até 50 eventos. O Ambiente Nacional bloqueia os dias 1 a 7, limita a soma de consultas/downloads a 10 acessos por empregador/dia e não permite solicitações concorrentes. A consulta agregada por `tpEvt`/`perApur` atende S-1299 e S-5013; S-5003 é evento por trabalhador e exige CPF/identificador que o hub não possui como fonte canônica.

O portal FGTS Digital possui manual operacional e autenticação Gov.br, mas não publica contrato de API M2M para consultar pendências, emitir guia ou confirmar pagamento. Essas dimensões não podem ser inferidas dos totalizadores eSocial.

## Goals / Non-Goals

**Goals:**

- Tornar operacional o recorte read-only oficial S-1299/S-5013 por competência.
- Reutilizar o A1 ativo de `ClientCredential` sem persistir material fora do vault ou escrever PEM/PFX em disco.
- Aplicar localmente os limites oficiais e interpretar de forma determinística respostas, faults e códigos do eSocial.
- Persistir XML oficial idempotente e alimentar o read model FGTS, endpoints, scheduler/Horizon e UI existentes.
- Expor disponibilidade, ambiente, eventos automáticos e bloqueios sem vazar identificadores, XML, CNPJ completo ou segredos.

**Non-Goals:**

- Acessar o portal FGTS Digital, Gov.br, CAPTCHA, cookies ou sessão humana.
- Emitir/pagar guia, consultar pendência/débito do portal ou declarar quitação.
- Transmitir, retificar, reabrir ou excluir eventos eSocial.
- Buscar S-5003 sem uma lista oficial/local de CPF ou identificadores por trabalhador.
- Reutilizar automaticamente o A1 do escritório: uma futura change poderá fazê-lo após modelar consentimento e procuração eSocial específicos.
- Ligar flags/egress de produção por padrão, adicionar SERPRO/SEFAZ, criar `mei`/`mei-worker` no Compose ou alterar targets de ops indisponíveis.

## Decisions

### Adapter local mínimo em vez do SDK `nfephp-org/sped-esocial`

Será implementado `HttpEsocialBxEventClient` atrás do contrato existente. Ele reutilizará `nfephp-org/sped-common` para leitura do PFX e `robrichards/xmlseclibs` para XMLDSig, já presentes no projeto, mas manterá request factory, transporte e parser próprios e pequenos.

A release estável pública do SDK é v1.0.15 (2023), enquanto o branch atualizado é `dev-master` e o próprio README exige testes antes do uso. Adicionar essa dependência aumentaria superfície e acoplaria envio/mutação que esta change não usa. O adapter local será limitado aos dois métodos oficiais read-only e terá fixtures de contrato.

### Driver e egress explicitamente fail-closed

`fgts_esocial.php` ganhará driver `disabled|official_bx`, ambiente `restricted|production`, gate separado de egress de produção e URLs oficiais fixadas por allowlist de configuração. Defaults: driver `disabled`, ambiente `restricted` e produção bloqueada. `AppServiceProvider` só ligará o client HTTP quando o driver for explícito; flags de módulo e kill switch existentes continuam prevalecendo.

Não haverá coordenadas SERPRO nem uso de `SerproOperationExecutor`, porque eSocial BX é outro sistema oficial e não consta do catálogo Integra Contador.

### Credencial do próprio cliente, tenant-scoped

Um resolvedor específico consultará `CredentialService::activeFor($client)` e materializará o PFX somente durante a chamada. O certificado já é validado na ativação contra a raiz do CNPJ do cliente. Controller, job e service continuarão reconstruindo `Office`/`Client` por `office_id`, sem aceitar `office_id` do HTTP.

Ausência, expiração ou material inválido resultará em readiness bloqueada antes do enqueue. O material será liberado em `finally`; logs conterão apenas códigos, IDs internos e host.

### SOAP 1.1 e XMLDSig auditáveis

O request factory produzirá mensagens `v1_0_0` para `ConsultarIdentificadoresEventosEmpregador` e `SolicitarDownloadEventosPorId`, com escaping por DOM. Um signer genérico assinará o documento `eSocial` inteiro com RSA-SHA256, SHA-256, C14N e transform enveloped, anexando somente o certificado final.

O transporte cURL usará PFX por `CURLOPT_SSLCERT_BLOB`, TLS >= 1.2, verificação de peer/hostname, `Content-Type: text/xml; charset=UTF-8` e `SOAPAction` oficial. Redirect, TLS sem validação e URL recebida do usuário serão proibidos.

### Fluxo agregado e limites de cobertura

Para cada competência, o provider consultará primeiro S-1299 e S-5013. Somente quando existirem identificadores fará um download por tipo, com no máximo 50 IDs. Resposta `406` equivale a sucesso vazio; `203` ou quantidade total maior que a retornada marca resultado parcial. O parser aceitará somente XML bem formado cujo evento/competência correspondam ao pedido; demais arquivos serão ignorados com diagnóstico sanitizado.

S-5003 continuará no enum e no pipeline de persistência para fontes futuras/importadas, mas o manifesto distinguirá `accepted_events` de `automatic_events`. Fechamento e totalização permanecem independentes; guia/pagamento sempre `UNSUPPORTED`.

### Quota durável e exclusão mútua

Uma migration adicionará ledger tenant-scoped de tentativas eSocial BX com hash do empregador, operação, ambiente, status/código HTTP/eSocial e timestamps, sem payload. O guard adquirirá lock Redis/cache por ambiente+hash do empregador durante todo o fetch e reservará uma entrada antes de cada chamada. Contagem conservadora de tentativas evita ultrapassar 10/dia mesmo após timeout; resposta remota 405 também bloqueia.

Dias 1 a 7 serão negados antes de materializar credencial ou criar chamada. O endpoint de readiness informará `ready`, blockers, ambiente, janela operacional e saldo local, sem prometer que chamadas feitas por outros softwares não consumiram a quota oficial.

### API, scheduler e Nuxt

Rotas permanecem no contexto tenant Sanctum; não haverá endpoint platform. `coverage` será enriquecido com fonte oficial, ambiente, eventos automáticos, limites e URLs documentais. Um `readiness?client_id=` fará preflight read-only, e `sync`/`sync-now` usarão o mesmo preflight antes de enfileirar/executar.

O job existente continuará no Horizon e o adapter existente será consumido por `fiscal:dispatch-due-monitoring`. Bloqueios oficiais serão retornados como estado bloqueado/código estável, não como dado FGTS inventado.

No Nuxt, `/monitoring/fgts` manterá o shell do arquétipo e exibirá alerta compacto da fonte/limites; as ações existentes continuam disparando `sync`. Tipos e testes validarão copy e contratos, sem redesenho do painel.

## Mapa de dependências

```text
N0 config + ledger + contratos/DTOs
 ├─ N1 signer + request factory + parser + transport + guard
 │   └─ N2 HttpEsocialBxEventClient + binding + readiness
 │       ├─ N3 service/controller/job/adapter + testes API
 │       └─ N3 tipos/UI + testes Nuxt
 └───────────────────────────────────────────────┘
                         N4 gates integrados
```

- Ownership: backend novo fica em `Services/Esocial` e migration/model próprios; frontend limita-se a `/monitoring/fgts`, `types/api.ts` e client fiscal.
- Bases estáveis: `ClientCredential`, `CredentialService`, vault, domínio FGTS/eSocial e scheduler fiscal existentes.
- Não há upstream ativo bloqueante. Se outra change tocar os mesmos arquivos de integração, as edições serão serializadas e o diff local preservado.
- Rollout: schema e código podem subir com driver desabilitado; Produção Restrita é habilitada por coorte/configuração; Produção exige segundo gate explícito. Rollback desliga driver/kill switch e mantém evidências/ledger para auditoria.

## Risks / Trade-offs

- [Quota oficial pode ser consumida fora do hub] → ledger local é conservador, resposta 405 é tratada como bloqueio e a UI não apresenta saldo como garantia remota.
- [Lock distribuído indisponível] → falhar fechado e deixar Horizon tentar novamente; nunca chamar sem exclusão mútua.
- [Procuração insuficiente retorna 407] → classificar como blocker de autorização e não fazer fallback para portal/A1 de outro titular.
- [Vazamento entre offices] → todas as consultas fixam `office_id` e `client_id`; ledger e readiness não aceitam tenant do request.
- [PFX/XML em logs ou API] → material só em memória, payload apenas no vault, diagnósticos com hashes/códigos e testes negativos de serialização.
- [Resposta XML alterada] → parser namespace-agnostic apenas no envelope, whitelist estrita dos eventos e fixtures de fault/sucesso/parcial; XML desconhecido não promove estado.
- [S-5013 não prova pagamento] → estados de guia/pagamento continuam `UNSUPPORTED` e copy explicita a limitação.
- [Bilhetagem SERPRO acidental] → não há chamada a Integra Contador nem `operation_key`; endpoints são exclusivamente eSocial.
- [Serviços Compose indevidos] → nenhum serviço novo; usa PHP/Horizon já existentes e nunca adiciona `mei`/`mei-worker`.

## Migration Plan

1. Aplicar a migration do ledger sem alterar dados existentes.
2. Implantar código com `FGTS_ESOCIAL_DRIVER=disabled`; validar assinatura/parser/fixtures e Produção Restrita.
3. Habilitar `official_bx` apenas em ambiente/coorte controlados, mantendo `FEATURE_FGTS_*` e kill switch.
4. Observar códigos 301/307–310/403–417, consumo local e jobs antes de autorizar egress de produção.
5. Em rollback, voltar o driver a `disabled` ou ativar kill switch; nenhuma evidência fiscal é apagada e nenhum evento externo foi mutado.

## Open Questions

- Uma futura capability deverá importar CPFs/identificadores de trabalhadores de uma fonte oficial de folha para automatizar S-5003 sem ampliar esta change.
- Caso o MTE publique API oficial do FGTS Digital para guia/pagamento, ela exigirá change própria, contrato separado e revisão dos estados hoje `UNSUPPORTED`.
