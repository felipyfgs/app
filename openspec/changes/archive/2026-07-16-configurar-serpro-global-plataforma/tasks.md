## 1. Domínio e persistência global

- [x] 1.1 Criar migrations/modelos para evidência OAuth versionada e limites quantitativos por ambiente/Office, com constraints Trial/Production, ciclo 1–28 e limites positivos; validar migrate/rollback e testes de banco.
- [x] 1.2 Implementar `SerproPlatformConfigurationService` com endpoints oficiais fixos, resumo sanitizado, seis gates, controles, limites e Offices pendentes; testar isolamento Trial/Production e ausência de segredo.
- [x] 1.3 Ajustar `SerproCredentialVersionService` ao ciclo PENDING/VERIFIED/ACTIVE/RETIRED/COMPROMISED, final da key e evidência recente ligada à versão/fingerprint; testar vault, transições e redaction.
- [x] 1.4 Implementar teste explícito OAuth mTLS sem rota fiscal, com HTTP fake em CI e invalidar evidência em rotação/comprometimento; executar `php artisan test --filter=SerproCredentialConnectionTest`.
- [x] 1.5 Implementar limites quantitativos e alerta 80% sobre ledger local, garantindo null/zero/100% fail-closed e concorrência atômica; executar testes do gate de uso.
- [x] 1.6 Fazer o kill switch efetivo combinar banco com `SERPRO_KILL_SWITCH` por bloqueio monotônico, provando que env false nunca promove e true sempre prevalece.

## 2. APIs e autorização

- [x] 2.1 Implementar `GET /api/v1/platform/serpro/configuration` e `POST /credential-versions` sob Sanctum/usuário ativo/Proprietário, sem Office context e com resource sanitizado.
- [x] 2.2 Implementar `POST .../{version}/verify`, `/test-connection` e `/cutover`, exigindo senha recente e confirmação reforçada no cutover; testar teste expirado/cross-version/cross-environment.
- [x] 2.3 Implementar `PATCH /external-gates/{gate}` com referência, resumo, responsável e data obrigatórios, sem waiver/PDF; testar os seis gates e Production bloqueada.
- [x] 2.4 Implementar `PUT /usage-limits` com ciclo, alerta, limite global e por Office, sem confiar Office enviado para escopo fiscal; testar autorização e validações quantitativas.
- [x] 2.5 Remover POST/activate/deactivate/block legados de contratos, manter leitura histórica sanitizada e cobrir que rotas antigas não alteram estado/vault.
- [x] 2.6 Cobrir APIs com testes de Proprietário único, senha recente, auditoria, redaction e arquitetura que proíbe transporte fiscal no controller global.

## 3. Painel global

- [x] 3.1 Copiar o arquétipo Settings fixado e criar `/admin/serpro/configuration` com `DashboardContent comfortable`, seletor Trial/Production e resumo de readiness.
- [x] 3.2 Implementar formulários de upload/rotação/teste/cutover, gates, limites e switches sem preencher novamente segredo; incluir histórico e Offices pendentes com link para `/settings`.
- [x] 3.3 Substituir a ativação direta da aba Contratos por navegação à Configuração e testar estados loading/empty/error, senha expirada e ausência de material secreto.
- [x] 3.4 Executar frontend `pnpm run lint`, `typecheck`, `test`, `generate` e E2E da Configuração/rotação/links, mantendo SPA estática.

## 4. Linguagem, decisão e aceite

- [x] 4.1 Criar/atualizar `CONTEXT.md` com Contratante SERPRO, Proprietário, Office A1, Termo, gate, versão, ambiente e canário; registrar ADR de controle no banco com bloqueio emergencial externo.
- [x] 4.2 Executar backend Pint/PHPUnit focal e suíte SERPRO, scanner de segredos e `openspec validate configurar-serpro-global-plataforma --type change --strict`.
- [ ] 4.3 Live ops-gated: cadastrar material real separado, testar OAuth Production e aceitar referências externas; manter desmarcado até evidência real e sem chamada fiscal.
- [ ] 4.4 Após aceite de software e ops, sincronizar specs, arquivar e commitar change/main spec/ADR no mesmo dia.

