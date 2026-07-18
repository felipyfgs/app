## Contexto

A rota `/monitoring/dctfweb` usa hoje uma única definição de colunas para DCTFWeb e MIT e oferece ações mutantes dentro do monitoramento. O adapter DCTFWeb envia chaves não oficiais, tenta uma segunda operação após falha e persiste o envelope JSON como evidência, embora `CONSRECIBO32` devolva `PDFByteArrayBase64`. O domínio já possui declarações, versões de evidência, cofre fiscal, portfólio tenant-scoped e comunicação template usados por PGDAS-D/PGMEI.

## Objetivos / Não objetivos

**Objetivos:**

- Reproduzir exatamente as oito colunas e a densidade da imagem, sem seleção ou colunas configuráveis na DCTFWeb.
- Tornar DCTFWeb e MIT cápsulas independentes no contrato e no renderer.
- Executar uma consulta `CONSRECIBO32` por cliente e PA congelado, com payload oficial e estado fail-closed.
- Validar e armazenar o PDF exclusivamente no cofre, expondo apenas descritores tenant-safe.
- Reutilizar a infraestrutura de comunicação em `TEMPLATE_ONLY` com contexto DCTFWeb isolado.

**Não objetivos:**

- Transmitir DCTFWeb, encerrar MIT, emitir DARF, afirmar pagamento ou ativar providers de comunicação.
- Fazer chamadas reais em testes, habilitar flags ou apagar projeções históricas.
- Interpretar categorias especiais na grade mensal geral.

## Decisões

### 1. Contratos e renderers separados por cápsula

A página manterá as tabs locais, mas selecionará `buildDctfwebColumns` ou `buildMitColumns`. DCTFWeb terá oito colunas fiscais: Situação, Últ. Declaração, Ações, Enviar, Cliente, Rastreio de envio, Última Busca e Histórico de Busca. Para perfis autorizados, a seleção técnica do arquétipo `customers.vue` poderá preceder essas colunas e alimentar exclusivamente consultas manuais somente-leitura. A estrutura de lista, `UTable` e classes de cabeçalho/célula partem de `.reference/nuxt-dashboard-template/app/pages/customers.vue`; a largura permanece fluida com scroll horizontal.

Alternativa rejeitada: manter a grade compartilhada e esconder colunas, pois isso preservaria a mistura de eixos e permitiria regressão na ordem visual.

### 2. Consulta observacional única e oficial

O monitor agendado resolverá para `dctfweb.consrecibo` e enviará `categoria: GERAL_MENSAL`, `anoPA: YYYY`, `mesPA: MM`. A competência esperada será o mês anterior no fuso do escritório e ficará congelada no `progress`; o `period_key` comercial não poderá sobrescrevê-la. Não haverá fallback automático para declaração completa.

Alternativa rejeitada: consultar recibo e relatório completo na mesma execução, por duplicar custo e confundir ausência com falha.

### 3. Documento seguro e observação imutável

Um decoder DCTFWeb localizará `PDFByteArrayBase64` no `dados` normalizado, aplicará Base64 estrito, limite de 10 MiB e assinatura `%PDF-`, e entregará apenas bytes validados ao versionamento existente. A observação registrará resultado, proveniência, categoria, PA, horário, run e razão sanitizada, sem payload fiscal. O histórico juntará observações, declarações e descritores de evidência; o download relerá o artefato com autorização do escritório.

Alternativa rejeitada: guardar o JSON de resposta, porque retém Base64 em banco/log e cria uma falsa evidência JSON.

### 4. Estado fail-closed separado da situação genérica

`DctfwebDeclarationState` terá `CURRENT`, `NO_MOVEMENT_VALID`, `DUE_WITHIN_DEADLINE`, `OVERDUE_NOT_FOUND` e `UNVERIFIED`. Documento produtivo válido confirma `CURRENT`; o parser pode especializar para `NO_MOVEMENT_VALID`. Ausência só vira atraso com consulta produtiva posterior ao prazo, aplicabilidade `APPLICABLE` e calendário oficial verificado. Simulação, parser inconclusivo, obrigação desconhecida e calendário não verificado resultam em `UNVERIFIED`.

O prazo mensal será calculado como o último dia útil do mês seguinte. Feriados só participam quando vierem de versão oficial verificada; sem essa fonte o estado não promove atraso.

### 5. Comunicação local isolada

A infraestrutura de `ClientCommunicationPreference` será parametrizada por `module_key` e `submodule_key`. O wrapper DCTFWeb usará `dctfweb/dctfweb`, mantendo `automatic_effective=false`. Prévia, rastreio e histórico são GETs locais; somente `ADMIN` e `OPERATOR` alteram o switch. O botão de envio abre a prévia, não dispara provider.

### 6. Credenciais segregadas por ambiente

O gateway oficial `TRIAL` usará o bearer próprio da demonstração, cifrado no `SecureObjectStore` e referenciado pelo contrato ativo no banco. O gateway `PRODUCTION` usará o par `access_token`/`jwt_token` obtido por OAuth mTLS com o PFX e Consumer Key/Secret do contrato produtivo. Configuração e `.env` conterão apenas URLs públicas; ausência do contrato ou do material exigido bloqueará a chamada antes do transporte.

Alternativa rejeitada: reutilizar o access token OAuth produtivo no gateway trial, pois o SERPRO recusa a chamada por assinatura de API, e guardar o bearer trial em `.env` contrariaria o ciclo de credenciais auditável do hub.

### 7. Consulta manual em massa e semântica do Trial

O botão principal da cápsula DCTFWeb chamará o endpoint dedicado de consulta DCTFWeb, uma vez por cliente, limitado a 100 alvos selecionados ou presentes na página. O PA informado no filtro vence; sem filtro, o backend resolve o mês anterior no fuso do escritório. A UI confirmará a ação, exibirá o total, manterá feedback enquanto enfileira e atualizará a carteira em janelas curtas após o despacho.

No ambiente `TRIAL`, o transporte usará o envelope fixo do cenário oficial `CONSRECIBO32`; token de procurador e poderes e-CAC não serão requisitos. A resposta terá proveniência `SERPRO_TRIAL`, será exibida como demonstração sem validade fiscal e nunca promoverá a declaração do cliente para estado produtivo. Mensagem de erro de negócio em HTTP 200 será falha normalizada, não sucesso.

Alternativa rejeitada: enviar os CNPJs reais ao mock Trial ou projetar o PDF demonstrativo como documento fiscal do cliente, pois ambos criariam uma falsa evidência. Em `PRODUCTION`, identidades reais, OAuth mTLS, procuração e poderes permanecem obrigatórios.

## Riscos / Trade-offs

- [PDF oficial muda o texto] → presença do PDF ainda confirma declaração; tipo, recibo e sem movimento permanecem opcionais e versionados por parser.
- [Calendário sem feriados oficiais] → estado fica `UNVERIFIED`, evitando falso atraso.
- [Dados legados sem categoria] → migration preenche `GERAL_MENSAL` e preserva IDs/relacionamentos.
- [Change PGDAS-D/PGMEI evolui em paralelo] → refatoração da comunicação será aditiva e manterá os construtores e endpoints existentes.
- [Tabela larga em mobile] → largura mínima e scroll horizontal, sem comprimir ou reordenar colunas.
- [Teste em massa no Trial repete o mesmo cenário SERPRO] → registrar apenas resultado técnico demonstrativo e manter situação fiscal `UNVERIFIED`.

## Plano de migração

1. Adicionar categoria/estado às declarações e criar observações sem remover dados.
2. Preencher registros existentes com `GERAL_MENSAL` e `UNVERIFIED`, depois substituir a unicidade por escritório, cliente, categoria e PA.
3. Publicar backend e frontend com flags atuais preservadas; rollback do código mantém as colunas aditivas inertes.
4. A remoção da migration só será segura antes de conter dados novos; após uso, rollback operacional será por código/flag, sem apagar evidências.

## Questões em aberto

Nenhuma. Categorias especiais permanecem no histórico, comunicação é somente template e a imagem é a fonte de verdade da grade principal.
