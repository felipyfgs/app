## Why

O painel divergiu do tema canônico do arquétipo local ao fixar uma identidade laranja e remover o seletor de paletas disponível no menu do usuário. O alinhamento devolve a aparência e a personalização previstas pelo Nuxt UI Dashboard Template sem redesenhar o shell.

## What Changes

- Restaurar o tema-base do arquétipo, com `primary: green`, `neutral: zinc`, fonte e escala verde canônicas.
- Incorporar ao menu do usuário o seletor de cor primária e de paleta neutra existente em `.local/reference/nuxt-dashboard-template`.
- Manter as opções de aparência claro/escuro e os itens de conta, instalação e logout já adaptados ao produto.
- Adicionar teste de contrato que impeça nova divergência entre o painel e a referência.
- Non-goals: redesenhar o shell ou páginas, alterar fluxos fiscais/SERPRO, ativar flags/canais, adicionar serviços Compose ou introduzir mutações de backend.

## Capabilities

### New Capabilities

- `dashboard-theme-selector`: tema inicial e seleção dinâmica das paletas primária e neutra pelo menu do usuário.

### Modified Capabilities

- Nenhuma.

## Impact

- Código afetado: `apps/web/app/app.config.ts`, `apps/web/app/assets/css/main.css`, `apps/web/app/components/UserMenu.vue` e testes unitários do frontend.
- APIs e dados: nenhuma alteração de API, persistência de domínio ou dependência externa.
- Compatibilidade: componentes continuam consumindo cores semânticas do Nuxt UI; a seleção muda apenas os aliases `primary` e `neutral` em runtime.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `.local/reference/nuxt-dashboard-template` e as main specs atuais.
- Depende de: nenhuma change ativa; capability/contrato próprio `dashboard-theme-selector`; marco exigido: nenhum; relação: coordenada.
- Desbloqueia: nenhuma change ativa conhecida.
- Paralelismo: pode avançar em paralelo com changes fiscais e de backend; deve evitar edições concorrentes em `UserMenu.vue`, `app.config.ts` e `main.css`.
