# 1.5 Matriz de funcionalidades e contratos

Cada linha deve ter, no gate final (fase 9–10): evidência de teste **APROVADO** ou **exceção formal**.

## Auth, sessão e tenancy

| Funcionalidade | Superfície | Autoridade de dados | Contrato / evidência |
|----------------|------------|---------------------|----------------------|
| Login SPA + Sanctum cookie | Fortify / web | `users`, sessions | CSRF + cookie |
| TOTP / 2FA | Fortify columns + confirm-totp | `users` | `POST api/v1/auth/confirm-totp` |
| Memberships | office_user | tenancy | middleware `EnsureOfficeContext` |
| Troca de escritório | `api/v1/tenants` + selected_office | CurrentOffice | invalidar stores no frontend |
| Revogação membership | admin membership | office_user.is_active | limpar session/selected |
| PLATFORM_ADMIN | `platform_memberships` | plano controle | **sem** leitura fiscal herdada |
| Papéis ADMIN/OPERATOR/VIEWER | OfficeRole | policies | |

## Cadastro e credenciais

| Funcionalidade | Superfície | Autoridade | Notas |
|----------------|------------|------------|-------|
| CRUD clientes | `api/v1/clients` | clients | criar atômico client+matrix |
| Estabelecimentos | nested / establishments | establishments | multi-estab por raiz (alvo) |
| Contatos / custom fields | clients API | contacts, custom_fields | |
| A1 upload / status | credentials | client_credentials + vault | **nunca** recuperar PFX |
| Elegibilidade captura | services | credential + establishment | |
| CNPJ lookup | `api/v1/cnpj` | externo sanitizado | |

## Capturas e catálogo documental

| Funcionalidade | Superfície | Autoridade |
|----------------|------------|------------|
| Sync ADN | jobs + sync-runs | sync_cursors, dfe_documents |
| Sync DistDFe | jobs Sefaz | channel_sync_cursors |
| AutXML office | jobs office dist | office_distribution_* |
| Import XML batch | import API/jobs | import batches → acquisitions |
| Catálogo unificado | `api/v1/documents` | dfe + interests + projections |
| Download / export | documents, exports | vault bytes via id; zip job |
| Notas operacionais | `api/v1/notes` | nfse_notes projection |
| CTE views | `api/v1/cte` | cte_* |

## Outbound

| Funcionalidade | Superfície | Autoridade |
|----------------|------------|------------|
| Recuperação MA outbound | `api/v1/outbound/*` | ma_outbound_*, recovery_attempts |
| SVRS NFC-e | jobs + outbound | svrs_*, recovery |
| Deadline scheduling | jobs PlanOutbound* | deadline schema |
| Capacidade / readiness | outbound snapshots | capacity, monthly_readiness |
| Sequence capture | QueryOutboundSequenceJob | number/series state |

## Monitoramento fiscal (hub)

| Funcionalidade | Superfície | Autoridade |
|----------------|------------|------------|
| Dashboard fiscal KPIs | `api/v1/fiscal/*` | runs, snapshots, findings |
| Simples/MEI | fiscal modules | projections + runs |
| DCTFWeb/MIT | fiscal | dctfweb_*, mit_* |
| Parcelamentos | fiscal | tax_installment_* |
| Situação fiscal | fiscal | snapshots sitfis |
| Caixa postal | fiscal/mailbox | mailbox_* |
| Declarações | fiscal | tax_obligation_projections |
| Guias | fiscal/guides | tax_guides, versions, payments |
| FGTS/eSocial (parcial) | fiscal | esocial_*, fgts_* |
| Mutações + TOTP | FiscalMutationController | fiscal_mutation_* |

## SERPRO / plataforma

| Funcionalidade | Superfície | Autoridade |
|----------------|------------|------------|
| Contrato global | platform API | serpro_contracts (controle) |
| Catálogo / preços | platform | dual catalogs → canônico |
| Ledger / consumo tenant | office + platform | usage entries (tenant vê só o seu) |
| Termo / autor / procurações | office SERPRO | authorizations, tax_proxy_powers |
| Assinatura office | subscriptions | office_subscriptions |
| Admin platform | `api/v1/platform/*` | platform only |

## Ops e shell

| Funcionalidade | Superfície | Autoridade |
|----------------|------------|------------|
| Ops health / backup status | ops API | instance_backup_runs |
| Settings tenant | frontend settings | office, SERPRO health sanitizado |
| Operations dashboard | operations API | health aggregates |
| Horizon | /horizon | jobs (ops) |

## Inventário de rotas

- Total de rotas (sem Horizon detalhado no snapshot): ver `http-routes-snapshot.md` (**254** linhas de app).
- Grupos principais: `api/v1/fiscal` (73), `outbound` (37), `office` (25), `platform` (17), `documents` (14), `clients` (11).

## Jobs a versionar/drenar no corte (amostra)

`SyncEstablishmentDistributionJob`, `SyncSefazDistDfeJob`, `SyncSefazCteDistDfeJob`, `SyncOfficeAutXmlDistDfeJob`, `SyncOfficeCteAutXmlDistDfeJob`, `ProcessDocumentImportBatchJob`, `RecoverSvrsNfceXmlJob`, `QueryOutboundSequenceJob`, `PlanOutboundDeadlineScheduleJob`, `BuildExportZipJob`, `AutoCienciaNfeJob`, jobs em `App\Jobs\Fiscal\*`.
