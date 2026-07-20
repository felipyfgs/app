## Context

C0 entrega em `services/mei` um executor FastAPI/Celery com HMAC, Redis, Playwright e artefatos efêmeros; em `apps/api`, entrega tentativas tenant-scoped e `MeiProviderRouter`. O provider portal ainda aceita apenas `fixture.health`. A C1 deve implementar quatro operações públicas sem tornar o Python dono de regras fiscais, tenancy, autorização, ledger ou evidência durável.

Os portais usam formulários server-rendered, antiforgery, redirects e downloads cujo HTML pode mudar sem versionamento. A execução live precisa permanecer OFF por padrão, com fixtures locais como contrato obrigatório e smoke real restrito a allowlist/consentimento.

## Goals / Non-Goals

**Goals:**

- Executar `pgmei.gerardaspdf`, `pgmei.gerardascodbarra`, `pgmei.dividaativa` e `dasnsimei.consultimadecrec` em contextos Playwright não persistentes.
- Entregar resultados estruturados, cobertura explícita e artefatos verificáveis ao Laravel.
- Evitar consumo SERPRO quando o portal termina com sucesso e permitir fallback apenas na taxonomia C0.
- Oferecer no Nuxt emissão de DAS por competência, histórico DASN-SIMEI e progresso/proveniência.

**Non-Goals:**

- Transmitir benefício ou declaração, gerar DAS de excesso, acessar CCMEI/Gov.br ou persistir sessão humana.
- Habilitar solver de captcha por padrão, usar stealth/anti-detecção ou repetir submissões ao portal; NoPeCHA permanece OFF, sem operações liberadas e com orçamento zero.
- Persistir HTML bruto, cookies, CNPJ completo, captcha ou conteúdo fiscal integral em logs.
- Substituir o SERPRO em operações não catalogadas ou emitir parecer jurídico sobre os portais.

## Decisions

1. **Registro de handlers por operação.** `OperationRegistry` resolve um handler com modelo Pydantic próprio; o dispatcher rejeita operação/input desconhecido antes de abrir o browser. Alternativa de um fluxo condicional único foi descartada porque mistura seletores, parsing e semântica fiscal.
2. **Navegação e parsing separados.** Cada handler usa páginas Playwright para navegação/download, mas delega HTML/texto sanitizado a parser puro versionado. Fixtures locais cobrem antiforgery, redirects, captcha, drift e downloads sem depender do portal live em CI.
3. **Seletores por semântica e checkpoints.** Locators priorizam label/role/name e cada transição valida URL, título e marcador esperado. Ausência de checkpoint antes de efeito remoto retorna `PORTAL_DRIFT`; não haverá seletores frágeis encadeados nem técnicas de evasão.
4. **CNPJ sempre string alfanumérica.** A validação aceita exatamente 14 caracteres ASCII alfanuméricos. Se o formulário live restringir a dígitos, o handler retorna `PORTAL_CNPJ_FORMAT_UNSUPPORTED` antes de submissão para permitir contingência.
5. **Emissão de DAS é sensível e idempotente.** `pgmei.gerardaspdf` e `pgmei.gerardascodbarra` usam preflight/confirmação/idempotência já existentes no Laravel. O provider registra `submitted` no primeiro request que possa gerar guia; após esse ponto timeout/drift resulta `UNCERTAIN`, nunca fallback ou segundo envio.
6. **Artefatos validados em duas fronteiras.** Python verifica assinatura `%PDF`, tamanho, digest e nome seguro antes de publicar descriptor; Laravel baixa por HMAC, recalcula digest/tipo/tamanho e ingere no `SecureObjectStore`. Código de barras é normalizado como string e nunca convertido para número.
7. **DASN com cobertura explícita.** `dasnsimei.consultimadecrec` retorna anos/status disponíveis e `coverage=SUMMARY`; declaração ou recibo integral somente terá `coverage=FULL` quando houver artefato integral validado. A projeção não inferirá campos ausentes.
8. **Captcha pluggable, limitado por job e sem evasão.** A interface `CaptchaSolver` tem `ManualCaptchaSolver` e `NoPechaCaptchaSolver`. NoPeCHA somente cria um job externo quando flag, chave, operação allowlisted, custo unitário e orçamento estão simultaneamente válidos; o polling é limitado por deadline e o token é injetado no mesmo `Page`/`BrowserContext`, sem recarregar a identificação ou repetir a submissão. Os defaults continuam OFF/zero/vazios. Captcha sem solução retorna erro classificado antes de submissão, permitindo fallback conforme política. Sessão remota e retomada com resposta humana pertencem à C2.
9. **Laravel continua dono do workflow.** Rotas públicas recebem apenas parâmetros fiscais permitidos e derivam `Office` da sessão. O Nuxt consulta Laravel, acompanha tentativa/run, baixa artefato autorizado e mostra `Portal Receita`, `SERPRO` ou `Contingência`.
10. **Rollout por operação.** Flags globais, allowlist de escritório e política por operação continuam OFF. Smoke live exige `MEI_PORTAL_SMOKE_ENABLED=true`, CNPJ/escritório allowlisted e `CONFIRM_MEI_PORTAL=SIM`.

## Risks / Trade-offs

- [Drift silencioso produz dado incorreto] -> checkpoints obrigatórios, parser fail-closed, fixtures versionadas e cobertura explícita.
- [Guia duplicada após timeout] -> marco `submitted`, idempotência Laravel e estado `UNCERTAIN` sem fallback.
- [PDF malicioso ou inválido] -> limite de bytes, magic bytes, SHA-256 e dupla validação antes do vault.
- [Captcha torna o portal pouco confiável] -> solver pluggable OFF, métrica de captcha e fallback pré-submissão.
- [Solver externo causa custo ou replay acidental] -> allowlist por operação, orçamento fail-closed, um job externo por execução, deadline e persistência apenas de driver/custo, nunca do token.
- [Portal limita CNPJ alfanumérico] -> incompatibilidade classificada e contingência, sem cast numérico.
- [Mudança concorrente entre C0 e C1] -> C1 só adiciona handlers/endpoints; contratos HMAC, estados e taxonomia de fallback de C0 permanecem compatíveis.

## Migration Plan

1. Publicar parsers/fixtures e handlers com live egress OFF.
2. Publicar endpoints Laravel e UI ocultos por flags; manter provider SERPRO como único caminho efetivo.
3. Habilitar fixture por operação em desenvolvimento e validar artefato/ledger.
4. Executar smoke read-only allowlisted; habilitar consulta para piloto e depois emissão de DAS.
5. Rollback: desligar operação/kill switch; tentativas e evidências permanecem, jobs novos usam SERPRO.

## Mapa de dependências

`adicionar-orquestrador-portal-mei (C0, verify)` -> `automatizar-servicos-publicos-mei (C1)` -> `habilitar-operacoes-assistidas-e-mutantes-mei (C2, verify)`. C0 mantém ownership de HMAC, estados, tentativas e provider router; C1 adiciona handlers públicos e contratos de apresentação sem reescrever artefatos C0. Parsers Python e UI podem avançar em paralelo; endpoints Laravel dependem dos schemas de resultado Python; rollout depende dos gates integrados.

## Open Questions

Nenhuma decisão bloqueante. Seletores live e presença de captcha serão confirmados por exploração Playwright controlada; qualquer divergência atualiza fixtures e versão do parser antes de habilitar egress.
