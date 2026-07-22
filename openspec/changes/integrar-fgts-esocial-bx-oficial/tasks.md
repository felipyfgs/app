## 1. N0 — Base fail-closed e auditoria

- [x] 1.1 Evoluir `config/fgts_esocial.php` e `.env.example` com driver `disabled|official_bx`, Produção Restrita default, egress produtivo OFF, endpoints allowlisted, limites/timezone e metadados oficiais; cobrir defaults e combinações inválidas em teste unitário.
- [x] 1.2 Criar migration/model do ledger eSocial BX tenant-scoped, sem payload/segredo/CNPJ completo, com índices para empregador+ambiente+dia e casts/hidden adequados; validar schema e serialização negativa em teste Feature.
- [x] 1.3 Criar DTOs/exceções internos para readiness, identificadores, arquivos baixados e falhas oficiais, com códigos estáveis e classificação retryable/permanent/blocked; cobrir invariantes e sanitização em teste unitário.

## 2. N1 — Protocolo oficial isolado e testado

- [x] 2.1 Implementar signer XMLDSig e request factory SOAP 1.1 para `ConsultarIdentificadoresEventosEmpregador` e `SolicitarDownloadEventosPorId`, com namespaces/actions v1.0.0, escaping DOM e lote máximo de 50; validar assinatura, estrutura e ausência de segredo em fixtures unitárias.
  Depende de: 1.1, 1.3
- [x] 2.2 Implementar parser seguro de SOAP/fault, códigos 201/203/301–417, identificadores e arquivos S-1299/S-5013, validando tipo/competência e extraindo metadados sanitizados; cobrir sucesso, 406 vazio, parcial, fault, XML malformado e evento divergente em teste unitário.
  Depende de: 1.3
- [x] 2.3 Implementar transporte cURL SOAP 1.1/mTLS com PFX BLOB em memória, TLS/hostname obrigatórios, allowlist de host e erros sanitizados; cobrir headers/opções e falhas HTTP/TLS com transporte injetável em teste unitário.
  Depende de: 1.1, 1.3
- [x] 2.4 Implementar guard/ledger com janela dos dias 1–7, quota conservadora de 10 chamadas/dia e lock distribuído por empregador+ambiente durante todo o fetch; testar bloqueio pré-egress, reserva atômica, concorrência, quota e atualização de resultado.
  Depende de: 1.1, 1.2, 1.3

## 3. N2 — Provider e readiness operacionais

- [x] 3.1 Implementar resolvedor do `ClientCredential` ACTIVE e serviço de readiness tenant-scoped sem materializar segredo quando bloqueado, incluindo driver/egress/feature/kill switch/janela/quota/credencial; cobrir outro office, ausência/expiração e produção bloqueada em teste Feature.
  Depende de: 1.1, 1.2, 1.3, 2.4
- [x] 3.2 Implementar `HttpEsocialBxEventClient` com fluxo S-1299/S-5013 consulta→download, deduplicação, parcialidade, liberação de material em `finally` e diagnósticos sanitizados; usar fakes de protocolo para testar vazio, sucesso, parcial, códigos oficiais e que S-5003 não é buscado sem CPF.
  Depende de: 2.1, 2.2, 2.3, 2.4, 3.1
- [x] 3.3 Atualizar binding do `EsocialEventClient` e manifesto de cobertura para distinguir eventos aceitos/automáticos, fonte, ambiente, limites e links oficiais, mantendo `DisabledEsocialEventClient` como default; cobrir resolução de container para drivers válidos/inválidos.
  Depende de: 1.1, 3.2

## 4. N3 — Fluxo tenant, scheduler e painel

- [x] 4.1 Adicionar endpoint `readiness`, aplicar o mesmo preflight em `sync`/`sync-now` e integrar códigos HTTP estáveis, isolamento de office e ausência de enqueue/ledger em bloqueios; cobrir rotas, papéis, feature flags, credenciais e payloads sem segredo em testes Feature.
  Depende de: 3.1, 3.2, 3.3
- [x] 4.2 Ajustar monitoring service, source adapter e job Horizon para preservar códigos blocked/retryable, persistir XML oficial idempotente e manter fechamento/totalização independentes de guia/pagamento; cobrir persistência, repetição, scheduler bloqueado e projeção em testes Unit/Feature.
  Depende de: 3.1, 3.2, 3.3
- [x] 4.3 Atualizar `createFiscalApi`, tipos e `/monitoring/fgts` para coverage/readiness/fonte oficial/limites e copy parcial honesta, preservando o shell do arquétipo; cobrir contratos, alertas e ação de sync em Vitest.
  Depende de: 3.3

## 5. N4 — Gates integrados e evidências de prontidão

- [x] 5.1 Rodar gates API (`composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, `php artisan test`) e corrigir regressões preservando mudanças locais não relacionadas.
  Depende de: 4.1, 4.2
- [x] 5.2 Rodar gates web (`pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity`, `pnpm run test:artifacts`) e corrigir regressões de UI/contrato.
  Depende de: 4.3
- [x] 5.3 Validar change/specs OpenSpec estritamente e validar Compose dev/prod, comprovando ausência de `mei`/`mei-worker`, segredos e novos serviços; registrar a configuração de rollout fail-closed na entrega.
  Depende de: 4.1, 4.2, 4.3
