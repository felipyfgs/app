## Context

`/monitoring/installments` usa `MonitoringModuleTable`, o mesmo shell estrutural das carteiras DCTFWeb e Simples Nacional, mas a composição específica de Parcelamentos divergiu: dez tabs técnicas aparecem em uma faixa horizontal e um alerta neutro repete permanentemente a disponibilidade do catálogo. Em 1366×639, essa introdução consome altura e compete com KPIs, filtros e tabela. A grade também mantém colunas redundantes para atraso e guia, embora essas informações já tenham contexto em parcelas, situação e detalhe do pedido.

A change upstream `monitorar-todos-parcelamentos-serpro` já implementou o contrato funcional, tipos, catálogo, APIs e detalhe local. Esta change é deliberadamente de composição visual e preservará o estado atual desses arquivos, sem reescrever o trabalho da upstream.

## Goals / Non-Goals

**Goals:**

- Aproximar hierarquia e densidade da carteira dos padrões já usados por Simples Nacional e DCTFWeb.
- Tornar a seleção entre onze opções compreensível e compacta em desktop e mobile.
- Colocar situação e identidade fiscal em uma spine visual previsível, reduzindo colunas redundantes.
- Manter todas as informações e ações fiscais acessíveis, inclusive os estados PAEX/SIPADE em prospecção.
- Cobrir a estrutura, a disponibilidade fail-closed e os principais rótulos por testes automatizados.

**Non-Goals:**

- Alterar `MonitoringModuleTable`, o shell, a navegação ou outras carteiras.
- Mudar endpoints, filtros server-side, paginação, projeções, RBAC ou persistência.
- Disparar consultas ao abrir detalhes ou habilitar qualquer operação não produtiva.
- Adicionar dependências frontend, serviço Compose ou configuração de infraestrutura.

## Decisions

### Usar uma tab rolável por modalidade oficial

O bloco `submodules` continuará no mesmo ponto estrutural do shell e receberá `ShellScrollableTabs` com onze divisões compactas: Todos, as oito modalidades produtivas e PAEX/SIPADE. Cada tab produtiva enviará exatamente um código ao filtro `modality`; PAEX e SIPADE aparecerão em tabs próprias com o sufixo “em prospecção” e permanecerão desabilitadas.

Essa escolha atende diretamente ao acesso rápido solicitado para cada tipo e continua baseada apenas nas modalidades que o catálogo Integra-Parcelamento realmente fornece. Rótulos curtos preservam a leitura; a faixa usa scroll touch contido em viewports estreitas. A instância usa o mesmo `size="md"` e o mesmo `ui` canônico da faixa de KPIs, sem overrides locais de `list` ou `trigger`, para que as duas proporções sejam idênticas. Um `USelectMenu` foi descartado porque esconderia as modalidades e exigiria uma interação adicional.

### Comunicar disponibilidade na própria tab

O alerta neutro permanente será removido. As tabs “PAEX · em prospecção” e “SIPADE · em prospecção” carregarão o estado indisponível e permanecerão desabilitadas. Alertas continuarão reservados a falhas reais de catálogo/overview, alinhando o componente ao propósito de feedback persistente do Nuxt UI.

### Reordenar e condensar a spine da tabela

A grade passará a priorizar `Situação`, `Modalidade`, `Pedido`, `Saldo`, `Parcelas`, `Próxima parcela`, `Cliente` e `Ações`. Atraso aparecerá dentro do resumo de parcelas, com cor semântica de erro somente quando houver contagem; o acesso à guia/pedido será consolidado em Ações. O detalhe completo, pagamentos e documento continuam no slideover local.

Essa composição reduz ruído e segue o ritmo das carteiras de referência — estado no início, cliente antes dos controles finais — sem remover dados. Manter as dez colunas atuais foi descartado porque comprime conteúdo e cria dois atalhos para o mesmo pedido.

### Preservar shell, cores semânticas e responsividade existentes

Não haverá CSS global nem cores Tailwind brutas. A mudança utilizará `text-muted`, `text-highlighted`, `text-error` e componentes Nuxt UI. `ShellScrollableTabs` preservará scroll touch contido no mobile; a tabela continuará usando o comportamento mobile do `MonitoringModuleDataTable` e `horizontal-scroll=false`.

## Mapa de dependências

```text
monitorar-todos-parcelamentos-serpro (C0, apply)
                    |
                    v
refatorar-ui-ux-parcelamentos (C1)
  N0 composição Vue + teste estrutural
                    |
                    v
  N1 gates frontend/OpenSpec
```

- Ownership upstream: catálogo, tipos, API client, utilitário `installments.ts`, monitoramento em lote e conteúdo funcional do slideover.
- Ownership desta change: composição e spine visual de `installments.vue`, mais asserções de UX no teste da carteira.
- Arquivo compartilhado: `apps/web/app/pages/monitoring/installments.vue`; editar por patch mínimo sobre o estado atual e auditar o diff para não perder o trabalho upstream.
- Marco coordenado: a upstream já atingiu `apply` nas tasks de interface; os gates frontend validam as duas changes em conjunto.
- Rollout: alteração SPA sem migração; rollback consiste em reverter apenas o patch desta change, mantendo contratos e dados upstream.

## Risks / Trade-offs

- [Faixa de tabs extensa] → Usar rótulos curtos e `ShellScrollableTabs`, com scroll touch contido e sem overflow da página.
- [Informação de atraso ou guia ficar escondida] → Integrar atraso à célula Parcelas e manter acesso explícito “Ver pedido” em Ações, além do documento já disponível.
- [Conflito com trabalho local/upstream] → Aplicar patches pequenos, não formatar o arquivo inteiro e revisar `git diff` limitado aos blocos alterados.
- [Regressão em viewport estreita] → Seletor full-width, texto truncável, cores semânticas e gates de fidelity/artifacts; nenhuma nova largura mínima na tabela.
- [Bilhetagem SERPRO acidental] → Não tocar em handlers de consulta; abrir seletor, filtrar ou abrir slideover continua sem egress.
- [Vazamento entre offices, segredos ou kill switches] → Nenhum contrato de dados, request, storage, log ou configuração é alterado.

## Migration Plan

1. Atualizar a composição específica de Parcelamentos e os testes no mesmo nível.
2. Executar Vitest direcionado, lint, typecheck, generate, suíte completa, fidelity e artifacts.
3. Validar a delta OpenSpec e as main specs em modo strict.
4. Fazer deploy pelo fluxo SPA normal; não há migração de banco nem flag nova.

## Open Questions

Nenhuma. Os padrões de referência, a viewport e o catálogo funcional já delimitam as decisões necessárias.
