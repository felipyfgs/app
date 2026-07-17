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

A página manterá as tabs locais, mas selecionará `buildDctfwebColumns` ou `buildMitColumns`. DCTFWeb terá, sem seleção, Situação, Últ. Declaração, Ações, Enviar, Cliente, Rastreio de envio, Última Busca e Histórico de Busca. A estrutura de lista, `UTable` e classes de cabeçalho/célula partem de `.reference/nuxt-dashboard-template/app/pages/customers.vue`; a largura permanece fluida com scroll horizontal.

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

## Riscos / Trade-offs

- [PDF oficial muda o texto] → presença do PDF ainda confirma declaração; tipo, recibo e sem movimento permanecem opcionais e versionados por parser.
- [Calendário sem feriados oficiais] → estado fica `UNVERIFIED`, evitando falso atraso.
- [Dados legados sem categoria] → migration preenche `GERAL_MENSAL` e preserva IDs/relacionamentos.
- [Change PGDAS-D/PGMEI evolui em paralelo] → refatoração da comunicação será aditiva e manterá os construtores e endpoints existentes.
- [Tabela larga em mobile] → largura mínima e scroll horizontal, sem comprimir ou reordenar colunas.

## Plano de migração

1. Adicionar categoria/estado às declarações e criar observações sem remover dados.
2. Preencher registros existentes com `GERAL_MENSAL` e `UNVERIFIED`, depois substituir a unicidade por escritório, cliente, categoria e PA.
3. Publicar backend e frontend com flags atuais preservadas; rollback do código mantém as colunas aditivas inertes.
4. A remoção da migration só será segura antes de conter dados novos; após uso, rollback operacional será por código/flag, sem apagar evidências.

## Questões em aberto

Nenhuma. Categorias especiais permanecem no histórico, comunicação é somente template e a imagem é a fonte de verdade da grade principal.
