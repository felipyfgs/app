## Contexto

A rota canônica de Simples/MEI já possui um shell compartilhado, porém ainda publica quatro submódulos e aplica uma tabela genérica a PGMEI. O contrato oficial observacional do PGMEI é anual e retorna dívida ativa por PA, tributo, valor, ente federado e situação; geração de DAS e atualização de benefício são operações mutantes distintas. Em paralelo, a implementação PGDAS-D em andamento criou uma fundação de comunicação template que pode ser compartilhada desde que o contexto fiscal seja parte da chave.

A solução deve preservar `RegimeApplicabilityService` como regra interna, manter DASN-SIMEI no domínio declaratório global, respeitar `CurrentOffice`, não promover snapshots legados e nunca transformar navegação ou abertura de modal em chamada faturável.

## Objetivos / Não objetivos

**Objetivos:**

- Expor apenas duas cápsulas locais na rota canônica e especializar PGMEI sem alterar a responsabilidade do PGDAS-D.
- Normalizar `DIVIDAATIVA24` em uma projeção anual auditável, idempotente e monetariamente exata.
- Oferecer histórico local, consulta manual explícita e comunicação somente em modo template.
- Tornar a remoção de Regime/DASN-SIMEI explícita nas APIs e compatível nos deep links legados.

**Não objetivos:**

- Emitir DAS, alterar benefício, transmitir DASN-SIMEI ou habilitar integração SERPRO produtiva.
- Apagar dados históricos ou remover a modelagem interna de regime tributário.
- Criar entrega real por e-mail/WhatsApp, provider, webhook ou scheduler de comunicação.

## Decisões

### 1. Cápsulas como estado efêmero da página

`Simples Nacional` e `MEI` serão estado local de `/monitoring/simples-mei`; troca de cápsula reinicia paginação e filtros exclusivos e invalida respostas assíncronas anteriores. Não serão criadas rotas filhas, query string ou itens de sidebar. Os paths legados redirecionam para a base e, portanto, abrem PGDAS-D.

Alternativa considerada: codificar a cápsula na URL. Foi rejeitada porque o requisito estabelece uma única superfície canônica e evita multiplicar módulos públicos.

### 2. Portfólio com rejeição explícita

O enum pode continuar representando conceitos usados fora do monitoramento, mas `allowedSubmodules`, a matriz frontend e `MonitoringSurfaceRegistry` aceitarão somente `PGDASD` e `PGMEI` para esta página. `REGIME` e `DASN_SIMEI` recebidos no filtro público resultam em `422`; não há fallback para PGDAS-D.

### 3. Codec dedicado ao serviço 24

O monitor PGMEI usa somente a chave `pgmei.dividaativa`, mapeada a `PGMEI/DIVIDAATIVA24/1.0`, rota `Consultar`, com payload `{ anoCalendario: "AAAA" }`. Um codec dedicado valida ano, interpreta `response.dados`, converte valores decimais para centavos sem `float` e preserva a situação original. O alias fictício `CONSULTAR_DAS` deixa de existir; operações 21–23 permanecem fora desta superfície.

### 4. Modelo projetado e histórico imutável

Serão criadas três estruturas tenant-scoped:

- projeção única por escritório, cliente e ano, contendo estado, total, contagem e última consulta válida;
- observação imutável por execução válida, com digest e totais;
- itens normalizados ligados à observação, com PA, tributo, centavos, ente e situação original.

`NO_ACTIVE_DEBT` significa apenas ausência no ano consultado. Falha, simulação ou resposta não interpretável gera `UNVERIFIED` e não substitui a última projeção válida. `freshness_state` é derivado na leitura: até sete dias `CURRENT`, depois `OUTDATED`, sem ocultar dívida.

### 5. Ciclo diário rotativo e consulta manual

Cada ciclo diário escolhe deterministicamente um dos cinco anos mais recentes e agenda no máximo uma chamada por cliente. A consulta manual exige ano, confirmação e lista de até 100 clientes; o backend valida o lote antes de criar qualquer execução. Falhas não disparam repetição automática nesta change.

### 6. Detalhes e DAS estritamente locais

A listagem recebe `detail.pgmei`. O histórico e os itens são lidos por endpoint local. DAS exibidos no modal vêm exclusivamente de `tax_guides`/Central de Guias já persistidos; abrir tabela, histórico, prévia ou rastreio não executa SERPRO.

### 7. Comunicação compartilhada com contexto fiscal

Preferências e eventos mantêm a chave de contexto `module/submodule`. PGDAS-D e PGMEI nunca compartilham switch, prévia ou rastreio. Para PGMEI, `execution_mode` permanece `TEMPLATE_ONLY`, `automatic_effective=false` e nenhuma ação gera dispatch/evento de entrega nesta change.


### 8. Template operacional PGMEI (sem colunas mensais do PGDAS-D)

PGMEI **não** repete as colunas Últ. Declaração e Sublimite (RBT12) do PGDAS-D — a consulta é anual de dívida ativa. As sete colunas de negócio, nesta ordem:

1. Situação — estado anual (dívida / sem dívida / não verificado); tooltip com ano, quantidade, total em centavos, frescor e última consulta
2. Ações — ícone de prévia + menu contextual
3. Enviar — switch `automatic_requested` (ou atalho de configuração quando canal/contato falta); cabeçalho com switch em massa da seleção
4. Cliente — razão social + CNPJ
5. Rastreio de envio — status + anexo local + abrir modal
6. Última Busca — data compacta; data/hora no tooltip
7. Histórico de Busca — botão lupa → modal local

A seleção autorizada é inserida pelo shell antes de Situação e não integra a contagem. Não existem colunas separadas para Dívida ativa, Total inscrito, Automático ou Detalhes.

**Ano-calendário:** a carteira PGMEI não expõe seletor de ano. A UI e a listagem usam o ano corrente; consultas manuais e histórico também operam nesse ano. Cobertura multi-ano permanece responsabilidade do scheduler/backend, não de um filtro de tela.
## Riscos / Trade-offs

- **Dados antigos ainda referenciam superfícies removidas** → migrations são apenas aditivas; o registro público deixa de resolvê-las sem apagar histórico.
- **Formato monetário variável da SERPRO** → parser decimal estrito, fixtures com inteiro, decimal e formato brasileiro e estado `UNVERIFIED` em ambiguidade.
- **Resposta tardia após troca rápida de cápsula** → chave de requisição/geração local impede que resposta obsoleta atualize a tabela ativa.
- **Rotação anual retarda a cobertura completa** → o ciclo diário cobre cinco anos em cinco ciclos, enquanto a consulta manual oferece correção explícita e limitada.
- **Compartilhamento acidental de preferências** → constraints e queries incluem `office_id`, `client_id`, `module` e `submodule`.

## Plano de migração

1. Aplicar migrations aditivas e publicar o backend com a nova validação e os endpoints locais.
2. Publicar o frontend com duas cápsulas e redirects legados.
3. Manter flags SERPRO desabilitadas/simuladas até configuração produtiva externa à change.
4. Começar a projeção somente na primeira consulta produtiva válida; não executar backfill de snapshots antigos.

Rollback de código restaura as superfícies anteriores sem perda, pois nenhuma tabela histórica é removida. As tabelas novas podem permanecer inativas durante rollback.

## Questões em aberto

Nenhuma questão bloqueante. A política operacional para habilitar consultas live e os textos finais de comunicação serão decididos fora desta change.
