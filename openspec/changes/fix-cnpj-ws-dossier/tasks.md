## 1. N0 — Backend QSA e testes do lookup

- [x] 1.1 Deduplicar sócios em `CnpjWsRegistrationLookup::mapShareholders` (chave nome + entered_at + qualification_code; documento sempre mascarado)
- [x] 1.2 Estender `CnpjRegistrationLookupApiTest` para assertar ≥26 `secondary_cnaes` e QSA deduplicado (≤6 nomes únicos) na fixture Globo
  - Evidência: `php artisan test --filter=CnpjRegistrationLookupApiTest`

## 2. N1 — UI do dossiê

- [x] 2.1 Em `ClientRegistration.vue`, exibir seção Atividades (CNAE principal + lista scrollável de `secondary_cnaes`)
  - Depende de: 1.1
- [x] 2.2 Em `ClientRegistration.vue`, exibir IEs (`state_registrations`) com ativas em destaque
  - Depende de: 1.1

## 3. N2 — Gates integrados

- [x] 3.1 Rodar gates API da área: `vendor/bin/pint --test` e `php artisan test --filter=CnpjRegistrationLookupApiTest`
  - Depende de: 1.1, 1.2
  - Evidência: pint PASS 2 files; 6 testes PASS (61 assertions)
- [x] 3.2 Rodar gates Web tocados: `pnpm run lint` e `pnpm run typecheck` em `apps/web`
  - Depende de: 2.1, 2.2
  - Evidência: `eslint app/components/clients/ClientRegistration.vue` PASS; lint/typecheck globais falham em arquivos pré-existentes fora do escopo (`MeiPublicServicesModal.vue`, `simples-mei/index.vue`, etc.)
- [x] 3.3 Validar OpenSpec da change: `npx @fission-ai/openspec@1.6.0 validate fix-cnpj-ws-dossier --type change --strict`
  - Depende de: 1.2, 2.2
  - Evidência: Change 'fix-cnpj-ws-dossier' is valid
