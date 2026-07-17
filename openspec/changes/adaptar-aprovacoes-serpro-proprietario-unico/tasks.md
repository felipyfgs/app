## 1. Política persistente de aprovação

- [ ] 1.1 Criar migration de `approval_policy`, classificar histórico como `DUAL_ROLE` e expirar pendências globais incompatíveis sem reinterpretar aprovação antiga; cobrir up/down em teste.
- [ ] 1.2 Implementar enum/serviço de política com allowlist fechada para `OWNER_CONFIRMATION` e `DUAL_ROLE`, tornando `SerproRolloutApproval::isFullyApproved()` consciente da ação.
- [ ] 1.3 Adicionar testes de matriz provando que credencial/contrato/kill-off aceitam somente proprietário e canário/rollout continuam exigindo política dual.

## 2. Confirmação reforçada das ações globais

- [ ] 2.1 Validar no endpoint ator proprietário, senha recente, frase específica fornecida pelo servidor, motivo, janela vigente e escopo exato; testar cada campo ausente/incorreto sem estado parcial.
- [ ] 2.2 Adaptar `SerproCredentialVersionService` e aprovações de credencial para consumir uma autorização singleton vinculada, removendo o mínimo configurável de dois aprovadores.
- [ ] 2.3 Adaptar ativação/substituição/desbloqueio de contrato para a mesma autorização, preservando vault, validade mínima, OAuth mTLS e rollback da versão anterior.
- [ ] 2.4 Adaptar retirada de kill switch global/de solução para executar com confirmação válida do proprietário e manter ativação do switch imediata/fail-closed.
- [ ] 2.5 Bloquear reuso, expiração, recurso divergente e fabricação por CLI/job; permitir somente consumo de aprovação HTTP persistida e manter `skip_oauth` restrito a local/testing.

## 3. Preservação das aprovações com duas pessoas

- [ ] 3.1 Validar no canário faturável um Proprietário e um `Office ADMIN` ativo do Office delimitado, rejeitando conta dual no segundo papel e qualquer `office_id` injetado pelo cliente.
- [ ] 3.2 Manter `ROLLOUT_PROMOTE` em `DUAL_ROLE` e adicionar regressões que impeçam uma confirmação singleton de satisfazer promoções ou canários.

## 4. API e painel SERPRO

- [ ] 4.1 Atualizar contratos e respostas sanitizadas para expor política/status/frase esperada sem segredo e remover mensagens “aguardando segundo PLATFORM_ADMIN”.
- [ ] 4.2 Implementar no Nuxt a confirmação explícita com motivo e janela usando o arquétipo existente, sem reexibir PFX, OAuth secret, token ou conteúdo do vault.
- [ ] 4.3 Cobrir UI/composable com testes de validação, sucesso, senha expirada e manutenção do fluxo dual do canário.

## 5. Verificação e encerramento

- [ ] 5.1 Executar `cd backend && php artisan test --filter='SerproCredentialVersionLifecycle|SerproContractApi|SerproPlatformSecurity|SerproReadiness'` sem chamadas live e sem falhas.
- [ ] 5.2 Executar `cd backend && vendor/bin/pint --test` e `cd frontend && pnpm run lint && pnpm run typecheck && pnpm run generate && pnpm run test` sem falhas.
- [ ] 5.3 Executar `openspec validate adaptar-aprovacoes-serpro-proprietario-unico --strict` e verificar conjuntamente `tornar-platform-admin-proprietario-unico` com flags/kill switches fail-closed.
- [ ] 5.4 Após o software verificado, sincronizar/arquivar a change e commitar no mesmo dia o main spec e o archive OpenSpec.
