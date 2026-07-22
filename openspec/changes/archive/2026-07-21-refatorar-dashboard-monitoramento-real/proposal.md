## Why

O dashboard `/monitoring` já consulta read models reais, mas a hierarquia atual fragmenta a leitura operacional, mistura ausência de cobertura com zero e oferece pouco contexto para decidir qual módulo exige ação. A tela precisa transformar os dados existentes em um resumo fiscal confiável, responsivo e navegável, sem estatísticas sintéticas nem consultas externas durante a leitura.

## What Changes

- Reorganizar integralmente `/monitoring` em resumo operacional, atenção prioritária, saúde dos módulos e atividade recente, preservando o shell canônico `UDashboardPanel`/Nuxt UI.
- Evoluir o payload agregado com estatísticas reais e metadados de cobertura/frescor necessários para distinguir “zero”, “sem dados”, “não suportado” e “falha parcial”.
- Tornar KPIs e cards acionáveis, com links canônicos para as carteiras correspondentes e estados de loading, vazio, erro total e erro parcial consistentes.
- Corrigir contratos visuais dos cards, gráficos e corpo rolável do painel em desktop e mobile, incluindo acessibilidade e formatação pt_BR.
- Manter a consulta manual disponível em uma seção secundária, fora do primeiro viewport operacional.
- Adicionar testes Feature do contrato agregado e testes web do mapeamento, estados e estrutura responsiva.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `monitoring-insights-dashboard`: ampliar o contrato do resumo real, explicitar cobertura/frescor e substituir o layout denso fragmentado por uma hierarquia operacional responsiva e acionável.

## Impact

- API: `MonitoringInsightsQueryService`, DTO público e testes de `GET /api/v1/fiscal/monitoring/insights`.
- Web: página `/monitoring`, componentes `monitoring/insights`, tipos, utilitários de apresentação e testes Vitest/fidelity.
- Sem nova dependência externa, sem egress SERPRO e sem alteração de flags de produção.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: capability canônica `monitoring-insights-dashboard`, endpoint agregado existente e arquétipo `.local/reference/nuxt-dashboard-template`.
- Depende de: nenhuma change ativa; capability/contrato: nenhum; marco exigido: nenhum; relação: coordenada.
- Coordenação: `operacionalizar-caixa-postal-ecac-monitoramento-economico` pode evoluir read models de caixa postal em paralelo, desde que esta change consuma apenas o contrato local estável e não altere sua orquestração.
- Desbloqueia: uma leitura operacional confiável do monitoramento e futuras evoluções de indicadores por módulo.
- Condições de paralelismo: trabalho web e testes do agregador podem avançar em paralelo; alterações no serviço agregado devem preservar edições locais de caixa postal e isolamento por `Office`.

### Non-goals

- Disparar consultas SERPRO live, emitir parecer fiscal/jurídico ou executar mutações fiscais a partir do dashboard.
- Ativar feature flags, canais SEFAZ/MEI ou adicionar `mei`/`mei-worker` ao Compose.
- Criar dados demonstrativos, metas arbitrárias ou estimativas quando o read model não tiver cobertura.
- Implementar backup/restore ou outros targets de ops indisponíveis.
