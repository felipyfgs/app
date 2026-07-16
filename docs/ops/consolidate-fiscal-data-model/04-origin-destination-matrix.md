# 1.4 Matriz origem → destino

Autoridade canônica = destino gravável após o corte. Legado = origem atual ou estrutura de compatibilidade.

## Cadastro e tenancy

| Conceito | Origem (legado / atual) | Destino canônico | Notas de backfill |
|----------|-------------------------|------------------|-------------------|
| Escritório | `offices` | `offices` (inalterado como tenant root) | — |
| Membership | `office_user` | membership ativa + seleção explícita | Invalidar seleção se membership revogada |
| Preferência de office | `users.selected_office_id` + session | mesmo, validado contra membership | Já parcialmente implementado em `CurrentOffice` |
| Cliente (raiz) | `clients` (1 por CNPJ completo de facto) | `clients` canônico `(office_id, root_cnpj)` UNIQUE | Agrupar por office+root; mapa origem→destino |
| Estabelecimento | `establishments` + às vezes 1 client/filial | `establishments` N:1 client, CNPJ 14 imutável | Preservar ids quando possível |
| Matriz | `establishments.is_matrix` + `clients.matrix_client_id` | **só** `is_matrix` + unique parcial | Aposentar `matrix_client_id` após gate |
| Contatos | `client_contacts` | contatos do Cliente raiz | Remap se clients colapsarem |
| Custom fields | `client_custom_fields` | atributos do Cliente raiz (confirmar def vs valor — open Q) | |
| Regime | `clients.tax_regime` + `client_tax_regime_periods` | períodos no Cliente raiz | |
| A1 | `client_credentials` | credencial da raiz; 1 ativa | Detectar conflito se N clients → 1 root |
| Platform admin | `platform_memberships` | plano de controle (sem office fiscal) | Sem leitura fiscal herdada |

## Documentos e capturas

| Conceito | Origem | Destino | Notas |
|----------|--------|---------|-------|
| Documento canônico | `dfe_documents` | mesmo (imutável) | Preservar `sha256`, `vault_object_id`, bytes |
| Aquisição | `document_acquisitions` (vazio local) + campos em interests/runs | `document_acquisitions` por chegada | Backfill “comprovável”; gaps → relatório |
| Interesse | `document_interests` | interesse semântico + junção | Separar NSU/fonte da semântica |
| Projeções | `nfse_notes`, `nfse_events`, `nfe_*`, `cte_*`, `mdfe_*` | projeções 1:1 documento | Parser version |
| Quarentena | `fiscal_document_quarantine` | custódia de divergência hash | Não contar como completo |
| Import | `document_import_batches(_items)` | origem de aquisição tipo upload/pacote | |

## Cursores

| Stream | Origem | Destino | Isolamento |
|--------|--------|---------|------------|
| ADN contribuinte | `sync_cursors` | cursor canônico ADN | office+establishment+env |
| DistDFe NFe/etc. | `channel_sync_cursors` | cursor canônico DistDFe | channel+env |
| Transições | `channel_sync_cursor_transitions` | histórico subordinado | |
| Autor/AutXML | `office_distribution_cursors` (+ runs) | stream autor | **não** misturar com contribuinte |
| Outbound série/número | `outbound_series_cursors`, `outbound_number_states` | sequenciamento outbound | **não** é NSU |

## Outbound / recuperação XML

| Conceito | Origem | Destino |
|----------|--------|---------|
| Pedido/caso | `ma_outbound_retrieval_requests` | caso de recuperação (identidade fiscal, prazo, urgência ≠ capturado) |
| Tentativa | `outbound_xml_recovery_attempts` (+ SVRS) | tentativa por fonte |
| Pacote / run | `outbound_capture_runs`, profiles, readiness | subordinado à tentativa |
| Sucesso | vínculo ad-hoc a documento | **só** via `document_acquisition` validada |
| Mutação | `fiscal_mutation_operations` (+ dctfweb attempts) | `fiscal_mutation_operations` genérico |

## SERPRO

| Conceito | Origem | Destino |
|----------|--------|---------|
| Catálogo A | `serpro_service_catalog_entries` (321) | operação estável `operation_key` + versão oficial |
| Catálogo B | `serpro_operation_catalog` (32) | **fundir** no mesmo canônico (mapa de chaves) |
| Preço | `serpro_price_versions`, `serpro_price_tiers` | regras de cobrança versionadas separadas |
| Contrato global | `serpro_contracts` | plano de controle (sem office_id) |
| Auth escritório | `office_serpro_authorizations` (+ events) | tenant data plane |
| Ledger | `serpro_api_usage_entries/reservations` | append-only idempotente |
| Agregado mensal | `serpro_usage_monthly_aggregates` | **separar** global vs office (proibir híbrido null) |
| Reconciliação | `serpro_usage_reconciliations` (+ adjustments) | append-only |

## Monitoramento e guias

| Conceito | Origem | Destino |
|----------|--------|---------|
| Categoria / obrigação | `fiscal_categories`, `tax_obligation_*` | catálogo + relação cliente–obrigação |
| Competência | `fiscal_competences` | `tax_periods` / identidade de período |
| Run | `fiscal_monitoring_runs` | lifecycle operacional |
| Snapshot | `fiscal_snapshots` | snapshot versionado; ≤1 corrente |
| Findings / pending | `fiscal_findings`, `fiscal_pending_items` | subordinados |
| Guias stub | `fiscal_guide_stubs` | migrar para guia lógica |
| Guia | `tax_guides` + `tax_guide_versions` | guia + versão vigente única |
| Pagamento | `tax_guide_payment_confirmations`, installment payments | estado normalizado + evidência |
| Mailbox / DCTF / FGTS / MIT / parcelamentos | tabelas dedicadas | projeções/módulos sobre run+snapshot |

## Correlação obrigatória

Toda linha migrada DEVE gravar em tabela de mapa (a criar na fase 2):

- `aggregate`, `source_table`, `source_id`, `target_table`, `target_id`, `office_id`, `correlation_id`, `status` (`MAPPED|AMBIGUOUS|REJECTED`), `notes_sanitized`.
