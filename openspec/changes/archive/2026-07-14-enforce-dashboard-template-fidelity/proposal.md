## Why

O frontend já usa Nuxt UI e alguns componentes do template oficial, porém a adaptação anterior aceitou divergências visuais e interacionais amplas demais. O resultado preserva funções do domínio, mas não mantém de forma verificável a composição, a densidade, a hierarquia, os estados e o comportamento responsivo do template fixado em `.reference/nuxt-dashboard-template`.

Esta mudança estabelece fidelidade como requisito testável, e não como preferência subjetiva, antes da conclusão do painel e do aceite operacional do sistema.

## What Changes

- Recriar cada rota do painel a partir de cópia direta do código do arquétipo correspondente em `.reference/nuxt-dashboard-template`, preservando o modelo visual e interacional do template.
- Permitir alterações somente em textos, traduções, rotas, ícones semanticamente necessários, tipos, dados reais, chamadas de API, permissões e proteções obrigatórias do domínio.
- Manter idênticos estrutura de componentes, slots, classes utilitárias, espaçamento, densidade, tipografia, cores, bordas, dimensões, hierarquia de ações, estados, responsividade, foco e navegação por teclado.
- Inventariar cada arquivo copiado e registrar, linha conceitual por linha conceitual, toda adaptação necessária para o produto.
- Capturar baselines visuais reproduzíveis do template e da aplicação em desktop e mobile, com comparação por rota e tolerâncias documentadas.
- Proibir reinterpretações, simplificações visuais, wrappers alternativos ou componentes apenas “equivalentes”; divergências não exigidas pelo domínio passam a ser defeitos.
- Registrar explicitamente toda linha estrutural ou classe que não puder permanecer igual por exigência técnica incontornável.
- Reestruturar shell, dashboard, Clientes, detalhe de Cliente, Notas, Exportações, Sincronizações e Administração para usar a mesma gramática visual e interacional do template.
- Preservar dados reais, APIs Laravel/Sanctum, permissões, paginação server-side e proteções de material sensível; não reintroduzir mocks do template.
- Ampliar testes de componentes e Playwright autenticado para comprovar fidelidade em `1440×900`, `390×844` e ausência de overflow em `360 px`.
- Produzir evidências visuais e uma checklist de aceite que permitam revisar a fidelidade sem depender de julgamento informal.

## Não-objetivos

- Manter conteúdo demonstrativo, marcas, usuários, clientes fictícios, mocks `server/api` ou regras comerciais do template após usar o código como base.
- Alterar contratos da API, banco de dados, regras fiscais, sincronização ADN, cofre, autenticação, tenancy ou perfis de acesso.
- Simular dados ou ações sem suporte funcional real; quando o template possuir um controle sem equivalente funcional, manter seu espaço e modelo visual com adaptação funcional segura ou documentar a única exceção necessária.
- Permitir troca arbitrária de escritório para imitar o seletor de equipes do template.
- Expor XML fiscal, PFX, senha, chave privada, PEM ou respostas ADN não sanitizadas.
- Adotar SSR ou manter processo Node em produção.

## Capabilities

### New Capabilities

- `dashboard-template-fidelity`: define a paridade verificável entre o painel NFS-e e o template oficial de referência, incluindo divergências permitidas, responsividade, acessibilidade e evidências visuais.

### Modified Capabilities

Nenhuma. As main specs ainda não foram publicadas; esta mudança adiciona um contrato independente e complementar aos delta specs dos changes em andamento.

## Impact

- Frontend em `frontend/app/`, especialmente layout autenticado, componentes globais e todas as páginas do painel.
- Testes em `frontend/tests/`, configuração Vitest/Playwright e documentação de execução visual.
- Referência local somente leitura em `.reference/nuxt-dashboard-template`, fixada como baseline e não como dependência de runtime.
- Artefatos de evidência visual gerados fora do bundle de produção e sem dados fiscais ou segredos reais.
- Sem mudança esperada em endpoints, migrations, imagens PHP, Nginx ou modelo de implantação SPA same-origin.
