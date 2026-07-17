## 1. Contratos e superfícies

- [x] 1.1 Restringir o portfólio Simples/MEI a `PGDASD` e `PGMEI`, rejeitando `REGIME` e `DASN_SIMEI` com `422`
- [x] 1.2 Remover Regime e DASN-SIMEI do `MonitoringSurfaceRegistry`, da matriz frontend e dos monitores automáticos, preservando serviços internos e dados
- [x] 1.3 Mapear exclusivamente `PGMEI/DIVIDAATIVA24/1.0`, remover `CONSULTAR_DAS` e cobrir o payload anual oficial

## 2. Persistência e projeção PGMEI

- [x] 2.1 Criar migrations e models tenant-scoped para projeções, observações imutáveis e itens de dívida ativa
- [x] 2.2 Implementar codec do serviço 24 com leitura de `response.dados`, validação do ano e conversão decimal exata para centavos
- [x] 2.3 Projetar respostas produtivas válidas atomicamente e impedir promoção de falhas, simulações ou respostas ambíguas
- [x] 2.4 Incluir `detail.pgmei` com estado, frescor, contagem, total e última consulta válida na listagem

## 3. Consultas e APIs

- [x] 3.1 Alternar deterministicamente os cinco anos recentes no scheduler com uma chamada por cliente/ciclo
- [x] 3.2 Criar endpoint tenant-scoped de histórico local por cliente/ano, incluindo itens e DAS já existentes na Central de Guias
- [x] 3.3 Criar consulta manual explícita e confirmada por ano, atômica e limitada a 100 clientes
- [x] 3.4 Garantir que navegação, detalhes, prévia e rastreio não executem operações SERPRO ou fiscais mutantes
- [x] 3.5 Reutilizar preferências e rastreamento template com isolamento entre `PGDASD` e `PGMEI`, sem envio real

## 4. Interface

- [x] 4.1 Renderizar somente as cápsulas locais `Simples Nacional · PGDAS-D` e `MEI · PGMEI`, mantendo a rota canônica e os redirects legados
- [x] 4.2 Reconciliar PGMEI com as sete colunas da referência: Situação, Ações, Enviar, Cliente, Rastreio de envio, Última Busca e Histórico de Busca (dívida/total/frescor só em Situação/tooltip); ano corrente fixo sem seletor na UI
- [x] 4.3 Reconciliar Ações (prévia + menu), Enviar (switch linha + bulk no cabeçalho), Rastreio compacto, Última Busca e Histórico de Busca com menus e modais em modo template
- [x] 4.4 Resetar paginação/filtros exclusivos e descartar respostas obsoletas ao alternar cápsulas

## 5. Verificação e fechamento

- [x] 5.1 Cobrir contratos, projeções, isolamento, permissões, limites, zero mutações e preservação de aplicabilidade em testes Laravel
- [x] 5.2 Cobrir nas duas cápsulas a ordem exata das colunas da referência, ausência das colunas antigas, cores/ícones, tooltips, menus, responsividade, modais e troca rápida em testes Nuxt
- [x] 5.3 Executar Pint, testes Laravel, `pnpm run test:gate`, geração estática, fidelity e OpenSpec strict
- [x] 5.4 Sincronizar/arquivar a change e criar commit quando todo o software estiver verificado
