## Context

A gestão de equipe já está implementada em `frontend/app/pages/settings/team.vue`, exposta canonicamente por `/conta/equipe`, e usa `SettingsTeamList` para apresentar uma lista linear. A API tenant-scoped em `OfficeMemberController` já lista e altera memberships de `/api/v1/office/members`, com `CurrentOffice`, Sanctum, senha recente e autorização por OfficeMembership `ADMIN` real.

A referência visual desejada organiza colaboradores em cards, com pesquisa e filtro por cargo. O template fixado não possui exatamente essa grade: a forma-base continuará sendo `app/pages/settings/members.vue` e `app/components/settings/MembersList.vue`, adaptando somente a área de resultados para cards responsivos dentro do shell atual. A aplicação permanece Nuxt 4 SPA (`ssr: false`) e não haverá backend ou dependência nova.

## Goals / Non-Goals

**Goals:**

- Tornar a equipe mais escaneável em telas largas e utilizável em telas estreitas.
- Combinar pesquisa por nome/e-mail e filtro local por papel.
- Manter as ações e os estados já existentes, com labels em pt-BR e componentes Nuxt UI.
- Preservar a rota `/conta/equipe`, o shell de Conta, a API e todas as garantias de tenancy e autorização.
- Cobrir a superfície com testes unitários e os gates oficiais do frontend.

**Non-Goals:**

- Persistir telefone ou avatar e alterar o modelo `OfficeMember`.
- Criar aba de departamentos ou histórico de ações dentro desta tela.
- Modificar endpoints, middleware, regras de senha recente, limite de vagas ou ativação.
- Redesenhar sidebar, navbar ou tabs do shell autenticado.
- Executar chamadas SERPRO, bilhetagem, live smoke ou habilitar flags/canais fiscais.

## Decisions

### 1. Evoluir a rota existente, sem criar página paralela

`/conta/equipe` continuará renderizando a implementação de `pages/settings/team.vue`, preservando compatibilidade com os redirecionamentos e a navegação já presentes. Criar outra rota produziria duas superfícies para a mesma capability e risco de divergência.

Alternativa considerada: criar uma página exclusiva inspirada integralmente na referência externa. Rejeitada porque duplicaria lógica e romperia o shell canônico do produto.

### 2. Preservar o arquétipo settings e adaptar apenas a coleção

A página manterá a anatomia copiada de `.reference/nuxt-dashboard-template/app/pages/settings/members.vue` e o componente partirá de `components/settings/MembersList.vue`. O cabeçalho, a ação principal, a busca e os estados continuam reconhecíveis; a lista será substituída por uma grade responsiva de cards Nuxt UI/HTML sem teto arbitrário adicional dentro do `DashboardContent` comfortable já definido pela Conta.

Alternativa considerada: copiar a página `customers.vue`. Rejeitada porque o domínio não pede tabela, paginação ou densidade de lista administrativa.

### 3. Filtrar no cliente sobre a coleção já carregada

Um estado de papel com opção `Todos` será combinado ao texto normalizado no `computed` existente. A lista atual já é retornada como coleção completa, sem paginação; portanto a filtragem local não altera contrato, tenancy ou carga de backend. O texto será comparado sem construir expressão regular a partir da entrada do usuário.

Alternativa considerada: adicionar query parameters à API. Rejeitada por não haver volume/paginação que justifique ampliar o contrato nesta change.

### 4. Card apresenta apenas dados existentes e ações autorizadas

Cada card mostrará avatar derivado, nome, e-mail, papel e status. Telefone não será simulado, pois não existe no `OfficeMember`. O seletor de papel e o menu continuarão condicionados por `canManageOfficeTeam` e pelo estado do membro. O backend permanece a autoridade final e retorna `403` quando o contexto não possui membership ADMIN real.

### 5. Estados e acessibilidade fazem parte da superfície

Loading skeleton, equipe vazia, nenhum resultado, erro recuperável e acesso negado continuarão distintos. Busca e filtro terão labels acessíveis; menus manterão `aria-label`; a ordem visual acompanhará a ordem da API. Em viewport estreita, controles empilham e a grade usa uma coluna, sem rolagem horizontal da página.

## Risks / Trade-offs

- [Cards ocupam mais altura que uma lista] → Usar grade responsiva com múltiplas colunas em viewports largas e conteúdo conciso.
- [Filtro local pode deixar de escalar se a API ganhar paginação] → Manter a decisão explícita e migrar filtros para parâmetros server-side numa change futura se houver paginação.
- [Ações podem parecer disponíveis fora do contexto correto] → Condicionar UI a `canManageOfficeTeam` e preservar o `403` fail-closed do backend.
- [Vazamento entre Offices por estado anterior após troca] → Preservar `sessionEpoch`, limpar coleção/segredo e recarregar pelo endpoint sem `office_id` do cliente.
- [Material de ativação pode permanecer visível] → Preservar `ActivationOneTimeSecret`, `Cache-Control: no-store` da API e limpeza do segredo na troca de sessão/unmount.
- [Mudança visual divergir do template] → Registrar os arquivos-base do arquétipo e limitar a adaptação à coleção em cards e aos filtros solicitados.
- [Kill switches, bilhetagem ou chamadas externas acidentais] → A change não toca integrações fiscais, jobs, flags ou serviços externos; validação ocorre inteiramente em CI.

## Migration Plan

1. Implementar a evolução visual atrás da mesma rota e contrato de API.
2. Executar testes unitários direcionados e `pnpm run test:gate` no frontend.
3. Gerar a SPA com `pnpm run generate` antes da entrega.
4. Em caso de regressão visual, reverter somente os componentes/página da equipe; não há migration de dados nem rollback de backend.
5. Após verificação, sincronizar a delta spec, arquivar a change e commitar os artefatos OpenSpec no mesmo dia.

## Open Questions

Nenhuma para iniciar a implementação. Telefone, avatar persistido, departamentos e histórico permanecem candidatos a changes futuras separadas.
