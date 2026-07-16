# 7.6 Classificação dos enums PHP (104)

**Data:** 2026-07-16  
**Regra de design:** estado interno fechado → PHP enum + varchar + CHECK; código oficial evolutivo → raw + mapper UNKNOWN; catálogo configurável → tabela.

| Classe | Critério | Persistência | Exemplos |
|--------|----------|--------------|----------|
| **Estado interno** | Conjunto fechado controlado pela plataforma | `varchar` + CHECK + enum PHP | `SyncCursorStatus`, `FiscalRunStatus`, `CredentialStatus`, `ImportBatchStatus`, `OfficeRole` |
| **Código oficial** | Valor de SEFAZ/SERPRO/ADN que pode evoluir | raw string + enum normalizado opcional | `RegistrationStatus` (fromExternal), códigos cStat, sitfis codes |
| **Catálogo configurável** | Editável / versionado por ops | tabela | SERPRO operations, tax obligations, deadline rules |
| **Motivo/erro** | Diagnóstico interno sanitizado | enum tipado | `FiscalMutationDenialCode`, quarantine reasons |

## Inventário resumido (`backend/app/Enums`, 104 arquivos)

### Estado interno (aplicar CHECK prioritário)
- Sync / captura: `SyncCursorStatus`, `CaptureChannel`, `DocumentDirection`, `DocumentKind`, `DocumentPurpose`, `DocumentArtifactQuality`, `CteCoverageStatus`
- Auth/tenant: `OfficeRole`, `PlatformRole`, `CredentialStatus`, `AuthorCertificateMode`, `AuthorIdentityType`, `OfficeCredentialPurpose`, `OfficeFiscalIdentityStatus`
- Import/export: `ImportBatchStatus`, `ImportBatchItemStatus`
- Monitoramento: `FiscalRunStatus`, `FiscalRunResult`, `FiscalSituation`, `FiscalCoverage`, `FiscalPendingStatus`, `FiscalVerificationState`, `FiscalLinkStatus`, `FiscalMutability`, `FiscalMutationStatus`, `FiscalPaymentStatus`, `FiscalGuideEmissionStatus`, `FiscalGuidePaymentStatus`, `MailboxTriageStatus`, `MailboxMessagesConsultStatus`, `DctfwebTransmissionStatus`, `DctfwebMutationStatus`, `MitEncerramentoStatus`, `FgtsIndependentState`
- Outbound: urgência sem CAPTURED (a alinhar), completeness de recovery case

### Código oficial / externo
- `RegistrationStatus` (mapper external)
- Situações NFS-e/NFe oficiais em projeções (manter raw)
- `EsocialEventCode`, códigos SERPRO oficiais em metadata
- `MailboxDteStatus`, alert severities oficiais quando aplicável

### Catálogo configurável (tabela, não enum puro)
- Chaves de módulo fiscal (`FiscalModuleKey` pode permanecer enum de plataforma fechado)
- Obrigação tributária / deadlines → `tax_obligation_*`, `tax_deadline_*`
- SERPRO → `serpro_operations` + versions + price tiers

### Motivo/erro
- `FiscalMutationDenialCode`, `FiscalFindingSeverity`, `MailboxAlertSeverity`
- Parse/signature results: `SignatureVerificationResult` (fechado) vs mensagens oficiais brutas

## Ações
1. CHECKs para estados internos críticos (cursor, credential, run, completeness outbound) — migrations dedicadas por agregado.
2. Mappers `raw → normalized|UNKNOWN` centralizados em `App\Support\Mappers\*`.
3. Remover leitura de env/HTTP dentro de enums puros (já majoritariamente OK).
4. Padronizar casing: valores de estado internos em `SCREAMING_SNAKE` (já dominante).

## Owner
| Área | Owner |
|------|--------|
| Captura/docs | domínio fiscal documental |
| Monitoramento/guias | hub fiscal |
| SERPRO | plataforma / control plane |
| Auth/tenant | platform security |
