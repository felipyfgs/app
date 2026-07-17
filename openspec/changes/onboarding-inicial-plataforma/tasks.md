## 1. Persistência e domínio

- [x] 1.1 Adicionar configuração fail-closed e migration/model singleton `platform_settings`, com backfill concluído para instalações existentes e trava persistente após exclusão do usuário.
- [x] 1.2 Implementar serviço transacional de disponibilidade/conclusão que valida base vazia, token constante e cria somente User + PlatformMembership global.
- [x] 1.3 Fazer o `default_office_id` nulo do administrador inicial convergir atomicamente para o primeiro Office cadastrado, sem OfficeMembership ou `selected_office_id`, e cobrir o contrato no backend.

## 2. API pública e identidade

- [x] 2.1 Adicionar controller, rotas `GET /api/v1/onboarding/status` e `POST /api/v1/onboarding`, HTTPS produtivo, throttle, sessão pós-commit e respostas `no-store`.
- [x] 2.2 Expor `platform_organization_name` sanitizado em `/me` e garantir redaction de `onboarding_token` sem alterar tenancy.
- [x] 2.3 Cobrir backend com testes de configuração, token, HTTPS, base existente, criação exata, rollback/repetição, sessão, sigilo e trava permanente.

## 3. Onboarding Nuxt

- [x] 3.1 Adicionar tipos/cliente da API e liberar `/onboarding` como rota pública especial no middleware.
- [x] 3.2 Criar página `/onboarding` no arquétipo Auth com quatro campos visíveis, token somente no fragmento/memória e redirect autenticado para `/admin/offices/new`.
- [x] 3.3 Ajustar login, navegação e redirects para `PLATFORM_ADMIN` sem Office, ocultando destinos e quick actions tenant.
- [x] 3.4 Adaptar `/admin` no arquétipo Home com organização somente leitura, empty state para primeiro Office e sem chamada tenant sem contexto.
- [x] 3.5 Cobrir frontend com testes unitários de formulário/fragmento, redirects e navegação sem office (`auth-redirect`, `onboarding-public`). E2E Playwright de viewports fica fora desta change (non-goal / follow-up).
- [x] 3.6 Redirecionar para `/admin` todo redirect pendente ou acesso direto a rota tenant quando o PLATFORM_ADMIN estiver sem contexto, com teste unitário da política.

## 4. Verificação e encerramento

- [x] 4.1 Executar Pint e testes Laravel focados/completos; executar lint, typecheck, Vitest, generate e E2E relevante no frontend.
- [ ] 4.2 OpenSpec strict validado (PASS). Após aceite: sincronizar/arquivar e commitar main spec no mesmo dia.
