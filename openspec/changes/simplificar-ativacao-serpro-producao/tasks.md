## 1. N0 — Contratos e persistência segura

- [x] 1.1 Criar migration, model, estados e factory do onboarding produtivo com chave idempotente, ator, tenant, consentimento versionado, etapa e metadados sanitizados; cobrir constraints, casts e proibição de material secreto no banco.
- [x] 1.2 Definir Form Request/DTO multipart estrito com Consumer Key, Consumer Secret, PFX, senha e `consent_granted=true`, recusando `office_id`, ambiente e campos técnicos; cobrir validação, tamanho/tipo do arquivo e redaction de erros.
- [x] 1.3 Adicionar feature flag e configuração do onboarding com default OFF, texto/versionamento do consentimento e parâmetros públicos de retomada, sem incluir segredo, endpoint alternativo ou bypass de gates; cobrir defaults fail-closed.

## 2. N1 — Orquestração de backend

- [x] 2.1 Implementar o serviço idempotente de etapas que compõe cadastro PENDING, roundtrip do cofre, validação PFX/CNPJ, teste OAuth mTLS, aprovação HTTP `CREDENTIAL_CUTOVER` e promoção transacional, preservando a versão ativa em falhas; cobrir happy path, retries e compensações.
  Depende de: 1.1, 1.2, 1.3
- [x] 2.2 Implementar o registro versionado da concessão e a integração tenant-scoped com `OfficeSerproAuthorizationService`, distinguindo credencial global, tenant de `CurrentOffice` e pendência de Termo/procuração/poder; cobrir ausência de contexto e isolamento cruzado.
  Depende de: 1.1, 1.2
- [x] 2.3 Implementar o despachante idempotente da sincronização inicial da Caixa Postal, condicionado em tempo de execução a contrato, assinatura, capability, allowlist, orçamento, autoridade oficial e kill switches, sem incluir mutações ou outbound; cobrir zero egress em cada gate bloqueado.
  Depende de: 1.1, 1.3

## 3. N2 — API, autorização e cliente frontend

- [x] 3.1 Expor POST de ativação e GET de estado local no namespace de plataforma, exigindo permissão canônica, `CurrentOffice`, sessão com senha recentemente confirmada e consentimento; criar/consumir a confirmação sensível somente via HTTP autenticado e cobrir RBAC/401/403/422/idempotência.
  Depende de: 2.1, 2.2, 2.3; dependência externa: `padronizar-autorizacao-multitenant` no marco `apply` para `tenant-access-governance` (bloqueante).
- [x] 3.2 Criar resources e códigos de erro sanitizados para estados `ACTIVE_SYNC_PENDING`, `ACTIVE`, `ACTION_REQUIRED` e `FAILED`, com etapa, timestamps, hints mascarados e ações seguras; testar que PFX, senha, Secret, bearer, JWT, XML e payload bruto não aparecem em JSON/log/auditoria.
  Depende de: 2.1, 2.2
- [x] 3.3 Adicionar tipos e métodos em `createPlatformApi` para submit multipart idempotente, consulta de estado e tratamento da reconfirmação padrão, sem aceitar `office_id` ou reter/preencher novamente segredos; cobrir testes unitários do cliente.
  Depende de: 1.2

## 4. N3 — Experiência simplificada e fluxo integrado

- [x] 4.1 Refatorar `frontend/app/pages/admin/serpro/configuration.vue` para tornar o onboarding mínimo o fluxo principal, com Consumer Key, Consumer Secret, PFX, senha, checkbox claro, progresso por etapa e erros acionáveis; manter ferramentas técnicas apenas como diagnóstico autorizado e cobrir acessibilidade/estados visuais.
  Depende de: 3.2, 3.3
- [x] 4.2 Cobrir a jornada HTTP completa com upload válido, consentimento, aprovação, cutover, autorização do tenant e despacho único da Caixa Postal, incluindo retry após timeout e reutilização segura de credencial global equivalente.
  Depende de: 3.1, 3.2
- [x] 4.3 Cobrir falhas integradas de PFX/CNPJ, cofre, OAuth, senha não recente, procuração/poder, kill switch e rollback, provando preservação da versão anterior, zero egress indevido e zero segredo nos artefatos de teste.
  Depende de: 3.1, 3.2

## 5. N4 — Gates e evidências de prontidão

- [ ] 5.1 Executar migration em PostgreSQL/SQLite, suites Laravel focadas e completas, `vendor/bin/pint --test`, `pnpm run test:gate`, `pnpm run generate` e `npx openspec validate simplificar-ativacao-serpro-producao --strict`; registrar evidência sanitizada de canário OAuth sem operação fiscal e de uma Caixa Postal tenant-scoped autorizada, mantendo a feature flag OFF até aprovação do rollout.
  Depende de: 4.1, 4.2, 4.3
