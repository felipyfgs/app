## Context

`/monitoring` usa `UDashboardPanel` e chama, no `onMounted`, o client tipado de `GET /api/v1/fiscal/monitoring/insights`. O agregador Laravel já lê pendências, findings, mensagens/alertas e-CAC, declarações e overviews de módulos no escopo do `Office`, com isolamento de falhas por seção. A tela atual, porém, apresenta todos os cards com peso semelhante, usa zeros em alguns estados sem cobertura e mede gráficos a partir do ref de um componente Vue, o que torna a renderização dependente do proxy interno em vez de um elemento DOM estável.

O projeto é SPA (`ssr:false`), usa Nuxt UI 4 e exige o shell do arquétipo local. Há uma change ativa de caixa postal com edições próprias no backend; esta change não altera sua orquestração, jobs, migrations ou contratos de sincronização.

## Goals / Non-Goals

**Goals:**

- Oferecer uma leitura operacional imediata, com números reais do `Office`, prioridades e destinos acionáveis.
- Distinguir valor zero de ausência de dados, falha parcial e módulo não suportado.
- Manter um único fetch agregado, tenant-safe e sem egress, com refresh manual e proteção por `sessionEpoch`.
- Corrigir medição/renderização dos gráficos e o overflow do corpo do `UDashboardPanel`.
- Reusar componentes Nuxt UI, tokens semânticos e breakpoints do arquétipo.

**Non-Goals:**

- Criar séries históricas, metas fiscais, scoring ou estimativas.
- Disparar refresh SERPRO em background ao abrir a página.
- Modificar pipelines, modelos ou sincronizadores da change de caixa postal.
- Redesenhar sidebar, navbar ou o shell global.

## Decisions

### 1. Estender o agregador, sem criar endpoint paralelo

O `MonitoringInsightsQueryService` continuará como read model único. Será adicionada uma seção segura de portfólio com a contagem real de clientes do `Office`, exposta em `kpis.clients_total`; falha dessa contagem retorna `null` e entra em `partial_errors`, sem transformar indisponibilidade em zero. Os demais KPIs continuam derivados dos read models existentes.

Alternativa considerada: compor várias rotas de carteira no browser. Rejeitada por aumentar requests, permitir snapshots com instantes diferentes e duplicar regras de cobertura na UI.

### 2. Hierarquia por decisão operacional

A página terá quatro camadas: cabeçalho e frescor; faixa de KPIs; grid prioritário com pendências/atividade e saúde fiscal; contexto analítico com RBT12, e-CAC e declarações; consulta manual colapsada no fim. Cards com domínio próprio apontam para a rota canônica correspondente.

Alternativa considerada: manter a ordem 8/4 atual. Rejeitada porque a coluna lateral acumula quatro conceitos sem hierarquia e torna os módulos críticos pequenos demais em viewport largo.

### 3. Estados honestos como contrato compartilhado

KPIs usam `—` quando sua seção falha ou ainda não tem dado, `…` apenas durante a primeira carga e `0` somente quando a API confirmou zero. Cards mostram skeleton/placeholder na carga inicial, empty state após sucesso sem registros e erro local quando a chave aparece em `partial_errors`. DIRF permanece não suportada, sem fração artificial.

### 4. Gráficos ancorados em elementos DOM

Os refs de `useElementSize` serão movidos de `UPageCard` para wrappers `div` nativos. A largura só habilita Unovis após medição positiva, e o fallback mantém altura estável. O corpo do painel receberá override apenas para `overflow-x-hidden`; scroll vertical e paddings continuam do tema `dashboard-panel.ts`.

### 5. Fetch cliente preservado e refresh concorrente seguro

Como a aplicação é SPA e o client `useApi()` centraliza sessão/401, será mantido o carregamento no cliente, com bloqueio por `sessionEpoch`. O botão de refresh não apaga o último snapshot válido durante uma atualização; erro total em uma nova tentativa preserva a última leitura com aviso de desatualização, enquanto a primeira falha continua fail-closed.

Alternativa considerada: migrar para `useAsyncData`. Não traz ganho de SSR neste app e exigiria adaptar o client autenticado e a invalidação de sessão sem resolver um problema observável.

## Mapa de dependências

- `N0`: contrato OpenSpec, DTO e utilitário puro de apresentação; ownership desta change.
- `N1`: agregador/teste Feature e componentes/testes web, dependentes do contrato N0 e paralelizáveis entre API e web.
- `N2`: composição final da página e correções de gráficos/overflow, dependentes dos contratos e componentes N1.
- `N3`: gates integrados API, web e OpenSpec.
- Upstream coordenado: `operacionalizar-caixa-postal-ecac-monitoramento-economico`; compatibilidade via `MailboxQueryService`/`MailboxMessage` já estáveis. Nenhum arquivo OpenSpec, job, migration ou serviço de sync daquela change será editado.
- Rollout: resposta é aditiva e consumidores antigos ignoram `clients_total`; a UI e o contrato tipado sobem juntos.
- Rollback: reverter a composição e o campo aditivo sem migração de dados.

## Risks / Trade-offs

- [Contagem de clientes confundida com adesão a todos os módulos] → rotular “Empresas no escritório” e não “100% monitoradas”.
- [Falha de uma query produzir zero enganoso] → isolar a seção e propagar `null` + `partial_errors`.
- [Vazamento entre offices] → filtrar explicitamente por `office_id` e ampliar teste Feature de tenant.
- [Consulta do dashboard gerar custo SERPRO] → limitar o serviço a read models locais; nenhum job ou client externo é chamado.
- [Conflito com trabalho local de caixa postal] → não editar os arquivos modificados daquela orquestração e validar o diff antes de cada patch.
- [Gráficos aumentarem custo/instabilidade visual] → limitar amostras, manter altura fixa e fallback textual acessível.
- [Segredos em API/log] → payload segue sanitizado; nenhum certificado, token ou conteúdo bruto é agregado.
- [Kill switches ou Compose MEI alterados acidentalmente] → nenhum config/Compose/flag faz parte dos arquivos da change.

## Migration Plan

1. Publicar o campo aditivo e seus testes no agregador.
2. Atualizar tipos, mapeamento e componentes web.
3. Substituir a composição de `/monitoring` e validar desktop/mobile, estados de carga/erro e links.
4. Rodar gates API, web e OpenSpec. Não há migration de banco.

## Open Questions

Nenhuma bloqueante. Séries históricas e indicadores de SLA ficam para uma capability futura, pois os read models atuais não oferecem histórico agregado suficiente para métricas honestas.
