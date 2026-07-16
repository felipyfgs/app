## Context

O frontend já possui um catálogo unificado em `NotesWorkspace`, com visão por empresa em `/docs` e visão por documento em `/docs/catalog`. NF-e, NFC-e, NFS-e e CT-e usam o mesmo contrato de documentos, mas a implementação da change `complete-cte-capture-with-distdfe-autxml-and-import` criou uma página filha de Configurações em `/settings/cte`, duplicou entradas de navegação e abriu uma exceção de autorização específica no middleware.

Essa página isolada reúne três preocupações diferentes: orientação de captura `autXML`, metadados sanitizados da identidade/A1 e pendências documentais; a saúde dos streams CT-e já existe em Sincronizações. A migração precisa preservar essas funções, papéis e isolamento por escritório sem manter CT-e como produto ou módulo independente.

## Goals / Non-Goals

**Goals:**

- estabelecer Documentos como única taxonomia funcional de NF-e, NFC-e, NFS-e e CT-e;
- tornar `/docs/catalog` o destino canônico da visão por documento e de todo deep-link documental de CT-e;
- integrar orientação `autXML`, cobertura e pendências CT-e ao contexto do catálogo, sem duplicar a saúde já exibida em Sincronizações;
- remover a rota, a navegação e a exceção de middleware de `/settings/cte`, mantendo redirect compatível;
- corrigir o requisito OpenSpec que colocou onboarding CT-e em Configurações;
- preservar permissões, troca de escritório, estados assíncronos e ausência de material sensível.

**Non-Goals:**

- alterar endpoints, persistência, projeções, cursores NSU, Scheduler ou captura CT-e;
- unificar serviços SEFAZ distintos ou esconder a separação operacional dos streams em Sincronizações;
- mover identidade fiscal, A1, PFX, senha ou flags sensíveis para Documentos;
- criar nova navegação, API ou design system exclusivo para CT-e;
- alterar o escopo escritural de NFS-e, NF-e, NFC-e ou MDF-e.

## Decisions

### D1 — CT-e é `kind=CTE`, não um módulo de produto

Sidebar, command palette e Configurações não terão destino CT-e próprio. CT-e será encontrado pelo grupo Documentos e pelo filtro `kind=CTE`, exatamente como NF-e e NFC-e são tipos filtráveis do mesmo catálogo.

A alternativa de apenas renomear `/settings/cte` foi rejeitada porque manteria a separação conceitual e continuaria duplicando navegação, autorização e estados.

### D2 — `/docs/catalog` é a rota canônica da visão por documento

`/docs` continua sendo a entrada do grupo Documentos e a visão por empresa; `/docs/catalog` representa a visão por documento. Links acionáveis de CT-e usarão `/docs/catalog` com query reproduzível, inicialmente `kind=CTE`, e poderão acrescentar contexto válido de cobertura ou pendência sem criar outra página.

`/settings/cte` será mantida somente como alias de migração e fará redirect com `replace` para `/docs/catalog?kind=CTE`, preservando parâmetros compatíveis. Ela não renderizará conteúdo nem permanecerá na navegação.

A alternativa de redirecionar todo `/docs` para `/docs/catalog` foi rejeitada porque a visão por empresa já é uma parte útil e distinta da mesma superfície Documentos.

### D3 — Conteúdo CT-e será decomposto por responsabilidade

O conteúdo hoje em `settings/cte.vue` será migrado da seguinte forma:

- orientação `autXML`, CNPJ copiável, metadados seguros e estado de habilitação: contexto de captura dentro de `/docs/catalog`, apresentado quando `kind=CTE` ou quando um deep-link solicitar ação CT-e;
- pendências/quarentena CT-e: fila documental no catálogo, com ações por papel e atualização da cobertura após resolução/importação;
- saúde, cursor, `maxNSU`, quiet e circuito `656`: permanecem em `/syncs`, que já separa stream do cliente e stream do escritório;
- cadastro/rotação de identidade e A1: permanecem em `/admin` com 2FA; o catálogo mostra somente metadados sanitizados e deep-link autorizado.

O componente de contexto CT-e deverá ser reutilizável pelo workspace do catálogo e não poderá virar um novo shell ou página top-level.

### D4 — A autorização volta a seguir a superfície de destino

Removida a página CT-e de Configurações, o middleware não precisará mais liberar uma exceção a `/settings` para VIEWER/OPERATOR. `/docs/catalog` seguirá as permissões normais de leitura de Documentos; importação e resolução permanecerão limitadas a ADMIN/OPERATOR, e qualquer ação de identidade/A1 continuará exigindo ADMIN com 2FA recente.

Na troca explícita de escritório, estado, requests e pendências CT-e do office anterior serão descartados usando o mesmo `sessionEpoch` já aplicado ao restante do catálogo.

### D5 — Backend e contrato documental não mudam

Os endpoints CT-e existentes podem continuar como adaptadores internos (`onboarding`, `coverage`, `pending`, `health`), mas a UI os consome sob a experiência Documentos. Não haverá migration de banco nem novo endpoint paralelo de catálogo. Listagem, detalhe, importação, exportação e download continuam usando a API canônica de documentos e `document_interests`, sempre tenant-scoped.

### D6 — Compatibilidade é redirect, não duas superfícies

Links existentes para `/settings/cte` não quebrarão imediatamente, porém o alias não terá layout Settings, item ativo, conteúdo duplicado ou autorização especial. Testes deverão garantir que uma navegação legada termina em `/docs/catalog` e que nenhuma referência navegável continua apontando para a rota antiga.

### D7 — A fonte OpenSpec concorrente será reconciliada antes do archive

A change ainda ativa `complete-cte-capture-with-distdfe-autxml-and-import` contém o requisito “Onboarding CT-e autXML no dashboard” com localização em Configurações. Durante o apply, esse delta deverá ser corrigido para a superfície Documentos antes que qualquer das duas changes seja arquivada. O delta desta change modifica requisitos que já existem nas main specs e acrescenta a regra canônica independente, evitando depender de um requisito ainda não sincronizado.

## Risks / Trade-offs

- **[Catálogo fica carregado com controles de captura]** → renderizar contexto CT-e apenas quando relevante e preservar a tabela como foco principal.
- **[Deep-links antigos perdem contexto]** → redirect determinístico com `kind=CTE` e preservação somente de query params aceitos pelo catálogo.
- **[Requests do office anterior aparecem após troca]** → invalidar estado pelo `sessionEpoch` e ignorar respostas atrasadas.
- **[Remoção da exceção de middleware bloqueia papéis legítimos]** → cobrir ADMIN, OPERATOR e VIEWER em `/docs/catalog` e confirmar que `/settings` volta a exigir o gate administrativo normal.
- **[Specs ativas ficam contraditórias]** → corrigir o delta CT-e concorrente e validar ambas as changes antes de archive.
- **[Saúde CT-e é duplicada no catálogo]** → manter detalhes operacionais exclusivamente em Sincronizações e apresentar no catálogo apenas estado documental/ação necessária.

## Migration Plan

1. Corrigir o delta OpenSpec concorrente que determina Configurações como localização do onboarding CT-e e validar a taxonomia unificada.
2. Extrair de `settings/cte.vue` os blocos de orientação e pendências para componentes consumidos por `NotesWorkspace` em `/docs/catalog`.
3. Ligar filtros/query e deep-links CT-e ao catálogo, preservando permissões e invalidação por troca de escritório.
4. Atualizar Sincronizações para apontar ações documentais a `/docs/catalog?kind=CTE` e manter ali somente saúde/cursor.
5. Remover itens CT-e de Configurações, command palette e sidebar; retirar a exceção de `/settings/cte` no middleware.
6. Converter `/settings/cte` em redirect compatível sem conteúdo próprio.
7. Migrar testes unitários/E2E, README e inventário de rotas; executar typecheck, testes e build.
8. Validar as changes OpenSpec envolvidas antes do archive.

Rollback: restaurar temporariamente os links para `/docs/catalog?kind=CTE`; não é necessário rollback de dados ou backend. O alias `/settings/cte` permanece seguro durante a transição, e nenhum documento, cursor ou aquisição é alterado pela migração.

## Open Questions

Nenhuma questão bloqueante. O apply pode escolher a apresentação mais compacta do contexto CT-e dentro do arquétipo já usado por `NotesWorkspace`, desde que não crie rota, shell ou navegação paralela e mantenha a tabela de documentos como foco.
