## Por quê

O monitoramento de Simples/MEI ainda expõe quatro superfícies genéricas e trata PGMEI como consulta de DAS, apesar de a operação observacional oficial ser a consulta anual de dívida ativa. A área precisa refletir as duas responsabilidades operacionais reais — PGDAS-D e PGMEI — sem misturar declaração anual, regime tributário interno ou operações fiscais mutantes.

## O que muda

- **BREAKING**: limitar `/monitoring/simples-mei` às cápsulas locais `Simples Nacional` (`PGDAS-D`) e `MEI` (`PGMEI`), removendo `Regime` e `DASN-SIMEI` das superfícies públicas, do portfólio e dos monitores automáticos.
- Rejeitar `REGIME` e `DASN_SIMEI` nas APIs de portfólio com `422` e redirecionar deep links legados apenas para a rota canônica, cuja cápsula padrão é `Simples Nacional`.
- Aplicar às duas cápsulas o mesmo template operacional de sete colunas; no PGMEI, dívida ativa, total em centavos e frescor alimentam Situação, tooltip e histórico, sem colunas fiscais independentes.
- Integrar exclusivamente `PGMEI/DIVIDAATIVA24/1.0` ao monitor automático e remover o mapeamento fictício `CONSULTAR_DAS`.
- Persistir projeção atual, observações imutáveis e itens normalizados de dívida, com isolamento por escritório, cliente e ano.
- Alternar os cinco anos mais recentes no ciclo diário e oferecer consulta manual, explícita e limitada a 100 clientes.
- Reutilizar preferências e rastreamento de comunicação em modo template, isolados por `PGDASD` e `PGMEI`, sem envio real.
- Manter internamente `RegimeApplicabilityService`, o domínio declaratório DASN-SIMEI e os dados históricos existentes.

Não são objetivos desta change: habilitar SERPRO live, emitir ou recalcular DAS, transmitir declaração, criar provider/webhook/job de comunicação, apagar dados históricos ou emitir parecer jurídico sobre os canais de contato.

## Capacidades

### Novas capacidades

- `pgmei-monitoring`: navegação pública com duas cápsulas, consulta e persistência de dívida ativa PGMEI, APIs tenant-scoped, controles de comunicação em modo template e interface especializada.

### Capacidades modificadas

Nenhuma.

## Impacto

- Backend Laravel: catálogo e codec SERPRO, scheduler fiscal, registro de superfícies, validação do portfólio, projeções de dívida, APIs tenant-scoped e reutilização da comunicação template.
- Banco PostgreSQL/SQLite de testes: migrations aditivas para projeções, observações e itens de dívida ativa; nenhum dado histórico será removido.
- Frontend Nuxt/Nuxt UI: duas cápsulas locais na rota canônica, tabela e modal PGMEI, filtro anual e remoção das superfícies Regime/DASN-SIMEI.
- Compatibilidade: deep links antigos redirecionam para a rota base; chamadas API aos submódulos removidos passam a falhar explicitamente com `422`.
