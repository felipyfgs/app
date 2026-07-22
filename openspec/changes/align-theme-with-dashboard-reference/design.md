## Context

O frontend já usa o shell do Nuxt UI Dashboard Template, mas `app.config.ts` e `main.css` substituem a paleta verde da referência por tokens laranja próprios. O `UserMenu.vue` preserva a estrutura do arquétipo, porém remove explicitamente o submenu de seleção de cores. A mudança é restrita a `apps/web` e não toca contexto de escritório, autenticação, APIs ou infraestrutura.

## Goals / Non-Goals

**Goals:**

- Tornar `green`/`zinc` o estado inicial do tema, como no arquétipo local.
- Portar a seleção de cor primária e neutra do `UserMenu.vue` de referência, com copy em pt-BR e sem perder os grupos específicos do produto.
- Aplicar a seleção imediatamente pelos aliases reativos de `useAppConfig()`.
- Fixar o contrato em teste unitário e validar os gates do frontend e OpenSpec.

**Non-Goals:**

- Redesenhar o shell, páginas ou componentes de domínio.
- Criar persistência no backend ou preferência por escritório/usuário.
- Alterar autenticação Sanctum, jobs, integrações SERPRO/SEFAZ/MEI, flags ou Compose.
- Copiar itens demonstrativos do template, como links para outros templates e perfil fictício.

## Decisions

### Reusar o mecanismo reativo do arquétipo

O seletor obterá `appConfig` com `useAppConfig()` e atualizará `appConfig.ui.colors.primary` ou `appConfig.ui.colors.neutral` nos handlers `onSelect`. Isso reproduz o comportamento suportado pelo template e mantém todos os componentes acoplados apenas aos nomes semânticos `primary` e `neutral`. A alternativa de escrever variáveis CSS diretamente foi descartada porque duplicaria o mecanismo de tema do Nuxt UI.

### Restaurar apenas os tokens canônicos da referência

`app.config.ts` voltará a declarar `primary: 'green'` e `neutral: 'zinc'`; `main.css` manterá a fonte `Public Sans` e a escala verde do template. Overrides globais de tamanho e de `UAlert`, bem como escalas Inter customizadas, serão removidos para que os defaults do arquétipo voltem a prevalecer. Cores de feedback continuarão usando os defaults semânticos do Nuxt UI. Preservar parte da paleta laranja foi descartado porque manteria dois temas-base concorrentes.

### Integrar o seletor ao menu existente

O submenu `Tema` será inserido no grupo que já contém `Aparência`, antes do seletor claro/escuro. As opções e chips visuais seguirão o componente de referência, enquanto conta, PWA e logout permanecerão inalterados. O estado selecionado será indicado por itens `checkbox` e a seleção não fechará o submenu.

### Testar contrato observável e fidelidade

Um teste unitário lerá a configuração/CSS e o componente para garantir o default verde/zinc, as paletas disponibilizadas e as mutações dos aliases semânticos. Os gates `lint`, `typecheck`, `generate`, `test`, `test:fidelity` e `test:artifacts` cobrirão integração e regressões do shell.

## Mapa de dependências

```text
N0: contrato do tema + teste unitário
 ├─ N1: tokens/configuração canônicos
 └─ N1: seletor no UserMenu
       └─ N2: gates integrados frontend/OpenSpec
```

- Ownership exclusivo desta change: `app.config.ts`, `main.css`, `UserMenu.vue`, novo teste e seus artefatos OpenSpec.
- Não há upstream ativo nem marco bloqueante; a change é `C0`.
- Changes fiscais podem seguir em paralelo desde que não editem os arquivos acima.
- Rollout é atômico no build estático do frontend; rollback consiste em reverter estes arquivos e regenerar o bundle.

## Risks / Trade-offs

- [A seleção do template é reativa apenas no runtime atual] → manter o mesmo contrato da referência e não prometer persistência entre recargas; persistência poderá ser uma capability separada.
- [Algumas combinações de cor podem ter contraste visual diferente] → limitar opções às paletas oficiais do Tailwind/Nuxt UI e manter os aliases semânticos.
- [Remover overrides globais pode alterar densidade de botões e campos] → validar `generate`, testes de fidelidade e artefatos; essa remoção é intencional para aderência ao tema-base do arquétipo.
- [Edição concorrente no `UserMenu.vue`] → verificar o diff antes da aplicação e preservar grupos específicos do produto.

## Migration Plan

1. Introduzir o teste de contrato do tema.
2. Restaurar configuração e escala verde da referência.
3. Portar o seletor para o menu adaptado do produto.
4. Executar os gates frontend e validar a delta OpenSpec.
5. Em caso de regressão, reverter os três arquivos de tema/menu e o teste no mesmo deploy.

## Open Questions

- Nenhuma para esta change; persistência entre recargas permanece explicitamente fora do escopo.
