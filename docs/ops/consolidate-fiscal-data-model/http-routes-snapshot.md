# Snapshot de contratos HTTP (rotas) — baseline consolidate-fiscal-data-model

**Gerado em:** 2026-07-15
**Escopo:** inventário de rotas; sem payloads, sem segredos.

| Method | URI | Name | Middleware (resumido) |
|---|---|---|---|
| GET|HEAD | `/` |  | web |
| POST | `api/v1/auth/confirm-totp` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/clients` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/clients` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/clients/{client}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| PATCH | `api/v1/clients/{client}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/clients/{client}/contacts` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/clients/{client}/contacts` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| DELETE | `api/v1/clients/{client}/contacts/{contact}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| PATCH | `api/v1/clients/{client}/contacts/{contact}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/clients/{client}/credential` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/clients/{client}/credential` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/clients/{client}/establishments` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/cnpj/{cnpj}/lookup` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/cte/coverage` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/cte/health` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/cte/onboarding` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/cte/pending` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/cte/repairs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/by-client` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/documents/import` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/import-batches` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/documents/import-batches` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/import-batches/{batch}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/import-batches/{batch}/export.csv` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/import-batches/{batch}/items` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/documents/import-batches/{batch}/items/{item}/retry` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/insights` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/{accessKey}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/documents/{accessKey}/manifestations` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/documents/{accessKey}/unlock-xml` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/documents/{accessKey}/xml` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| PATCH | `api/v1/establishments/{establishment}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/exports` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/exports` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/exports/{export}/download` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/categories` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/category-links` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/category-links` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/category-links/batch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/dctfweb/consult` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/dctfweb/declarations` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/dctfweb/declarations/{declaration}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/dctfweb/events` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/dctfweb/transmit` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/declarations` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/declarations/calendar` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/declarations/catalog` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/declarations/project` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/declarations/summary` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/declarations/{projection}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/declarations/{projection}/evidences` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/declarations/{projection}/evidences/{evidence}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/evidence/{evidence}/download` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/fgts/competences` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/fgts/competences/{status}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/fgts/coverage` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/fgts/events` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/fgts/sync` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/fgts/sync-now` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/findings` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/guides` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/guides` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/guides/challenge` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/guides/downloads/{token}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/guides/preflight` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/guides/{guide}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/guides/{guide}/download-token` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/guides/{guide}/payment-confirmations` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/guides/{guide}/reconcile` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/installments/guides` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/installments/modalities` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/installments/orders` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/installments/orders/{order}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/installments/parcels` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/installments/runs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mailbox/alerts` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mailbox/messages` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mailbox/messages/{message}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mailbox/messages/{message}/attachments/{attachment}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mailbox/messages/{message}/body` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| PATCH | `api/v1/fiscal/mailbox/messages/{message}/triage` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mailbox/state` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mit/apuracoes` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mit/apuracoes/{apuracao}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/mit/consult` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/mit/encerrar` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/modules/{module}/clients` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/modules/{module}/overview` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/mutations` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/mutations/preflight` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/mutations/{mutation}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/mutations/{mutation}/reconcile` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/pending-items` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/runs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/runs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/runs/{run}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/simples-mei/catalog` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/simples-mei/clients/{client}/competences` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/simples-mei/clients/{client}/guide-stubs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/simples-mei/clients/{client}/regimes` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/simples-mei/clients/{client}/snapshots` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/simples-mei/consult` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/simples-mei/das` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/simples-mei/transmit` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/sitfis` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/fiscal/sitfis/refresh` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/snapshots` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/fiscal/snapshots/{snapshot}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/integrations/cte/push` |  | api, ThrottleRequests:30,1 |
| GET|HEAD | `api/v1/me` |  | api, Authenticate:sanctum, EnsureActiveUser |
| GET|HEAD | `api/v1/notes` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/notes/by-client` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/notes/insights` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/notes/{accessKey}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/notes/{accessKey}/xml` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/autxml` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/autxml/cursor` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/autxml/enrollments` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/autxml/enrollments/{enrollment}/confirm` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/autxml/enrollments/{enrollment}/inactivate` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/fiscal-identity` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/fiscal-identity` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/fiscal-identity/credential` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/fiscal-identity/credentials/{credential}/revoke` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/integration-tokens` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/integration-tokens` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/integration-tokens/{token}/revoke` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/serpro-authorization` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/serpro-authorization/author` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/serpro-authorization/author-a1` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/serpro-authorization/eligibility` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/serpro-authorization/health` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/serpro-authorization/proxy-powers` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/serpro-authorization/proxy-powers` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/serpro-authorization/proxy-powers/sync` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/serpro-authorization/refresh-token` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/office/serpro-authorization/termo` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/serpro-usage` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/serpro-usage/entries` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/office/subscription` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/operations/inbox` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/operations/quarantine` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/operations/quarantine/{quarantine}/resolve` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/operations/summary` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/deadline/advance-target` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/deadline/capacity` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/deadline/competence` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/deadline/confirm-partial` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/deadline/contingency-batch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/deadline/export` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/deadline/metrics` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/deadline/pending` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/establishments/{establishment}/seed` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/kill-switch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/kill-switch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/profiles` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/profiles/{profile}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/profiles/{profile}/activate` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/profiles/{profile}/csc` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/profiles/{profile}/csc` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/profiles/{profile}/package` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/profiles/{profile}/series` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/runs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/series/{series}/numbers` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/series/{series}/reset` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/series/{series}/trigger-query` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-nfce/breaker` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/svrs-nfce/breaker/reset` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-nfce/kill-switch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/svrs-nfce/kill-switch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-nfce/profiles/{profile}/summary` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-nfce/recoveries` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/svrs-nfce/recoveries` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-nfce/recoveries/{recovery}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-nfce/recoveries/{recovery}/attempts` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/svrs-nfce/recoveries/{recovery}/retry` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-nfce/summary` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/outbound/svrs-portal/egress` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/svrs-portal/egress/elevate-budget` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/svrs-portal/egress/extend-cooldown` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/outbound/svrs-portal/egress/select-canary` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/platform/serpro-usage/consolidation` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro-usage/recompute` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro-usage/reconciliations` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro/breaker/reset` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/platform/serpro/catalog` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/platform/serpro/contracts` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro/contracts` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/platform/serpro/contracts/{serproContract}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro/contracts/{serproContract}/activate` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro/contracts/{serproContract}/block` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro/contracts/{serproContract}/deactivate` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/platform/serpro/health` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/platform/serpro/kill-switch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| POST | `api/v1/platform/serpro/kill-switch` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/platform/tenants` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/platform/tenants/{office}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| PATCH | `api/v1/platform/tenants/{office}/subscription` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsurePlatformAdmin, EnsurePlatformAdminTwoFactor |
| GET|HEAD | `api/v1/sync-runs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/sync-runs` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/tenants/memberships` |  | api, Authenticate:sanctum, EnsureActiveUser |
| POST | `api/v1/tenants/switch` |  | api, Authenticate:sanctum, EnsureActiveUser, ThrottleRequests:30,1 |
| GET|HEAD | `api/v1/work/calendar` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/calendar/day` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/departments` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/departments` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| PATCH | `api/v1/work/departments/{department}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/departments/{department}/assign-membership` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/exports` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/exports/{export}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/exports/{export}/download` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/generation-batches/{batch}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/generation-batches/{batch}/confirm` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/kpis` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/processes` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/processes` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/processes/{process}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| PATCH | `api/v1/work/processes/{process}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/processes/{process}/archive` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/processes/{process}/comments` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/queue` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/bulk` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/tasks/{task}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/assign` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/block` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/claim` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/comments` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/complete` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/dispense` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/evidences` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| DELETE | `api/v1/work/tasks/{task}/evidences/{evidence}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/tasks/{task}/evidences/{evidence}/download` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/reopen` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/resume` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/tasks/{task}/start` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/templates` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/templates` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| GET|HEAD | `api/v1/work/templates/{template}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| PATCH | `api/v1/work/templates/{template}` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `api/v1/work/templates/{template}/preview` |  | api, Authenticate:sanctum, EnsureActiveUser, EnsureOfficeContext, EnsureAdminTwoFactor, EnsureOfficeSubscriptionWritable |
| POST | `forgot-password` | password.email | web, RedirectIfAuthenticated:web |
| POST | `login` | login.store | web, RedirectIfAuthenticated:web, ThrottleRequests:login |
| POST | `logout` | logout | web, Authenticate:web |
| POST | `reset-password` | password.update | web, RedirectIfAuthenticated:web |
| GET|HEAD | `sanctum/csrf-cookie` | sanctum.csrf-cookie | web |
| GET|HEAD | `storage/{path}` | storage.local |  |
| PUT | `storage/{path}` | storage.local.upload |  |
| GET|HEAD | `up` |  |  |
| POST | `user/confirm-password` | password.confirm.store | web, Authenticate:web |
| GET|HEAD | `user/confirmed-password-status` | password.confirmation | web, Authenticate:web |
| PUT | `user/password` | user-password.update | web, Authenticate:web |
| PUT | `user/profile-information` | user-profile-information.update | web, Authenticate:web |
