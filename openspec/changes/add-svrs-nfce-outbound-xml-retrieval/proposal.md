## Por quê

O piloto MA provou que uma NFC-e modelo 65 de saída, cuja chave já foi descoberta, pode ter seu `nfeProc` original recuperado gratuitamente no portal oficial da SVRS com o A1 relacionado à nota, preservando protocolo, digest e assinatura. Transformar essa evidência em produto fecha o gap entre `KEY_DISCOVERED` e `XML_CAPTURED` sem API comercial, reconstrução de XML ou operação fiscal mutante.

## O que muda

- Adicionar cliente HTTP+mTLS próprio para o formulário autenticado `NFCESSL/DownloadXMLDFe`, sem navegador, Selenium ou execução de JavaScript.
- Extrair o `nfeProc` do wrapper HTML/JavaScript por parser estrito e versionado, tratando mudança de página como falha segura.
- Validar chave, DV, modelo 65, ambiente, emitente, autorização, digest e assinatura XMLDSig antes de persistir.
- Integrar a recuperação automática ao estado `KEY_DISCOVERED -> XML_PENDING -> XML_CAPTURED`, com idempotência, proveniência e bytes imutáveis no `SecureObjectStore`.
- Implementar filas Horizon, locks, rate limit conservador, backoff, circuit breaker, kill switch, métricas e auditoria sem material sensível.
- Adicionar API e UI operacional para elegibilidade, fila, tentativas, bloqueios, reprocessamento e fallback assistido.
- Entregar rollout por gates: fixtures sanitizadas, homologação quando possível, smoke restrito allowlisted, piloto e ampliação gradual.
- Alterar explicitamente a decisão anterior que rejeitava todo uso do download humano da SVRS como integração: a exceção será somente o fluxo NFC-e 65 comprovado, por HTTP+mTLS, atrás de flag e controles próprios.

## Não-objetivos

- Recuperar NF-e modelo 55 pela SVRS ou afirmar que o achado atende SVAN/MA.
- Automatizar Gov.br, SEFAZNET, CAPTCHA, MFA, cookies humanos ou navegação genérica de portal.
- Usar Selenium/Chrome, executar JavaScript remoto ou persistir PFX/PEM/senha em disco.
- Descobrir chaves por força bruta, substituir o motor de sequência por `nNF` ou usar DistDFe para NFC-e.
- Emitir, inutilizar, cancelar ou transmitir NFC-e para provocar rejeições.
- Usar API comercial ou reconstruir `nfeProc` a partir da consulta de protocolo.
- Prometer contrato/SLA da SVRS; indisponibilidade ou mudança do HTML mantém o documento pendente e aciona fallback assistido.

## Capacidades

### Novas capacidades

- `svrs-nfce-outbound-xml-retrieval`: recuperação gratuita de `nfeProc` de NFC-e 65 por chave conhecida e A1, incluindo protocolo HTTP+mTLS, parser do wrapper, validações, segurança, resiliência e gates de rollout.
- `outbound-xml-recovery-orchestration`: orquestração idempotente entre chave descoberta, recuperação SVRS, ingestão documental, retentativas, bloqueios e fallback assistido.

### Capacidades modificadas

- `outbound-xml-ingestion`: aceitar bytes recuperados automaticamente pela SVRS na mesma persistência imutável usada pelo import, com proveniência específica e quarentena de divergências.
- `operations-dashboard`: exibir métricas, backlog, falhas, circuit breaker e ações operacionais do canal SVRS NFC-e.
- `frontend-dashboard-experience`: oferecer configuração e acompanhamento do recovery de NFC-e no detalhe do estabelecimento e na inbox, respeitando papéis e 2FA.

## Impacto

- **Backend:** novo adapter libcurl mTLS, parser de resposta, orquestrador, jobs Horizon, estados/aquisições, políticas, métricas e auditoria; reutilização de `SecureObjectStore`, credencial A1 por raiz e projeção NF-e/NFC-e existente.
- **Banco:** complementos de estado e telemetria da recuperação, sem reutilizar cursor NSU e sem sobrescrever documentos imutáveis.
- **API/UI:** endpoints same-origin para consulta, reprocessamento e controles; telas Nuxt UI derivadas do dashboard oficial do repositório.
- **Operação:** novas flags independentes, allowlist, rate limit por raiz/IP, circuit breaker e runbook de rollback/fallback.
- **Sistemas externos:** portal oficial da SVRS em produção/homologação quando disponível; nenhuma dependência de API comercial.
- **Compatibilidade:** sem breaking change de API existente; a change depende dos estados e interfaces entregues por `build-ma-outbound-nfe-nfce-capture`.
