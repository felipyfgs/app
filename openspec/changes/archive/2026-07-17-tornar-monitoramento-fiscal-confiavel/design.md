## Context

O read model de módulos já possui o vocabulário canônico de nove situações e o overview já transporta `data_origin`, `is_synthetic`, `source_label` e `as_of`. Entretanto, `ModuleCountersDto` publica apenas cinco situações, o total e os contadores podem ser calculados com escopos diferentes, e a UI compartilhada não apresenta a proveniência recebida. Como consequência, uma carteira inteiramente bloqueada ou desconhecida pode mostrar total positivo com todos os KPIs em zero, enquanto dados demonstrativos mantêm aparência produtiva.

No frontend, `useFiscalModulePortfolio` já protege parte das respostas pela época da sessão e calcula origem sintética, mas `MonitoringModuleTable` não recebe esses metadados. `KpiStrip` codifica somente cinco estados. O vazio compartilhado considera alguns defaults `all` como filtros ativos e chama o resolvedor com loading fixo em falso.

A change ativa `reorganizar-rotas-monitoramento` define rotas canônicas e preserva todos os módulos visíveis. Esta implementação deve entrar após seus gates, reutilizar os arquétipos `HOME`, `LIST` e `INBOX` do template fixado e não alterar navegação, rotas ou shell. As APIs continuam tenant-scoped sob Sanctum e `EnsureOfficeContext`; nenhum endpoint de plataforma, migration, job Horizon ou chamada SERPRO é necessário.

As páginas, porém, não compartilham o mesmo tipo de retorno. PGDAS-D e DCTFWeb combinam campos estruturados e documentos; MIT, Caixa Postal, cadastros e processos retornam dados estruturados; SITFIS conclui de forma assíncrona com PDF; dashboard, Declarações, Guias e detalhe do cliente são agregadores; DASN-SIMEI e documentos de e-Processo não estão produtivos; FGTS/eSocial nem sequer pertence à Integra Contador. A change passa a fixar essas diferenças em `page-payload-matrix.md`, usando o catálogo oficial versionado como fonte de coordenadas e estado.

## Goals / Non-Goals

**Goals:**

- Tornar a proveniência e a idade fiscal do dado visíveis nas superfícies compartilhadas.
- Fazer contadores e total representarem uma partição exaustiva da mesma carteira.
- Representar estados sem resultado sem aparência ou ação de sucesso.
- Reduzir a faixa de KPIs ao que possui valor operacional, mantendo todos os estados filtráveis.
- Corrigir a distinção entre loading, vazio canônico e vazio filtrado.
- Preservar isolamento por `CurrentOffice` inclusive diante de respostas assíncronas atrasadas.
- Fixar para cada página sua responsabilidade, operações oficiais, tipo de retorno e onde campos/documentos aparecem.
- Permitir acesso tenant-scoped somente a PDF/recibo realmente persistido, mantendo payload bruto e XML fora da superfície pública.

**Non-Goals:**

- Corrigir em profundidade envelope `body`/`dados`, codecs, schemas e mappers específicos de cada operação SERPRO.
- Fazer redesign visual amplo ou implementar todos os campos possíveis de cada família além da projeção útil definida na matriz.
- Ocultar, remover ou reordenar módulos e rotas.
- Alterar filtros/presets, paginação server-side ou arquétipos do template.
- Refatorar o dashboard para um endpoint agregado ou resolver N+1.
- Corrigir Guia, Caixa Postal, calendário, TOTP ou mutações fiscais.
- Alterar drivers, flags, allowlists, bilhetagem, filas ou persistência.
- Emitir documento novo, transmitir declaração, encerrar apuração, alterar benefício ou executar outra mutação fiscal.
- Executar live smoke, habilitar canais, tratar ticket SERPRO, jurídico ou LGPD.

## Decisions

### Cada página possui um contrato de superfície verificável

Um registro backend de contratos SHALL cobrir individualmente todas as rotas de `page-payload-matrix.md`. Cada entrada terá `surface_key`, rota canônica, responsabilidade, canal de origem, `operation_keys`, estado oficial, `result_kind` e política de evidência. `operation_keys`, `idSistema`, `idServico`, rota lógica e versão SHALL ser resolvidos pelo catálogo oficial versionado; páginas e componentes não farão hard-code dessas coordenadas.

Os tipos de retorno serão `STRUCTURED`, `PDF`, `ASYNC_PDF`, `AGGREGATE` ou `UNAVAILABLE`:

- `STRUCTURED` renderiza somente projeção tipada/allowlisted no detalhe da própria página;
- `PDF` publica ação de documento apenas quando o artefato existe;
- `ASYNC_PDF` mostra processamento até o artefato conclusivo existir, sem expor protocolo;
- `AGGREGATE` delega detalhe e documento ao módulo de origem, sem duplicar payload;
- `UNAVAILABLE` explica o estado oficial e não produz linha, documento ou ação sintética.

Uma operação fora de `PRODUCTION`, ausente no catálogo ou incompatível com o contrato falhará fechada. A alternativa de inferir capacidade pelo nome da página foi rejeitada porque DASN-SIMEI, FGTS, Caixa Postal e e-Processo mostram que módulo, canal e tipo de retorno não são intercambiáveis.

### Documento público é referência a evidência, não conteúdo do payload

O DTO público de um item poderá trazer `document.available`, `kind`, `label`, `content_type`, `observed_at`, `source_surface`, `source_label`, `href` e `unavailable_reason`. `href` será gerado exclusivamente no backend a partir de `FiscalEvidenceArtifact` pertencente ao `CurrentOffice`; a UI não montará URL por convenção nem mostrará botão antecipadamente. `operation_key`, sistema, serviço, rota e versão permanecem no contrato backend e não são coordenadas fornecidas à UI.

PDF e recibo autorizados usarão a rota tenant-scoped existente e `FiscalEvidenceStore::readAuthorized`, com nome/content type sanitizados e `Cache-Control: no-store`. Ausência de artefato será `STRUCTURED_ONLY`, `PROCESSING`, `NOT_SUPPORTED`, `NOT_PRODUCTION` ou `NOT_COLLECTED`, nunca um link quebrado.

O envelope SERPRO, `dados` bruto, Base64, cabeçalhos, tokens, coordenadas (`operation_key`, sistema, serviço, rota e versão), protocolos, hashes, `run_id`, `vault_object_id`, paths e XML bruto MUST NOT integrar resposta tenant pública. Se a resposta integral ou XML for retido para auditoria, ficará no `SecureObjectStore`; o painel mostrará apenas campos de negócio allowlisted e metadados sanitizados. Fallback que despeje JSON bruto quando um mapper falhar foi rejeitado por vazar dados e transformar incompatibilidade técnica em falsa evidência de produto.

### Agregadores preservam a origem do documento

Dashboard, Declarações, Guias e detalhe do cliente não ganham uma operação SERPRO artificial. Seus itens carregarão `source_surface`/`source_label` sanitizados e deep-link ou `href` produzido pela projeção originadora. Isso evita cópia divergente de PDF, perda de modalidade e conclusões como “pago” ou “entregue” sem correlação oficial.

Caixa Postal produtiva não documenta anexos, MIT não produz PDF, a consulta simples de vínculos não produz comprovante e os documentos do e-Processo permanecem em prospecção. Nessas superfícies, o contrato proíbe ação de documento mesmo que o layout genérico suporte uma.

### Agregação única produz contadores e total

O backend expandirá `ModuleCountersDto` com `blocked`, `unknown`, `unsupported` e `not_applicable`. A agregação inicializará todas as chaves a partir de `FiscalSituation::cases()` e agrupará cada cliente pelo `situationSqlExpression` no escopo que aplica busca, competência, submódulo, cliente, entrega, cobertura e modalidade, mas remove `situation`.

O mesmo resultado agrupado produzirá os nove contadores e `total_clients`, evitando duas consultas com filtros divergentes. A listagem paginada continuará aplicando `situation`; clicar num KPI muda a lista, não a distribuição usada como referência pelo overview. Criar um contador genérico `other` foi rejeitado porque esconderia quebra do contrato canônico. Calcular a diferença no frontend também foi rejeitado porque não identifica qual situação está ausente.

A alteração da API é aditiva: clientes antigos podem ignorar as quatro novas chaves. O frontend será tolerante durante deploy escalonado, preenchendo chave ausente com zero, mas os testes do backend exigirão o contrato completo.

### Proveniência fiscal não usa timestamp de transporte

`MonitoringModuleTable` receberá `dataOrigin`, `dataOriginLabel`, `sourceLabel` e `asOf` do overview. `DEMO` e `SIMULATED` usarão um alerta persistente antes dos KPIs com o texto de ausência de validade fiscal; `LIVE` usará metadado compacto de fonte e observação. Origem ausente falhará fechada para `Origem não informada`. `as_of` nulo produzirá `Sem observação oficial`.

`lastGoodAt`, que descreve sucesso de uma requisição da aplicação, não substituirá `as_of`, que descreve observação fiscal. Usar `new Date()` como frescor foi rejeitado porque faria uma resposta antiga parecer atual. O dashboard mostrará badge por módulo e alerta global quando houver origem sintética; seus indicadores declarados produtivos excluirão overviews sintéticos, embora cada carteira demonstrativa ainda possa exibir seus números sob o aviso.

### Um catálogo dirige filtros e KPIs

Os tipos TypeScript de `FiscalModuleCounters` e `FiscalKpiKey` passarão a cobrir os nove códigos do enum PHP. Rótulo, ícone, tom e filtro continuarão derivados do catálogo fiscal compartilhado, sem strings específicas por página.

`KpiStrip` sempre exibirá `Total`; para os estados, exibirá contagem positiva e conservará o item ativo mesmo em zero. O filtro estruturado continuará oferecendo todos os estados. `UNKNOWN`, `UNSUPPORTED`, `BLOCKED` e `ERROR` usarão suas semânticas não positivas existentes. Manter todas as cápsulas zeradas foi rejeitado por ocupar espaço sem orientar ação; removê-las também do filtro foi rejeitado porque impediria consultas explícitas.

### Estado vazio usa filtros normalizados e loading real

Uma função compartilhada avaliará filtros ativos sobre `normalizeMonitoringFilters`, tratando `all`, string vazia e `null` como defaults. `ModuleDataTable` passará `props.loading` ao resolvedor de estado e preservará erro, dados anteriores e refresh como estados distintos. Assim, a primeira carga não será anunciada como vazia e uma carteira sem filtros reais usará o vazio canônico.

### Troca de Office invalida todo o estado fiscal visível

O composable continuará usando a época de sessão, mas limpará overview, origem, linhas, contadores e seleção ao mudar `CurrentOffice`, antes da nova carga. Sequência e `AbortSignal` impedirão que resposta do Office anterior seja aplicada. O backend continuará recebendo o `Office` de `CurrentOffice`; `office_id` em query ou body não participará do escopo.

Não haverá nova rota ou middleware. A alternativa de manter o overview anterior durante a troca foi rejeitada porque mistura visualmente tenants mesmo que por poucos milissegundos.

### Aceite é inteiramente offline

Testes Laravel cobrirão os nove estados, soma igual ao total, remoção do filtro de situação no overview e isolamento por Office. Testes Vitest cobrirão banner/badge, KPI enxuto, fallback de origem/frescor, loading/vazio e descarte na troca de Office. Os gates serão `pint --test`, testes backend focados, `pnpm run test:gate`, `pnpm run generate`, fidelity/artifacts aplicáveis e validação OpenSpec estrita. Nenhum teste dependerá de credencial, rede SERPRO, flag ligada ou consumo faturável.

## Risks / Trade-offs

- [Consumidor antigo assume exatamente cinco chaves] → mudança aditiva, contrato documentado e deploy backend antes do frontend.
- [Situação SQL devolve valor fora do enum] → normalizar fail-closed para `UNKNOWN` e cobrir a invariável soma = total.
- [Módulo sintético ainda é navegável] → aviso persistente e exclusão de agregados produtivos; visibilidade permanece por restrição da change de rotas.
- [Resposta atrasada mistura Offices] → limpar estado na época da sessão, cancelar requisições e testar concorrência de troca.
- [Timestamp técnico parece frescor fiscal] → manter `lastGoodAt` e `as_of` semanticamente separados e usar fallback textual explícito.
- [Alteração quebra fidelidade visual] → inserir conteúdo dentro do body dos arquétipos existentes, sem trocar shell, navbar, toolbar, tabela ou master-detail.
- [A change parece corrigir a veracidade de todos os campos] → non-goals e follow-ups deixam explícito que codecs/mappers e colunas por família continuam separados.
- [Página anuncia documento que a operação não retorna] → contrato por superfície validado contra catálogo e teste que exige artefato real antes de publicar `href`.
- [Download revela outro tenant ou conteúdo sensível] → resolução obrigatória por `CurrentOffice`, leitura autorizada do cofre, resposta sem IDs/paths internos e testes cruzados de Office.
- [Payload bruto vira fallback de UI] → DTO allowlisted e teste de ausência de envelope, Base64, XML, protocolo, hash e IDs internos em todas as respostas cobertas.
- [Consulta agrupada fica mais cara] → reutilizar o escopo SQL já existente e validar plano/tempo com fixtures locais; nenhuma chamada externa ou bilhetagem é adicionada.

## Migration Plan

1. Concluir a baseline já planejada de contadores, proveniência, KPIs e estados compartilhados.
2. Introduzir o registro de contratos das superfícies e validá-lo contra o catálogo oficial versionado.
3. Publicar o DTO aditivo de documento/evidência e remover payload bruto das respostas tenant cobertas.
4. Aplicar e testar a matriz página por página, mantendo os arquétipos e rotas existentes.
5. Executar gates backend/frontend e validação OpenSpec; depois sincronizar, arquivar e commitar código, main spec e archive no mesmo dia.

Rollback: reverter a apresentação frontend preservando as chaves extras da API, que são compatíveis; se necessário, reverter depois o agregador backend. Não há migration, alteração de dado persistido ou chamada externa a desfazer.

## Open Questions

Nenhuma para esta change. A matriz fixa responsabilidade, formato e exposição segura; correções profundas de codecs/mappers e campos adicionais continuarão em fatias posteriores por família fiscal.
