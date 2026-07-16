## 1. Estruturas de dados e migração aditiva

- [x] 1.1 Criar migrations/models para perfil institucional, credencial A1 canônica, vínculos de finalidade, consentimento versionado e estado de onboarding; fazer backfill seguro de `Office`/`OfficeFiscalIdentity` e testar upgrade/rollback em SQLite e Postgres compatível.
- [x] 1.2 Criar estruturas separadas para seleção de office em modo privilegiado e auditoria interna append-only, sem membership fictícia, cobrindo constraints e retenção de metadados sanitizados em testes de modelo.
- [x] 1.3 Criar estruturas para procurações sincronizadas, entitlements 5/7/10, limites 100/150/200, override negociado de clientes, ledger comercial, consulta inaugural e política mensal por office+monitor; preservar o ledger/orçamento técnico e testar unicidades/idempotência.

## 2. Configuração e credencial do escritório

- [x] 2.1 Implementar domínio e APIs tenant-scoped do perfil com os quatro campos, autorização `Office ADMIN`, descarte de `office_id` do request e troca confirmada de CNPJ com invalidação atômica; validar com testes feature/policy.
- [x] 2.2 Generalizar `OfficeCredentialService` para a credencial canônica e-CNPJ A1 no vault, validação exata de titularidade/senha/validade e vínculos `SERPRO_TERM_SIGNING` e `NFE_AUTXML_DISTDFE`, sem serializar segredo; validar com testes de serviço e arquitetura.
- [x] 2.3 Implementar consentir/revogar, replace validate-before-cutover, remoção confirmada, eventos de reonboarding e alertas de painel em 30/7/1 dias; testar falha preservando A1 anterior, bloqueio imediato e ausência de e-mail/download.

## 3. Automação SERPRO e procurações

- [x] 3.1 Implementar state machine e jobs Horizon idempotentes para gerar/assinar Termo, executar `Apoiar`, armazenar/renovar token/ETag e reagir a perfil/consentimento/A1, usando locks e mocks do transporte em todos os testes.
- [x] 3.2 Alterar o gateway para derivar autor e autorização do `CurrentOffice`, recusar parâmetros técnicos tenant-facing e separar diagnóstico global de pendência acionável; cobrir OAuth/mTLS sanitizado, segredos e proibição de import global em controllers tenant.
- [x] 3.3 Implementar sincronização oficial de procurações e os quatro estados, coluna/projeção por cliente e gate por metadado da `operation_key`, provando em testes que operação sem poder aplicável continua e que não existe importação/override manual.

## 4. Contexto privilegiado da plataforma

- [x] 4.1 Implementar seletor global e resolução `CurrentOffice(access_mode=platform_privileged)` para todo `PLATFORM_ADMIN`, mantendo ator real, sessão separada e rejeição de escopo por body/query; testar troca, logout e ausência de membership.
- [x] 4.2 Adaptar policies, scopes e auditoria para capacidade efetiva de `Office ADMIN` no contexto privilegiado, inclusive leitura fiscal e configuração, garantindo isolamento entre offices e invisibilidade da trilha interna ao tenant em testes de arquitetura/feature.
- [x] 4.3 Remover o middleware TOTP global da navegação de plataforma e exigir reconfirmação recente de senha para replace/remove do A1 e mutações fiscais privilegiadas, mantendo flags, allowlist, assinatura, orçamento, idempotência e kill switch fail-closed; testar todos os caminhos de negação.

## 5. Franquia comercial e agendamento mensal

- [x] 5.1 Implementar resolução de período pela assinatura, entitlements dos três planos, limite negociado somente por plataforma e bloqueio de cadastro acima do máximo, sem crédito/rollover/override de consultas; validar renovação fora do mês-calendário.
- [x] 5.2 Implementar ledger comercial separado e correlacionado ao técnico, consulta inaugural única `quota_units=0`, consumo compartilhado manual/scheduled e débito somente no primeiro despacho remoto; testar retry, polling, bloqueio pré-transporte e concorrência.
- [x] 5.3 Substituir/evoluir o scheduler intervalar para política office+monitor de dia 1–28 com default hash estável, um item por cliente+monitor+período, spillover Horizon, alerta de snapshot recente e intervalos oficiais server-side; testar saldo esgotado, reexecução e que NFS-e/SEFAZ/autXML não consomem franquia.

## 6. Painel Nuxt

- [x] 6.1 Usando `panel-ui` → `ui-archetype`, reconstruir `/settings` como página única de perfil, consentimento, A1 e agendas, reutilizando o padrão visual de certificado sem download e removendo campos técnicos SERPRO; cobrir estados loading/vazio/erro e testes de componentes.
- [x] 6.2 Reservar `/admin/*` à plataforma, adicionar seletor global/contexto visível e atualizar listas/detalhes com Procuração, saldo, último snapshot, próxima execução, confirmação de refresh recente e erros acionáveis; validar acessibilidade, isolamento e rotas com testes unit/e2e.

## 7. Verificação, rollout e encerramento

- [x] 7.1 Executar `backend/vendor/bin/pint --test`, suites focadas e `php artisan test`, incluindo migrations, policies, arquitetura, vault, filas e ausência de chamadas SERPRO reais; registrar/fixar qualquer regressão antes de avançar.
- [x] 7.2 Executar no `frontend/` `pnpm run lint`, `pnpm run typecheck`, `pnpm run test`, `pnpm run generate` e e2e pertinente; executar também `./docker/ops/verify.sh --full` quando a stack estiver disponível.
- [x] 7.3 Documentar flags default OFF, métricas, reconciliação de A1 divergente, rollback e gate externo de aprovação jurídica/segurança; reconciliar requisitos conflitantes das main specs (archive go-live / `openspec/specs/serpro-*`), validar esta change com `openspec validate separar-configuracao-escritorio-plataforma-serpro --strict` e, após aceite real do software, sincronizar/arquivar e commitar os artefatos OpenSpec no mesmo dia. (rollout-notes.md; validate --strict OK; **não** archive — aguarda 7.1/7.2 + aceite)
