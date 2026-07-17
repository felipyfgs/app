## 1. Contrato visual e filtros

- [x] 1.1 Adaptar `frontend/app/pages/settings/team.vue` a partir do arquétipo `.reference/nuxt-dashboard-template/app/pages/settings/members.vue`, preservando rota, ação primária, vagas e estados existentes; verificar com `cd frontend && pnpm exec eslint app/pages/settings/team.vue`.
- [x] 1.2 Implementar filtro local de papel com opção `Todos` e combiná-lo à pesquisa normalizada por nome/e-mail sem RegExp construída da entrada; verificar os cenários combinados com `cd frontend && pnpm run test -- tests/unit/team-surface.test.ts`.

## 2. Grade de colaboradores

- [x] 2.1 Adaptar `frontend/app/components/settings/TeamList.vue` a partir de `.reference/nuxt-dashboard-template/app/components/settings/MembersList.vue` para uma grade responsiva de cards com nome, e-mail, papel e status; verificar com `cd frontend && pnpm exec eslint app/components/settings/TeamList.vue`.
- [x] 2.2 Preservar seletor de papel, menu de regeneração/desativação/reativação, estados de processamento e labels acessíveis, ocultando mutações quando `canMutate` for falso; verificar com `cd frontend && pnpm run test -- tests/unit/team-surface.test.ts`.
- [x] 2.3 Ajustar loading skeleton, equipe vazia, nenhum resultado, erro e acesso negado para o novo layout, garantindo uma coluna em viewport estreita e ausência de overflow horizontal; verificar o contrato estrutural com `cd frontend && pnpm run test -- tests/unit/team-surface.test.ts`.

## 3. Cobertura e gates

- [x] 3.1 Criar `frontend/tests/unit/team-surface.test.ts` cobrindo arquétipo, cards, pesquisa + papel, estados distintos, dados exibidos e condicionamento das ações; executar `cd frontend && pnpm run test -- tests/unit/team-surface.test.ts`.
- [x] 3.2 Executar o gate oficial completo do frontend com `cd frontend && pnpm run test:gate` e corrigir regressões atribuíveis à change.
- [x] 3.3 Validar a geração SPA de produção com `cd frontend && pnpm run generate` e confirmar que nenhuma dependência, rota de backend ou campo de telefone/avatar foi introduzido.

## 4. Fechamento OpenSpec

- [x] 4.1 Validar a change com `openspec validate aprimorar-tela-equipe --strict` e manter como pendente qualquer item sem evidência real.
- [x] 4.2 Após implementação e verificação, sincronizar a delta spec, arquivar `aprimorar-tela-equipe` e commitar no mesmo dia a main spec e os artefatos em `openspec/changes/archive/`.
