## Why

As tabelas do painel foram implementadas em momentos diferentes e hoje divergem em densidade, bordas, estados vazios, paginação, responsividade e hierarquia de ações. A inconsistência também mascara problemas funcionais, como paginação local sobre APIs server-side e navegação por páginas simulada sobre cursores.

## What Changes

- Derivar todas as tabelas administrativas dos blocos fixados de `customers.vue` e `HomeSales.vue` do template Nuxt UI Dashboard.
- Centralizar os presets visuais canônicos de `UTable`, com variantes administrativa, densa e compacta rastreáveis ao template.
- Uniformizar loading, vazio, erro, ações de linha, rodapé, contagem e controles de paginação.
- Manter filtros, ordenação, paginação e overlays como estado local de UI; reservar paths para visões navegáveis e não serializar estado efêmero em query parameters do navegador.
- Corrigir Clientes para paginação, busca, filtros e ordenação server-side, mantendo o estado efêmero da tabela local à página e a URL canônica limpa, como em `customers.vue`.
- Corrigir Documentos para navegação por cursor sem simular offset e abrir o detalhe em modal responsivo, sem comprimir a tabela.
- Paginar Exportações e a visão de Documentos por empresa, preservando `office_id` derivado da sessão.
- Padronizar Fechamento, Saúde, Sincronizações, Importações, onboarding `autXML` e tabelas contextuais.
- Traduzir estados e ações técnicas para pt-BR sem perder o valor técnico em detalhes acessíveis.
- Ampliar testes de estados, responsividade e regressão visual para todas as superfícies tabulares.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `dashboard-template-fidelity`: tornar explícita a origem compartilhada dos presets tabulares e a cobertura visual de todas as tabelas autenticadas.
- `frontend-dashboard-experience`: estender consistência, estados e paginação server-side a todas as superfícies tabulares e proibir simulação de páginas aleatórias sobre cursor.

## Impact

- Frontend Nuxt: páginas e componentes com `UTable`, utilitários visuais, modal de detalhe, tipos e composables de API.
- Backend Laravel: contratos paginados de Clientes, Exportações e agregação de Documentos por empresa.
- Testes: unitários, typecheck, lint, estados de lista, responsividade em 360 px e snapshots em 390/1440 px.
- Sem nova dependência de runtime e sem alteração de tenancy, autenticação ou tratamento de segredos.

## Não-objetivos

- Criar um design system paralelo ao Nuxt UI e ao template fixado.
- Alterar regras fiscais, cursores NSU, captura documental ou governança SVRS.
- Adicionar SSR/Node em produção, mocks em runtime ou seletor livre de escritório.
- Expor XML bruto, PFX, senha, PEM, chave privada ou identificadores internos do cofre.
