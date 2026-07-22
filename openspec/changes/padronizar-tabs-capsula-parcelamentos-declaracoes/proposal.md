## Why

As faixas locais de Parcelamentos e Declarações usam `UTabs` em formato pill, mas sua composição ainda diverge da cápsula de situação usada como referência em Simples Nacional: além das diferenças estruturais, os itens locais não exibem a badge numérica nativa do Nuxt UI. Sem contadores reais por modalidade ou obrigação, controles visualmente equivalentes comunicam quantidades de formas diferentes.

## What Changes

- Fazer Parcelamentos e Declarações consumirem o mesmo contrato estrutural da cápsula de KPI de Simples Nacional: `ShellScrollableTabs`, `size="md"`, variante pill/primary canônica e wrapper limitado à largura do pai.
- Preservar a ação compacta `Operações` junto ao seletor de Declarações, isolando-a do cálculo de largura interno da faixa de tabs.
- Remover declarações visuais redundantes nas páginas e deixar variante/cor sob os defaults canônicos do componente compartilhado.
- Incluir a badge `badge` nativa em todas as tabs locais, alimentada por contagens tenant-scoped reais e estáveis ao alternar a tab.
- Expor as contagens dimensionais no `metrics.tab_counts` do overview já consumido pela carteira, sem chamadas HTTP adicionais por item.
- Atualizar testes para comparar o markup estrutural das três superfícies e validar scroll contido, proporção, badges e ausência de overrides locais.
- Non-goals: redesenhar o shell; alterar modalidades, obrigações, filtros ou rotas; criar contagens sintéticas; habilitar SERPRO live, mutações fiscais, flags, canais SEFAZ ou serviços `mei`/`mei-worker`; usar targets de backup/restore indisponíveis.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `panel-scrollable-tabs-overflow`: amplia o contrato compartilhado para exigir que tabs locais de Parcelamentos e Declarações reutilizem a mesma cápsula, proporção, badge de contagem e contenção de largura da faixa KPI de Simples Nacional.

## Impact

- Web Nuxt: `apps/web/app/pages/monitoring/installments.vue`, `apps/web/app/pages/monitoring/declarations.vue`, tipo do overview e testes Vitest das tabs locais; o shell e `ShellScrollableTabs` permanecem a fonte visual canônica.
- API Laravel: o read model de overview passa a publicar `metrics.tab_counts` para Parcelamentos e Declarações, com cobertura Feature; sem rota, persistência, chamada SERPRO ou infra nova.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `panel-scrollable-tabs-overflow` em `openspec/specs/` e o contrato visual atual de `MonitoringKpiStrip`/Simples Nacional.
- Depende de: `completar-central-declaracoes-serpro` no marco `apply` e `refatorar-ui-ux-parcelamentos` no marco `apply`.
- Capability/contrato e marco exigido: composição vigente das tabs e read model de overview de Declarações e Parcelamentos; relação `coordenada`, pois esta change adiciona metadado público de contagem sem alterar operações fiscais.
- Desbloqueia: paridade visual verificável entre as três faixas de cápsulas.
- Paralelismo: pode avançar após preservar o conteúdo já aplicado pelas duas changes upstream; gates devem auditar o diff direcionado para não sobrescrever operações declarativas, modalidades ou detalhe de pedidos.
