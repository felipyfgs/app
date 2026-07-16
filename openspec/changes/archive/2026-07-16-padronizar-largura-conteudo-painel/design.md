## Context

O shell autenticado já segue `UDashboardPanel`/`UDashboardNavbar` do template Nuxt UI fixado. As páginas de lista e home deixam o `#body` fluido, enquanto `settings.vue` do template usa uma coluna `lg:max-w-2xl`. Essa largura de leitura funciona no demo, mas ficou pequena para os cards e formulários do produto. Adaptações posteriores introduziram `max-w-3xl` e `max-w-4xl`, sem uma regra compartilhada.

A mudança é somente de apresentação no frontend SPA. Não toca backend, sessão, Office, papéis, APIs ou conteúdo sensível.

## Goals / Non-Goals

**Goals:**

- Definir uma primitiva autoimportada do Nuxt para largura e espaçamento de conteúdo autenticado.
- Usar aproximadamente 1024 px como largura confortável para configurações e detalhes textuais, e aproximadamente 1152 px para detalhes densos com grids/aside.
- Manter listas, tabelas, home e workspaces fluidos, conforme os arquétipos `customers.vue`, `index.vue` e `inbox.vue` do template.
- Eliminar limites de largura divergentes nos shells de configuração/detalhe migrados.
- Verificar a regra estruturalmente e pelos gates do frontend.

**Non-Goals:**

- Redesenhar navbar, sidebar, tabs, cards, tabelas ou modais.
- Alterar formulários, validações, permissões, tenancy, APIs ou regras fiscais.
- Aplicar limite global de largura a listas e workspaces densos.
- Alterar telas de autenticação, que possuem um arquétipo próprio e deliberadamente estreito.

## Decisions

### Primitiva `DashboardContent`

Será criado `frontend/app/components/dashboard/DashboardContent.vue`, autoimportado pelo Nuxt como `DashboardContent` (o Nuxt remove o segmento duplicado do nome), com variantes estáticas:

- `comfortable`: `max-w-5xl` para settings, administração e detalhes textuais;
- `wide`: `max-w-6xl` para detalhes densos com grids e painéis auxiliares;
- `full`: sem limite máximo, disponível para exceções explícitas.

A raiz manterá a anatomia estrutural do wrapper de `settings.vue` do template: `mx-auto flex w-full min-w-0 flex-col`. Cada página preservará suas classes responsivas de espaçamento, sem misturá-las à decisão compartilhada de largura.

Alternativa considerada: `UContainer`. Ele centraliza conteúdo, mas adiciona padding horizontal próprio controlado por tema; dentro de `UDashboardPanel#body` isso duplicaria o padding já existente. Uma primitiva fina preserva a árvore do arquétipo e centraliza apenas a decisão que varia no produto.

### Largura por arquétipo

Settings e detalhes usarão a variante `comfortable`; detalhes com grids/aside usarão `wide`. Lista, home, calendário e mestre–detalhe continuarão sem wrapper limitador, pois precisam usar a largura disponível.

Alternativa considerada: um único `max-width` global no `UDashboardPanel`. Foi rejeitada porque comprimiria tabelas e workspaces e descaracterizaria os arquétipos de lista e mestre–detalhe.

### Migração focada

Serão migrados os shells autenticados que hoje possuem um `max-w-2xl`, `max-w-3xl` ou `max-w-4xl` de página, além do detalhe de cliente que já corresponde ao arquétipo settings e está totalmente fluido. Limites locais de inputs, textos, células e modais permanecerão intactos, pois atendem objetivos diferentes.

## Risks / Trade-offs

- [Cards de formulário podem ficar visualmente largos demais] → manter campos com largura própria quando necessário e limitar o container a `max-w-5xl`, sem usar tela inteira.
- [Páginas densas podem perder espaço] → usar `wide` ou permanecer fluidas conforme o arquétipo, sem regra global no painel.
- [Classes Tailwind dinâmicas podem não ser geradas] → mapear variantes para strings literais no componente.
- [Conflito com alterações locais já em andamento] → editar somente wrappers e testes específicos, preservando o restante do conteúdo das páginas.
- [Regressão responsiva] → base sempre `w-full min-w-0`; limites máximos só afetam o teto, não o mobile.
