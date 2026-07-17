## 1. Invariável de dados e domínio

- [x] 1.1 Criar migration com preflight de duplicidade e índice único parcial de `PLATFORM_ADMIN`, cobrindo PostgreSQL/SQLite com teste de schema e rollback.
- [x] 1.2 Centralizar a obtenção/criação do Proprietário em serviço transacional que converta colisão concorrente em `platform_owner_already_exists`, com testes unitários/feature.
- [x] 1.3 Adaptar onboarding inicial, `app:bootstrap-office` e `PlatformAdminDemoSeeder` para recusarem qualquer vínculo global prévio e provar idempotência com seus testes dedicados.
- [x] 1.4 Impedir exclusão, cascade ou desativação comum que remova o único proprietário, cobrindo o bloqueio e a preservação de `OfficeMembership` em teste.

## 2. Consolidação e recuperação operacional

- [x] 2.1 Implementar `app:platform-owner:consolidate --keep=<user-id>` com confirmação interativa, lock, revogação de sessões e auditoria sanitizada; testar base válida, duplicada e cancelamento.
- [x] 2.2 Implementar `app:platform-owner:recover` para correção ou transferência atômica, usando prompt oculto para senha e sem aceitar segredo em argv/stdout/log; testar revogação e unicidade final.
- [x] 2.3 Adicionar testes de concorrência e falha intermediária garantindo zero estado parcial e no máximo uma `PlatformMembership` global.

## 3. API singular da plataforma

- [x] 3.1 Criar `GET/PATCH /api/v1/platform/owner` sob Sanctum, `EnsurePlatformAdmin` e senha recente nas alterações sensíveis, retornando somente identidade e Office padrão sanitizados.
- [x] 3.2 Remover as rotas plurais de criação/detalhe/regeneração, `CreatePendingPlatformAdminService` e os ramos globais pendentes incompatíveis sem afetar ativação de usuários de Office.
- [x] 3.3 Atualizar testes de API para contrato singular, rejeição do cliente legado, isolamento por Office e ausência de acesso fiscal implícito.

## 4. Painel do proprietário

- [x] 4.1 Atualizar tipos e `createPlatformApi.ts` para o recurso `owner`, removendo operações plurais e cobrindo o composable em teste unitário.
- [x] 4.2 Substituir `/admin/admins` e “Administradores” por `/admin/owner` e “Proprietário”, usando `panel-ui`/`ui-archetype` sem tabela nem botão de novo administrador.
- [x] 4.3 Validar navegação, edição singular, responsividade e acessibilidade com Vitest/Playwright, incluindo ausência da UI de criação global.

## 5. Verificação e encerramento

- [x] 5.1 Executar `cd backend && php artisan test --filter='InitialOnboarding|PlatformAdmin|PlatformOwner|DemoSeeder'` e `vendor/bin/pint --test` sem falhas.
- [x] 5.2 Executar `cd frontend && pnpm run lint && pnpm run typecheck && pnpm run generate && pnpm run test` sem falhas.
- [x] 5.3 Executar `openspec validate tornar-platform-admin-proprietario-unico --strict` e a suíte coordenada de `adaptar-aprovacoes-serpro-proprietario-unico`, mantendo flags e canais fiscais desligados.
- [x] 5.4 Após o software verificado, sincronizar/arquivar a change e commitar no mesmo dia os main specs e o archive OpenSpec.
