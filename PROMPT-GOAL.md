# Goal — Integra Contador completa, utilizável e validada no SERPRO real

Você é o agente principal responsável por concluir a integração do hub com a
API SERPRO Integra Contador. Trabalhe de forma autônoma, persistente e
evidence-driven até cumprir integralmente a definição de pronto deste arquivo
ou até encontrar um bloqueio externo que realmente exija autoridade, dado ou
credencial que você não pode inferir.

Não trate este trabalho como uma varredura superficial de catálogo. O alvo é
produto pronto para produção: contrato oficial correto, backend seguro,
interface funcional, testes, operação observável e pelo menos uma validação
HTTP real e semanticamente válida para cada serviço oficialmente produtivo.

## Comando de trabalho

Para qualquer alteração em `frontend/`, use exclusivamente as skills de UI
`panel-ui` e `ui-archetype`.

## Orquestração inteligente de subagentes

O agente principal é o coordenador e continua responsável pelo resultado
integrado. Use subagentes de forma deliberada para aumentar throughput e
qualidade, não apenas para multiplicar análises. Antes de cada lote, transforme
o ledger e as tasks OpenSpec em um grafo curto de trabalho, identificando
dependências, caminho crítico, itens paralelizáveis, arquivos compartilhados e
critério de aceite. Mantenha um quadro simples com `PENDING`, `IN_PROGRESS`,
`BLOCKED` e `PASS`, responsável e evidência esperada por item.

Delegue trabalhos concretos, limitados e verificáveis. Toda missão enviada a um
subagente deve informar:

- objetivo e contexto mínimo necessário;
- change/task e operações sob sua responsabilidade;
- escopo de arquivos que pode editar e arquivos que deve apenas inspecionar;
- invariantes de segurança, tenancy e SERPRO aplicáveis;
- critérios de aceite e comandos de validação focados;
- formato da devolutiva: achados, arquivos alterados, testes, evidências, riscos
  e blockers.

Use os slots de execução disponíveis com consciência do caminho crítico:

- paralelize pesquisa oficial, auditorias read-only, implementação de lotes sem
  arquivos em comum e testes independentes;
- serialize migrations, contratos compartilhados, catálogo canônico, ledger e
  integrações que disputem os mesmos arquivos ou dependam do mesmo resultado;
- mantenha um subagente por escopo durante as correções para preservar contexto;
- não crie dois implementadores para a mesma tarefa nem permita edições
  concorrentes no mesmo conjunto de arquivos;
- enquanto subagentes trabalham, o coordenador deve avançar em integração,
  revisão de dependências ou outra tarefa independente; não deve apenas aguardar;
- redistribua trabalho quando surgir blocker, mas não aumente paralelismo se o
  gargalo for credencial, aprovação, ambiente externo ou uma única dependência.

Monte a equipe conforme a necessidade do lote, combinando quando útil:

1. `scout/planner`, para confrontar fonte oficial, código, ledger e OpenSpec e
   produzir o mapa de dependências, sem editar implementação;
2. `implementer`, para uma change, família ou fluxo pequeno com propriedade de
   arquivos claramente delimitada;
3. `frontend implementer`, quando houver uma superfície Nuxt independente;
4. `validator`, que não implementou o item e deve reler o diff, executar os
   checks e emitir `VERDICT: PASS` ou `VERDICT: FAIL` com evidência;
5. `security/tenancy reviewer`, para auditoria transversal de `CurrentOffice`,
   RBAC, segredos, mutações, logs e artefatos nos lotes de maior risco.

Todos os agentes compartilham o mesmo working tree. Cada um deve inspecionar o
estado atual antes de editar, preservar mudanças do usuário e de outros agentes
e comunicar imediatamente qualquer sobreposição. O coordenador deve resolver
conflitos de escopo, revisar o diff agregado e integrar resultados; mensagens ou
resumos de subagentes não substituem a leitura dos arquivos e das evidências.
Não delegue autorização para canário real, mutação fiscal, mudança de flags,
commit, push ou archive: esses gates continuam sob responsabilidade direta do
coordenador e exigem as aprovações definidas neste prompt.

Ao concluir cada onda paralela, recolha todas as devolutivas, atualize o quadro
e o ledger, rode validações de integração e só então libere a próxima onda
dependente. Um item só muda para `PASS` depois do validator independente; saída
incompleta, ausência de teste ou conflito não resolvido volta ao implementador
com uma correção específica. Prefira poucos subagentes com missões precisas a
muitos agentes com contexto amplo e responsabilidade ambígua.

## Objetivo verificável

Ao final, toda operação cujo estado oficial vigente seja `PRODUCTION` deve:

- existir 1:1 no catálogo canônico local, sem coordenada inventada;
- resolver `operation_key`, `idSistema`, `idServico`, `versaoSistema`, rota,
  modo de autenticação, procuração/poder, bilhetagem, mutabilidade e política
  assíncrona corretos;
- possuir contrato de entrada e saída tipado, adapter/codec fail-closed,
  persistência/projeção segura quando necessária e testes offline;
- possuir API local tenant-scoped e autorizada, sem aceitar `office_id` do
  client e sem expor parâmetros técnicos SERPRO ao navegador;
- estar acessível por uma UI de negócio 100% funcional ou, quando for uma
  operação estritamente automática, por uma superfície operacional completa
  que permita configurar, acompanhar, inspecionar resultado sanitizado e agir
  sobre falhas;
- ter evidência de uso da interface em desktop e mobile;
- ter pelo menos uma chamada real pelo endpoint de consumo contratado, com
  proveniência `PRODUCTION_CANARY`, `simulated=false`, payload válido para o
  cenário e resultado semanticamente aceito;
- estar protegida pelos controles de produção do hub e documentada no ledger.

No baseline atual, verificado em 2026-07-18, o catálogo possui **119 operações**:
**98 `PRODUCTION`**, **19 `PROSPECTION`**, **1 `UNDER_CONSTRUCTION`** e
**1 `CANCELED`**. Entre as 98 produtivas há **73 não mutantes** e **25
mutantes**. Recalcule esses números a partir da fonte oficial e do snapshot no
início de cada retomada; não preserve contagens antigas se a SERPRO mudar o
catálogo.

As 21 operações não produtivas devem continuar inventariadas e recusadas pelo
executor. Não as promova, não crie UI que prometa execução e não as use para
completar artificialmente a cobertura.

## Fontes de verdade

Leia antes de editar:

- `AGENTS.md`;
- `backend/resources/serpro/official-service-catalog.v2026-07-16.json`;
- `backend/resources/serpro/official-sources.v2026-07-16.json`;
- `docs/ops/integra-contador-matriz-cobertura.md`;
- `docs/ops/integra-contador-evidencias-piloto.md`;
- `docs/ops/serpro-integra-vs-hub-inventory.md`;
- `docs/ops/PROMPT-ATIVAR-PILOTO-SERPRO-TRIAL.md`;
- `docs/ops/multitenant-rbac-inventory.md`;
- `docs/ops/schema-conventions.md`;
- `docs/ui/visual-standardization-checklist.md`;
- todas as changes ativas relacionadas em `openspec/changes/`;
- o código real de catálogo, transporte, autorização, operação, adapters,
  projeções, controllers, jobs e UI; não confie apenas nos inventários.

Confira a documentação oficial vigente:

- catálogo: <https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/catalogo_de_servicos/>;
- serviços × procurações: <https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/servicos_vs_procuracoes/>;
- autenticação: <https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/quick_start/>;
- envelope e rotas: <https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/integra_contador/>;
- demonstração/Trial: <https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/como_usar_a_demonstracao_da_api/>;
- cenários de demonstração e a página específica de cada serviço, quando
  houver dúvida de payload ou resposta.

Se a fonte oficial divergir do snapshot, pare a operação afetada, registre o
diff, atualize o snapshot e seus hashes/testes em uma change OpenSpec própria e
só então implemente. Nunca invente `idSistema`, `idServico`, rota, versão,
payload, poder e-CAC ou status oficial.

## Baseline que deve ser auditado, não presumido

Já existe um probe E2E externo:

- `backend/app/Console/Commands/SerproE2eProbeCommand.php`;
- `backend/app/Services/Serpro/E2e/SerproE2eProbeService.php`;
- `backend/app/Services/Serpro/E2e/SerproE2ePayloadFactory.php`;
- artefatos sanitizados em `storage/app/serpro-e2e-probe/`;
- evidências visuais E2E são regeneráveis e devem permanecer somente em
  diretórios locais ignorados pelo Git; arquivos de execuções anteriores não
  são fonte de verdade.

O último inventário registrou, para as 98 produtivas, 31 `PASS_BUSINESS`, 34
`FAIL_SERPRO` e 33 `BLOCKED_HUB`. Isso é ponto de partida, não aceite final:

- `FAIL_SERPRO` 4xx/5xx comprova transporte, mas não comprova implementação;
- `BLOCKED_HUB` sem egress não é chamada real;
- `PASS_BUSINESS` antigo deve ser revalidado após o código final e auditado
  contra o contrato específico; payload placeholder ou resposta vazia mal
  interpretada invalida o PASS;
- se o probe usou Trial/demonstração, reclassifique o resultado como
  `PASS_TRIAL`, ainda que o transporte tenha sido HTTP real;
- um `platform_support=IMPLEMENTED` no JSON prova apenas suporte do
  resolver/executor atual, não prova produto completo.

Preserve as mudanças existentes do usuário. O working tree pode estar sujo;
não resete, não descarte e não sobrescreva trabalho concorrente. Antes de cada
lote, registre o baseline e limite o diff à change/tarefa em execução.

## Ledger obrigatório 1:1

Mantenha `docs/ops/integra-contador-matriz-cobertura.md` como ledger humano e
um tracker sanitizado gerado pelo probe. Cada uma das 119 linhas deve conter ou
permitir derivar, no mínimo:

- `operation_key`, código oficial, `idSistema`, `idServico`, versão e rota;
- estado oficial e data/fonte da última verificação;
- família/capability, poder e-CAC e regra de representação;
- `is_mutating`, classe de bilhetagem e async/cache policy;
- maturidade real de backend, API local, persistência e UI;
- testes unitários, feature, arquitetura, Nuxt e browser executados;
- ambiente usado (`TRIAL` para mock oficial ou `PRODUCTION_CANARY` para
  validação real), sem confundir os dois;
- classificação da evidência real, HTTP, status de negócio, timestamp,
  correlation/request tag sanitizada e hash do artefato;
- pendência, blocker e responsável por liberar a condição externa;
- status final `READY_PRODUCTION`, `BLOCKED`, `INVENTORIED_NON_PROD` ou
  equivalente sem ambiguidade.

O ledger não pode conter CPF, CNPJ completo, PFX/P12, senha, PEM, chave privada,
Consumer Secret, access token, `jwt_token`, token do procurador, Termo XML,
payload fiscal bruto, Base64 de documento ou path interno de vault.

## Estratégia de implementação por pequenos lotes

Reconcile primeiro as changes OpenSpec existentes; não duplique propostas já
implementadas. Artefatos OpenSpec devem ser sempre em pt-BR. Crie changes
pequenas, com uma capability e no máximo duas quando forem inseparáveis. Não
crie uma única change gigante para as 98 operações.

Percorra nesta ordem de dependência:

1. fundação: catálogo/importação, schemas, resolver, OAuth mTLS, token cache,
   envelope, headers, transporte, erro, rate limit, circuit breaker,
   bilhetagem, orçamento e sanitização;
2. autorização: contrato global, autor, Termo assinado, cache 304,
   `autenticar_procurador_token`, `PROCURACOES/OBTERPROCURACAO41` e matriz de
   poderes por office/cliente;
3. leituras e apoios por família: Simples/MEI, DCTFWeb/MIT, parcelamentos,
   caixa postal/DTE, PagtoWeb/Sicalc, SITFIS, Redesim e e-Processo;
4. fluxos assíncronos encadeados: SITFIS e EVENTOSATUALIZACAO;
5. emissões sem alteração cadastral/declaratória, com documentos no cofre e
   download same-origin autorizado;
6. mutações fiscais, uma a uma, somente depois dos gates específicos;
7. validação visual e operacional transversal;
8. regressão completa e revisão real final de todas as produtivas.

Dentro de cada família, priorize operações que produzem identificadores usados
por outras. Exemplos: listar antes de consultar detalhe, obter número de
declaração antes do recibo, obter pedido de parcelamento antes do detalhe,
solicitar protocolo antes de consultar/emitir resultado. Nunca use `0`, `1`,
`MISSING_PROTOCOL` ou identificador inventado como evidência final.

## Contrato mínimo por operação

Uma operação só pode ser marcada pronta quando o validator comprovar:

1. coordenada e estado confrontados com a página oficial específica;
2. `request_schema` completo, inclusive objetos/arrays aninhados, enums,
   formatos, opcionais condicionais e regras de domínio;
3. fixture sanitizada de request e de todos os tipos relevantes de response;
4. codec/DTO tipado e fail-closed para entrada, sucesso, erro, vazio,
   documento, assincronismo e cache aplicáveis;
5. adapter de domínio; controller não monta JSON SERPRO nem chama transporte
   direto;
6. resposta externa sanitizada antes de log, evidência, projeção e API;
7. idempotência, concorrência, retry e timeout adequados à mutabilidade;
8. persistência tenant-scoped e artefatos sensíveis somente no
   `SecureObjectStore`;
9. API local autenticada/autorizada via `CurrentOffice`, recusando `office_id`
   de query/body/JSON e cobrindo negação cross-tenant;
10. UI completa e testes correspondentes;
11. teste offline Fake/Simulated sem HTTP real;
12. evidência HTTP real aceita segundo os critérios abaixo.

Não aceite executor genérico com JSON cru como substituto de contrato de
produto. Reuso por família é desejável quando mantém schemas, semântica,
sanitização e UX específicos verificáveis.

## UI 100% funcional

Para cada operação `PRODUCTION`, implemente um fluxo de usuário real dentro da
família correta. Operações automáticas podem compartilhar uma superfície de
monitoramento; operações manuais precisam de ação explícita. A listagem do
catálogo em `admin/serpro/catalog` não conta como UI de negócio.

A UI deve:

- seguir `.reference/nuxt-dashboard-template` no commit fixado e as skills
  `panel-ui`/`ui-archetype`/Nuxt UI;
- ser responsiva em desktop e mobile e preservar o shell atual;
- ter estados de loading, vazio, sucesso, erro, bloqueado, sem autorização,
  async/processando, rate limit e indisponibilidade;
- mostrar dados sanitizados e linguagem de negócio em pt-BR, sem `operation_key`,
  coordenada SERPRO, JSON cru ou identificador fiscal completo;
- nunca disparar SERPRO em GET, montagem de componente, abertura de modal,
  navegação, polling sem limite ou simples refresh visual;
- exigir confirmação antes de consulta possivelmente faturável;
- para mutação, exibir efeito fiscal, contribuinte mascarado, resumo dos dados,
  custo/risco, idempotency key, confirmação reforçada e 2FA recente;
- respeitar `TenantAuthorization`, papéis/permissões e feature flags;
- oferecer histórico local, proveniência, última tentativa, resultado, retry
  seguro quando aplicável e download autorizado para documentos;
- preservar acessibilidade, teclado, foco, labels e contraste;
- ter teste Nuxt de comportamento e evidência visual desktop/mobile.

É permitido usar Playwright ou navegador para validar a aplicação. Reuse o
harness existente quando ele for adequado. O `test:e2e` do frontend foi
removido deliberadamente: não o restaure nem adicione uma stack E2E ao produto
sem decisão OpenSpec. Artefatos do browser devem ficar em path ignorado, usar
dados mascarados e passar por varredura de segredos antes de serem citados.

## Dados piloto

É permitido usar os dados já carregados no banco piloto e os cenários oficiais
de demonstração SERPRO. Prefira resolver office, cliente, estabelecimento,
período, declaração, pagamento, mensagem, parcelamento e protocolo por relações
de domínio existentes; não fixe IDs de banco no código e não imprima NI completo.

Use `PilotSeeder`/`seed-pilot` apenas conforme os comandos reais do repositório.
Não rode `migrate:fresh`, não destrua nem recrie o banco piloto e não leia
arquivos brutos de `dados/` para colher segredos. Se faltar uma pré-condição de
negócio válida, procure outro registro piloto autorizado. Para homologar o
fluxo, use um cenário Trial oficial, mas ele nunca substitui a pré-condição real
da revisão final. Se ainda faltar, registre blocker exato; não fabrique dados
para obter HTTP 200.

## Validação SERPRO real

Chamada real significa egress HTTPS para os endpoints oficiais, credencial
real carregada do vault, `SERPRO_USE_FAKE_CLIENTS=false`, capability `real`,
`simulated=false` e execução pela cadeia de produção do hub. Chamar Fake,
Simulated, fixture, mock, proxy local ou somente `/authenticate` não valida a
operação de negócio.

A implementação e a maioria dos testes podem usar Trial/homologação. A própria
documentação da SERPRO define o Trial como demonstração baseada em dados
simulados (`mock objects`); portanto, mesmo quando houver egress HTTP ao endpoint
de demonstração e a aplicação marcar `simulated=false`, isso **não** é evidência
de negócio real e não satisfaz a revisão final.

Use todos os cenários Trial oficiais disponíveis para validar schemas, códigos,
encadeamentos e UX com segurança. Registre-os como `PASS_TRIAL`, nunca como
`PASS_REAL_*`. Para cada operação produtiva, a revisão final deve executar ao
menos uma chamada pelo endpoint de consumo contratado, com credenciais e dados
piloto reais, em canário de produção explicitamente aprovado. O campo local
`simulated=false` é necessário, mas não suficiente: ambiente, endpoint,
contrato e proveniência também precisam comprovar `PRODUCTION_CANARY`.

Classificações aceitas:

- `PASS_TRIAL`: demonstração oficial SERPRO com mock object; aceita apenas para
  homologação, nunca para o critério real final;
- `PASS_REAL_SYNC`: resposta síncrona válida, schema e semântica conferidos;
- `PASS_REAL_EMPTY`: sucesso válido e vazio legítimo para um request de negócio
  correto — não vale para payload placeholder;
- `PASS_REAL_ASYNC_COMPLETE`: a cadeia assíncrona chegou ao estado terminal e o
  resultado final foi validado;
- `PASS_REAL_CACHE`: 304 usado somente onde a documentação prevê cache e com
  prova de que token/protocolo/artefato válido foi reutilizado;
- `BLOCKED_EXTERNAL`: não há contrato, procuração, poder, dado ou autorização;
- `FAIL_REAL`: 4xx, 5xx, schema inválido, erro de negócio, timeout não resolvido,
  resposta simulada ou pós-condição ausente.

Somente as quatro classificações `PASS_REAL_*`, acompanhadas de proveniência
`PRODUCTION_CANARY`, satisfazem o critério real. `PASS_TRIAL` não satisfaz.
HTTP 202/204 é intermediário e não fecha operação assíncrona. HTTP 304 sem
reuso comprovado não fecha. HTTP 400/401/403/404/429/500/503 nunca é PASS,
mesmo comprovando que houve uma chamada. `BLOCKED_HUB` também nunca é PASS.

Para operações dependentes, o probe deve encadear resultados reais. Exemplos:

- SITFIS: solicitar protocolo, respeitar `tempoEspera`, consultar/emitir até o
  estado terminal e validar o PDF sem expô-lo;
- eventos: solicitar PF/PJ, persistir protocolo protegido, respeitar limite e
  obter eventos até conclusão;
- caixa postal: listar mensagens e usar um identificador real protegido para o
  detalhe;
- PGDASD/DEFIS/DCTFWeb: listar/encontrar declaração antes de consultar recibo,
  extrato, XML ou PDF específico;
- parcelamentos: listar pedidos/parcelas antes de detalhe, pagamento ou guia;
- PagtoWeb/PNRContador: obter documento/solicitação existente antes do
  comprovante ou situação.

Evolua `serpro:e2e-probe` se necessário para suportar payloads derivados,
resume, espera assíncrona, orçamento, seleção por operação, manifest de mutação
e tracker sanitizado. O probe deve falhar com exit code diferente de zero se
qualquer alvo não obtiver `PASS_REAL_*`; não retorne sucesso apenas porque
percorreu a lista.

## Gate obrigatório para mutações

Existem 25 operações produtivas marcadas como mutantes. A intenção geral deste
goal de validar tudo não autoriza o agente a inventar dados, transmitir
declaração, encerrar apuração, alterar benefício, renunciar vínculo ou gerar
obrigação/guia em produção sem contexto operacional específico.

Use primeiro o cenário oficial Trial para homologar a mutação sem efeito fiscal
real, sabendo que ele não fecha o critério final. Antes de qualquer canário de
produção mutante, obtenha um manifest de aprovação com:

- `operation_key` e efeito esperado;
- office/cliente piloto autorizados, identificados de forma sanitizada;
- payload de negócio revisado por responsável fiscal;
- ambiente, janela, responsável e 2FA recente;
- flags/capability/allowlist mínimas e temporárias;
- teto de chamadas e custo;
- idempotency key e política de timeout sem retry cego;
- pré-condições, pós-condição verificável, conciliação e plano de contenção;
- confirmação explícita de que o efeito fiscal é desejado.

Sem esse manifest, conclua código, UI e homologação, marque
`BLOCKED_EXTERNAL`, informe exatamente o que falta e mantenha o goal aberto.
Nunca desligue kill switch ou amplie allowlist globalmente. Restaure o runtime
ao estado fail-closed após o canário.

## Segurança e prontidão de produção

São invariantes, não itens opcionais:

- OAuth mTLS usa e-CNPJ do contratante e exige `access_token` + `jwt_token`;
- `pedidoDados.dados` é string JSON escapada conforme o serviço;
- `autenticar_procurador_token` só entra no header quando aplicável;
- contrato SERPRO é global; tenant não recebe credencial nem contexto fiscal
  privilegiado de `PLATFORM_ADMIN`;
- toda autoridade tenant vem de `CurrentOffice`/`TenantAuthorization`;
- PFX, PEM, senhas, secrets, tokens, Termo, XML/PDF/Base64 e payload bruto nunca
  entram em log, API, DOM, screenshot, tracker, commit ou resposta do agente;
- capabilities e mutações ficam default OFF; kill switch global vence;
- `simulated` é proibido em production;
- rate limiter, budget, circuit breaker, timeout, lock e retry distinguem
  consulta idempotente de mutação;
- mutação com timeout vira pendência de conciliação, sem retry cego;
- chamadas bilhetáveis usam teto, classificação correta e `x-request-tag`
  sanitizada de até 32 caracteres;
- 403 é bilhetável nas rotas aplicáveis e não pode virar retry automático;
- CNPJ é string e deve estar preparado para formato alfanumérico vigente;
- migrations são forward-only, PostgreSQL é truth, jobs usam Redis/Horizon;
- downloads usam cofre, autorização same-origin, MIME/nome seguro e auditoria;
- erros são acionáveis internamente e sanitizados externamente;
- nenhuma mudança habilita canal outbound para outros offices.

Faça varreduras automatizadas de logs, JSON de evidência, respostas API, DOM e
screenshots para padrões de segredo e identificadores não mascarados. Qualquer
vazamento é `VERDICT: FAIL` automático.

## Evidência por lote

Para cada tarefa/fluxo, o implementador deve registrar:

- change/task OpenSpec;
- arquivos tocados;
- fonte oficial e contrato confirmado;
- testes focados executados;
- evidência de API local e UI;
- evidência real sanitizada ou blocker externo;
- riscos, bilhetagem e rollback/contenção;
- atualização das linhas correspondentes do ledger.

O validator deve reler código e evidências no workspace, executar os checks e
julgar item a item. Não confiar apenas no relatório do implementador. Diff fora
da change, segredo, cross-tenant, payload placeholder, 4xx/5xx aceito como
sucesso, UI de catálogo usada como produto ou simulação alegada como real são
falhas obrigatórias.

## Gates técnicos

Rode checks focados em cada lote e, ao final, os gates completos reais do repo:

```bash
cd backend && composer validate --strict
cd backend && vendor/bin/pint --test
cd backend && php artisan test

cd frontend && pnpm run test:gate
cd frontend && pnpm run generate
cd frontend && pnpm run test:fidelity
cd frontend && pnpm run test:artifacts

npx openspec validate --specs --strict
./docker/ops/verify.sh
```

Use os equivalentes via `docker compose exec -T php` quando o host não tiver as
dependências. Não invente comandos ou targets. Falha atribuível ao diff deve ser
corrigida. Falha preexistente deve ser demonstrada com baseline, mas continua
impedindo a declaração global de “pronto para produção” até ser resolvida ou
formalmente retirada do critério pelo usuário.

Antes dos canários, valide stack, readiness, filas, scheduler, vault, contrato,
Termo, procurações, poderes, orçamento e flags sem imprimir os valores. Execute
o probe como o usuário da aplicação e grave artefatos somente em diretório
ignorado e com acesso restrito. Exemplo de formato, adaptando IDs sem expô-los:

```bash
docker compose exec -T --user www-data php php artisan serpro:e2e-probe \
  --office=<OFFICE_ID_PILOTO> \
  --client=<CLIENT_ID_PILOTO> \
  --only=<OPERATION_KEYS_DO_LOTE> \
  --artifact-dir=/var/www/html/storage/app/serpro-e2e-probe/<RUN_ID> \
  --json
```

Não execute `--all` cegamente enquanto payloads dependentes, orçamento e
mutações não estiverem gated. A revisão final pode usar `--all` somente quando
o comando respeitar manifests, dependências, limites e falhar para qualquer
linha sem `PASS_REAL_*`.

## Revisão final independente

Quando o ledger indicar que todas as 98 produtivas estão prontas, designe um
validator/reviewer que não implementou o último lote. Ele deve:

1. refazer o diff oficial × snapshot × ledger e provar contagem 1:1;
2. conferir que 21 não produtivas continuam não executáveis;
3. auditar cada linha produtiva em backend, API, UI, testes e evidência real de
   `PRODUCTION_CANARY`, separada das evidências `PASS_TRIAL`;
4. rejeitar evidência antiga que não corresponda ao código final;
5. rodar todos os gates técnicos;
6. executar jornadas browser desktop/mobile por família, incluindo ações e
   estados, não apenas screenshot de página carregada;
7. executar/revalidar uma chamada real de consumo contratado,
   semanticamente válida, por operação;
8. validar assincronismo, documentos, cache, bilhetagem, budget e mutações;
9. varrer segredos, dados fiscais e isolamento cross-tenant;
10. produzir relatório final em pt-BR com tabela 119:1 e uma única linha
    `VERDICT: PASS` ou `VERDICT: FAIL`.

## Definição global de pronto

Só declare `VERDICT: PASS` quando, simultaneamente:

- o catálogo oficial vigente estiver espelhado 1:1;
- todas as operações `PRODUCTION` estiverem `READY_PRODUCTION`;
- cada operação produtiva tiver `PASS_REAL_*` fresco, `simulated=false` e
  proveniência `PRODUCTION_CANARY`;
- nenhum 4xx/5xx, bloqueio, placeholder, dependência ausente ou evidência antiga
  estiver sendo contado como sucesso;
- todos os fluxos assíncronos tiverem estado terminal validado;
- toda mutação tiver homologação e canário autorizado conforme o manifest;
- UI, desktop/mobile, API, tenancy, RBAC, documentos e observabilidade estiverem
  completos;
- os gates backend, frontend, OpenSpec, browser, arquitetura e segurança
  passarem;
- não houver segredo ou dado fiscal indevido em diff/evidência;
- as 21 não produtivas continuarem inventariadas e bloqueadas;
- matriz, evidências piloto e runbooks refletirem o estado final verdadeiro;
- não restar blocker.

Se faltar credencial, poder e-CAC, contrato comercial, dado piloto aplicável,
aprovação de mutação ou teto de custo, não reduza o critério e não invente
PASS. Entregue `VERDICT: FAIL` ou `BLOCKED_EXTERNAL`, liste por
`operation_key` exatamente o que falta, conclua todo trabalho independente e
indique a ação mínima necessária para retomar.

Não commite, não faça push, não altere configuração Git e não arquive changes
sem a autorização correspondente e sem PASS real. O sucesso é o produto
comprovadamente pronto — não o número de arquivos alterados nem o número de
requisições tentadas.
