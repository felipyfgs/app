## 1. N0 — Contrato oficial e limites documentais

- [x] 1.1 Confrontar `CCMEI/EMITIRCCMEI121/1.0` com a página oficial específica, registrar coordenada, schema de entrada/saída, bilhetagem e formato documental em fixture sanitizada; interromper a implementação se a fonte divergir do snapshot.
  Evidência: página oficial consultada em 18/07/2026 confirmou rota `Emitir`, entrada vazia e `dados` JSON escapado com `cnpj` e `pdf` Base64; fixture sintética `backend/tests/fixtures/serpro/ccmei/121.json` não contém dado fiscal real.
- [x] 1.2 Mapear os pontos de extensão CCMEI existentes e definir codec, DTO, chave idempotente, projeção e descritor de download sem modificar consultas 122/123.
  Evidência: revisão do padrão PNR para cofre/AAD/idempotência, PGDAS-D para validação de PDF e `CcmeiMonitoringController` para tenancy/RBAC; a nova projeção documental será separada de `CcmeiCertificateProjection` (consulta 122).

## 2. N1 — Backend seguro tenant-scoped

- [x] 2.1 Implementar codec/DTO fail-closed e testes unitários para sucesso, vazio, erro, layout inválido, proveniência sintética e documento fora dos limites permitidos.
  Evidência: `CcmeiCertificateIssuanceCodecTest` e teste de projetor cobrem envelope JSON escapado, vazio/layout inválido, não-PDF, limite, fonte sintética e ausência de persistência.
  Depende de: 1.1, 1.2
- [x] 2.2 Criar migração forward-only, modelos e projetor idempotente que guardem bytes apenas no `SecureObjectStore` e persistam metadados sanitizados tenant-scoped.
  Evidência: migration `2026_07_18_110000_create_ccmei_issued_certificates_table.php`, modelo ocultando referências do cofre e projetor com AAD por office/cliente/hash; idempotência e isolamento cobertos em teste Feature.
  Depende de: 1.1, 1.2
- [x] 2.3 Implementar serviço de emissão manual por `CurrentOffice`, com flags/kill switch, autorização, rate limit, orçamento, timeout sem retry cego e correlação sanitizada.
  Evidência: `CcmeiCertificateIssuanceService` usa exclusivamente `SerproOperationExecutor`, chave idempotente e correlação aleatória sanitizada; o executor aplica capability, kill switch, orçamento e circuit breaker, e a rota possui throttle 10/minuto.
  Depende de: 2.1, 2.2
- [x] 2.4 Expor GET de histórico local, POST confirmado de emissão e download same-origin autorizado; recusar `office_id`, referência estrangeira e parâmetros técnicos SERPRO.
  Evidência: rotas CCMEI tenant-scoped adicionadas ao `api.php`; GET não chama SERPRO, POST exige `confirmed`, e download usa lookup office+cliente antes de abrir o cofre com cabeçalhos privados/no-store.
  Depende de: 2.2, 2.3
- [x] 2.5 Cobrir tenancy, papéis, ausência de egress em GET, cofre, idempotência, concorrência, sanitização e download em testes Feature/Unit Laravel.
  Evidência: 14 testes Laravel / 70 assertions em `CcmeiMonitoringControllerTest`, `CcmeiCertificateIssuanceCodecTest` e `CcmeiCertificateIssuanceProjectorTest`; o projetor usa `lockForUpdate` e índice único por office/cliente/hash.
  Depende de: 2.1, 2.2, 2.3, 2.4

## 3. N2 — Painel de negócio CCMEI

- [x] 3.1 Adicionar contratos de frontend e ações API sem transmitir `office_id`, coordenadas ou conteúdo documental bruto.
  Evidência: tipos sanitizados, `useCcmeiCertificateIssuance` e `createFiscalApi` usam somente id opaco de cliente/certificado e o POST envia apenas `confirmed: true`.
  Depende de: 2.4
- [x] 3.2 Implementar no detalhe do cliente o painel de emissão e histórico seguindo `panel-ui` e `ui-archetype`, com loading, vazio, sucesso, erro, bloqueio, sem permissão e confirmação bilhetável.
  Evidência: `ClientCcmeiCertificateIssuancePanel` integrado em `/monitoring/clients/:clientId/ccmei` reutiliza `UPageCard` do arquétipo, mostra estados explícitos e só faz POST após a confirmação no diálogo dedicado.
  Depende de: 3.1
- [x] 3.3 Cobrir o painel com testes Nuxt: nenhuma chamada na montagem/GET, confirmação explícita, estados, ausência de dados sensíveis e download autorizado.
  Evidência: `ccmei-certificate-issuance-panel.nuxt.test.ts` e `api-modules.test.ts` aprovados; a montagem lê só o histórico local, a emissão exige clique e não há `office_id` ou identificadores fiscais no contrato.
  Depende de: 3.1, 3.2

## 4. N3 — Evidências e validação integrada

- [x] 4.1 Atualizar a linha `ccmei.emitirccmei` do ledger com contratos, maturidade local, testes e blocker de Trial/canário, sem classificá-la como pronta para produção.
  Evidência: `docs/ops/integra-contador-matriz-cobertura.md` registra backend/UI como `ok` e preserva `BLOCKED` até Trial autorizado e `PASS_REAL_*` + `PRODUCTION_CANARY`.
  Depende de: 2.5, 3.3
- [x] 4.2 Executar Pint, testes Laravel focados, lint, typecheck, Vitest, generate, fidelity, scan de artefatos, OpenSpec estrito e varredura de segredos; registrar limitações reais.
  Evidência: `composer validate --strict`, Pint, suíte Laravel, `pnpm run test:gate`, `pnpm run generate`, fidelity e scan de artefatos aprovados em 18/07/2026; `openspec validate emitir-certificado-ccmei --strict` aprovou. Limitação preservada: não houve Trial nem canário de produção.
  Depende de: 4.1
