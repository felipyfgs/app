## Context

O frontend possui 13 superfícies com `UTable`. A maioria replica manualmente classes de `customers.vue`, algumas usam a variante de `HomeSales.vue` e outras não possuem preset algum. Clientes pagina localmente após baixar toda a carteira, Documentos converte cursor em páginas aleatórias, Exportações limita silenciosamente a 50 itens e a visão por empresa agrega o universo do filtro em memória.

A forma visual continua subordinada ao template fixado em `.reference/nuxt-dashboard-template` no commit `0f30c09`. Nuxt UI completa apenas a API dos componentes, e as APIs Laravel permanecem responsáveis por paginação e tenancy.

## Goals / Non-Goals

**Goals:**

- Tornar todas as tabelas reconhecíveis como derivação literal de `customers.vue` ou `HomeSales.vue`.
- Eliminar cópias divergentes do objeto `ui` e padronizar estados e rodapés.
- Manter volume de dados limitado no navegador por paginação/cursor server-side.
- Preservar identidade, estado e ação principal em 360 px.
- Cobrir cada superfície tabular com teste funcional ou visual apropriado.

**Non-Goals:**

- Criar componente genérico que esconda toda a API de `UTable`.
- Alterar regras fiscais, captura, armazenamento de XML ou segurança de certificados.
- Introduzir dependência visual além de Nuxt UI e do template fixado.
- Substituir APIs cursor-based por offset quando o cursor é a garantia de consistência.

## Decisions

### 1. Presets visuais pequenos e explícitos

Será criado `frontend/app/utils/table-ui.ts` com três objetos imutáveis:

- `DASHBOARD_TABLE_UI`: cópia literal da tabela de `customers.vue`.
- `DENSE_DASHBOARD_TABLE_UI`: mesma estrutura com `px-3 py-2`, usada em Clientes e Documentos.
- `COMPACT_DASHBOARD_TABLE_UI`: cópia de `HomeSales.vue`, usada apenas em tabelas compactas do dashboard.

Cada `UTable` continuará declarando colunas e slots localmente. Um wrapper universal foi rejeitado porque esconderia slots, seleção TanStack e variações responsivas do template.

### 2. Estado vazio dentro da própria tabela ou substituindo-a

Tabelas administrativas usarão um único estado vazio. Quando a identidade visual pedir `UEmpty`, a tabela será ocultada sem dados ou o slot `#empty` conterá esse estado; não haverá linha vazia padrão mais um segundo `UEmpty`.

### 3. Clientes será realmente server-side com estado local

A página manterá `page`, `per_page`, `q`, `status`, filtro operacional e ordenação em estado local, seguindo `customers.vue`. Esses valores serão enviados como query somente à API Laravel; a URL do navegador permanecerá `/clients`. A API devolverá uma página e metadados; o frontend não percorrerá todas as páginas. KPIs continuarão vindo de agregação independente do recorte da página.

### 4. Cursores não serão convertidos em páginas aleatórias

Documentos, Saúde e Sincronizações manterão o padrão “carregar mais”. Documentos acumulará resultados usando `next_cursor`; mudança de filtro reiniciará linhas e cursor. `UPagination` será usado somente onde a API oferece total/página estáveis.

### 5. Documentos preserva a largura do catálogo

O detalhe abrirá em modal responsivo tanto no desktop quanto no mobile. O painel lateral do arquétipo Inbox foi rejeitado após validação visual porque comprimia a tabela e criava uma grande área vazia sem documento selecionado. “Por cliente” usará `/docs`, “Catálogo” usará `/docs/catalog` e o detalhe usará `/docs/:accessKey`; filtros, cursor e seleção permanecerão no estado local do workspace.

### 6. APIs limitadas terão paginação explícita

Exportações e agregação por empresa aceitarão `page`/`per_page` e retornarão metadados. Consultas continuarão sob o escopo de `office_id`; nenhuma informação de outro escritório será usada para totais ou linhas.

### 7. Tradução sem apagar diagnóstico

Badges e botões usarão labels pt-BR. Códigos técnicos permanecerão em `title`, descrição ou detalhe acessível quando úteis para suporte.

## Risks / Trade-offs

- [Links antigos podem conter estado tabular na query] → ignorar o estado efêmero legado e carregar os defaults canônicos da página.
- [Cursor acumulado aumenta memória] → limitar tamanho da página e oferecer reinício por filtro; não permitir salto arbitrário.
- [Paginação por empresa exige consulta agregada mais complexa] → agregar no PostgreSQL antes de paginar e testar isolamento por escritório.
- [Baselines visuais podem mudar em lote] → atualizar somente após comparação por zona e usar fixtures sintéticas determinísticas.
- [Arquivos já possuem alterações locais] → preservar mudanças existentes e limitar patches aos blocos tabulares.

## Migration Plan

1. Introduzir presets e migrar tabelas sem alterar dados.
2. Uniformizar loading/vazio/erro/footer e labels.
3. Migrar Clientes, Exportações e Por empresa para paginação server-side.
4. Migrar Documentos para cursor incremental e modal de detalhe responsivo.
5. Atualizar testes de estado, responsividade e snapshots.
6. Liberar após unitários, typecheck, lint do escopo e build passarem.

Rollback: reverter por superfície, mantendo os endpoints paginados compatíveis com chamadas sem parâmetros.

## Open Questions

Nenhuma questão bloqueante. A implementação seguirá os limites atuais das APIs e o template fixado.
