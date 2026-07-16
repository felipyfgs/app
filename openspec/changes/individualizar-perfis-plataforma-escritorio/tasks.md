## 1. Identidade, dados e bootstrap

- [x] 1.1 Adicionar `platform_memberships.default_office_id`, relacionamento e backfill determinístico auditável sem criar OfficeMembership; cobrir migration up/down, registro da decisão, Office padrão inativo e dados legados em testes.
- [x] 1.2 Alterar `app:bootstrap-office` para criar atomicamente a mesma conta como `PLATFORM_ADMIN` e `OfficeRole::ADMIN`, com o primeiro Office como padrão; testar primeira execução, rollback e repetição.
- [x] 1.3 Evoluir `CurrentOffice`, `/api/v1/me` e os tipos frontend para separar `access_mode` de `real_membership`/`real_office_role`; definir `current_office=null` + `context_status` no `/me` e `409 office_context_required` nas rotas tenant, testando conta Office, Plataforma e dual.

## 2. Contexto global e contrato de Offices

- [x] 2.1 Atualizar o serviço de seleção para resolver sessão válida → Office padrão, persistir toda seleção válida como novo padrão, preservar eventual membership real e nunca usar `office_id` tenant-facing; testar seleção, novo login, logout, default inativo, ausência de membership e isolamento.
- [x] 2.2 Padronizar `GET /api/v1/platform/offices` como `{ data: { offices, selected_office_id, default_office_id } }`, remover o fallback legado do composable e adicionar testes de contrato backend/frontend.
- [x] 2.3 Garantir por middleware e testes de rota que `/api/v1/platform/*` e `/admin/*` são globais, enquanto módulos normais continuam tenant-scoped e ignoram `office_id` do cliente.

## 3. Fronteira de suporte no Work

- [x] 3.1 Criar matriz versionada de todas as rotas Work em leitura versus mutação/exportação e adicionar gate que permita leitura global, mas exija OfficeMembership ativa para criar, editar, excluir, executar, reivindicar, atribuir, comentar, anexar evidência e exportar; testar o contexto privilegiado com a feature flag habilitada.
- [x] 3.2 Reforçar policies e serviços Work para usar o papel da membership real, inclusive na conta dual, e cobrir cada família de endpoint com testes `200/403`, ausência de efeito e isolamento entre Offices.

## 4. Autenticação e confirmação sensível

- [x] 4.1 Remover TOTP/2FA dos fluxos Fortify, middleware, rotas e telas de Plataforma e Escritório sem apagar dados legados ainda necessários ao rollback; testar login e navegação de ambos os perfis sem desafio adicional.
- [x] 4.2 Generalizar a reconfirmação de senha para todos os perfis com janela server-side de quinze minutos exclusiva da sessão e invalidação em logout, troca/reset de senha, desativação e sessão inválida; testar relógio, senha incorreta, nova sessão, logout e separação por ator.
- [x] 4.3 Inventariar com `rg` e substituir cada gate TOTP/2FA em HTTP, jobs, CLI, A1/CNPJ, mutações fiscais, credenciais/contrato SERPRO, kill switch, quatro olhos e canário por senha recente; bloquear aprovação humana por CLI/job, reconciliar `AGENTS.md`, `openspec/config.yaml` e runbooks aplicáveis e manter um teste/gate sem referências funcionais legadas.
- [x] 4.4 Migrar aprovações/auditoria futuras para `confirmation_method` e `confirmed_at`, preservar leitura sanitizada do legado e testar integridade, quatro olhos, ausência de senha/hash/segredo e manutenção de assinatura, flags, allowlist, orçamento, idempotência, rate limit e kill switch fail-closed.

## 5. Painel único e Settings conciso

- [x] 5.1 Usando `panel-ui` → `ui-archetype`, derivar a sidebar de `/me`, manter módulos normais para Escritório, adicionar grupo Admin somente à Plataforma e cobrir acesso direto por middleware de rota.
- [x] 5.2 Remover `PrivilegedContextBanner` e renderizar apenas `Plataforma · <Office>` no seletor; testar troca, conta dual, ausência de banner e acessibilidade do selo.
- [x] 5.3 Mover a página Departamentos para `/settings/departments`, retirar `/admin/departments` e preservar escopo/policies tenant-safe com testes de rota e componente.
- [x] 5.4 Limpar `/settings` e modais conforme `ui-configuracao-concisa`: remover o aviso de implantação, compactar consentimento, criar empty state do A1, deduplicar “sem download” e limitar confirmações a duas frases sem `UAlert` aninhado.
- [x] 5.5 Adicionar teste específico de superfície de Settings para textos proibidos, alertas informativos e explicações duplicadas, além de testes unit/e2e dos menus, selo e estados concisos.

## 6. Verificação e encerramento

- [x] 6.1 Executar `cd backend && vendor/bin/pint --test && php artisan test` e, com a stack iniciada, `./docker/ops/verify.sh --full`; obter saída PASS de todos os comandos e corrigir regressões antes de avançar.
- [x] 6.2 Executar em `frontend/` `pnpm run lint`, `pnpm run typecheck`, `pnpm run test`, `pnpm run generate` e `pnpm run test:e2e`, registrando evidência dos fluxos Plataforma, Escritório e dual.
- [x] 6.3 Validar `openspec validate individualizar-perfis-plataforma-escritorio --strict`; após aceite real do software, sincronizar/arquivar sem conflito com `separar-configuracao-escritorio-plataforma-serpro` e commitar os artefatos OpenSpec no mesmo dia.
