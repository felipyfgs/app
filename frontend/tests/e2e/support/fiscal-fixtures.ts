/**
 * Fixtures fiscais determinísticas e sanitizadas para E2E/visual e testes de contrato.
 *
 * - data_origin DEMO no cenário ready (banner demo)
 * - CNPJ mascarado (nunca completo em carteira)
 * - Sem PFX, PEM, vault ids, tokens SERPRO, XML real, Consumer Secret
 * - Discriminados por module_key (overview + client row)
 */
import type {
  FiscalModuleClientDetail,
  FiscalModuleClientRow,
  FiscalModuleCounters,
  FiscalModuleOverview,
  FiscalPortfolioModuleKey
} from '../../../app/types/fiscal-modules'
import {
  FISCAL_MODULE_LABELS,
  FISCAL_PORTFOLIO_MODULE_KEYS,
  fiscalDataOriginLabel,
  isFiscalPortfolioModule,
  isSyntheticFiscalOrigin
} from '../../../app/types/fiscal-modules'
import type {
  FiscalCategory,
  FiscalFinding,
  FiscalMonitoringRun,
  FiscalPendingItem,
  FiscalSnapshot,
  FgtsCoverageManifest
} from '../../../app/types/api'

export const FISCAL_FIXTURE_NOW = '2026-07-14T15:00:00.000Z'
export const FISCAL_FIXTURE_COMPETENCE = '2026-07'
export const FISCAL_FIXTURE_OFFICE_ID = 1
export const FISCAL_FIXTURE_CLIENT_ID = 1
export const FISCAL_FIXTURE_CLIENT_ID_B = 2

/** CNPJ sintético alfanumérico (nunca expor completo nas linhas de carteira). */
export const FISCAL_FIXTURE_CNPJ_RAW = '12ABC345678900'
export const FISCAL_FIXTURE_ROOT_RAW = '12ABC345'
export const FISCAL_FIXTURE_CNPJ_B_RAW = '98XYZ765432100'
export const FISCAL_FIXTURE_ROOT_B_RAW = '98XYZ765'

/** Espelha maskCnpj do ModulePortfolioQueryService (sem material sensível). */
export function maskCnpjFixture(cnpj: string): string {
  const clean = String(cnpj || '').toUpperCase().replace(/[^0-9A-Z]/g, '')
  if (clean.length < 8) return '****'
  return clean.slice(0, 4) + '*'.repeat(Math.max(0, clean.length - 8)) + clean.slice(-4)
}

export function maskRootCnpjFixture(root: string): string {
  const clean = String(root || '').toUpperCase().replace(/[^0-9A-Z]/g, '')
  if (clean.length < 4) return '****'
  return clean.slice(0, 4) + '*'.repeat(Math.max(0, clean.length - 4))
}

export const FISCAL_FIXTURE_CNPJ_MASKED = maskCnpjFixture(FISCAL_FIXTURE_CNPJ_RAW)
export const FISCAL_FIXTURE_ROOT_MASKED = maskRootCnpjFixture(FISCAL_FIXTURE_ROOT_RAW)
export const FISCAL_FIXTURE_CNPJ_B_MASKED = maskCnpjFixture(FISCAL_FIXTURE_CNPJ_B_RAW)
export const FISCAL_FIXTURE_ROOT_B_MASKED = maskRootCnpjFixture(FISCAL_FIXTURE_ROOT_B_RAW)

export const FISCAL_FIXTURE_LEGAL_NAME = 'Cliente Demonstração Segura'
export const FISCAL_FIXTURE_LEGAL_NAME_B = 'Segundo Contribuinte Demo ME'
/** Tenant sentinela (office_id=2) — isolamento multi-escritório nas fixtures E2E. */
export const FISCAL_FIXTURE_SENTINEL_LEGAL_NAME = 'Cliente Tenant Sentinela'
export const FISCAL_FIXTURE_OFFICE_B_ID = 2

export interface FiscalTenantContext {
  officeId?: number
}

export function fiscalLegalNameForOffice(officeId = 1): string {
  return officeId === FISCAL_FIXTURE_OFFICE_B_ID
    ? FISCAL_FIXTURE_SENTINEL_LEGAL_NAME
    : FISCAL_FIXTURE_LEGAL_NAME
}

/** Chaves proibidas em qualquer payload fiscal público da fixture. */
export const FISCAL_FORBIDDEN_KEYS = [
  'pfx',
  'pem',
  'private_key',
  'privateKey',
  'certificate_password',
  'cert_password',
  'vault_id',
  'vault_object_id',
  'vault_path',
  'secure_object_id',
  'consumer_secret',
  'consumer_key',
  'consumerSecret',
  'access_token',
  'refresh_token',
  'serpro_token',
  'termo_xml',
  'signed_termo',
  'raw_xml',
  'xml_content',
  'body_content',
  'password',
  'passphrase',
  'VAULT_MASTER_KEY'
] as const

/** Padrões de conteúdo proibidos (PEM/XML real etc.). Montados em runtime para não acionar a varredura de fontes. */
export const FISCAL_FORBIDDEN_CONTENT: readonly RegExp[] = [
  new RegExp(`-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY${String.fromCharCode(45).repeat(5)}`),
  new RegExp(`-----BEGIN CERTIFICATE${String.fromCharCode(45).repeat(5)}`),
  /<\?xml[\s\S]{0,80}<(InfNFSe|NFe|nfeProc)/i,
  /Consumer\s*Secret/i,
  new RegExp(['VAULT', 'MASTER', 'KEY'].join('_'))
]

export type FiscalListScenario = 'ready' | 'empty' | 'error' | 'slow'

function pageEnvelope<T>(items: T[], perPage = 15) {
  return {
    data: items,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: perPage,
      total: items.length
    },
    current_page: 1,
    last_page: 1,
    per_page: perPage,
    total: items.length
  }
}

export function fiscalCounters(overrides: Partial<FiscalModuleCounters> = {}): FiscalModuleCounters {
  return {
    up_to_date: 1,
    processing: 0,
    pending: 1,
    attention: 1,
    error: 0,
    ...overrides
  }
}

export function buildModuleDetail(module: FiscalPortfolioModuleKey): FiscalModuleClientDetail {
  switch (module) {
    case 'simples_mei':
      return {
        module_key: 'simples_mei',
        submodule: 'PGDASD',
        period_key: FISCAL_FIXTURE_COMPETENCE,
        competence_id: 901,
        links: {
          regimes: `/api/v1/fiscal/simples-mei/clients/${FISCAL_FIXTURE_CLIENT_ID}/regimes`,
          competences: `/api/v1/fiscal/simples-mei/clients/${FISCAL_FIXTURE_CLIENT_ID}/competences`,
          snapshots: `/api/v1/fiscal/simples-mei/clients/${FISCAL_FIXTURE_CLIENT_ID}/snapshots`,
          guide_stubs: `/api/v1/fiscal/simples-mei/clients/${FISCAL_FIXTURE_CLIENT_ID}/guide-stubs`
        }
      }
    case 'dctfweb':
      return {
        module_key: 'dctfweb',
        submodule: 'DCTFWEB',
        dctfweb: {
          id: 911,
          period_key: FISCAL_FIXTURE_COMPETENCE,
          transmission_status: 'TRANSMITTED',
          payment_status: 'PENDING',
          receipt_number: 'DEMO-RX-001',
          situation: 'PENDING'
        },
        mit: {
          id: 912,
          period_key: FISCAL_FIXTURE_COMPETENCE,
          encerramento_status: 'OPEN',
          dctfweb_transmission_status: 'PENDING',
          situation: 'ATTENTION'
        },
        links: {
          declarations: `/api/v1/fiscal/dctfweb/declarations?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          mit: `/api/v1/fiscal/mit/apuracoes?client_id=${FISCAL_FIXTURE_CLIENT_ID}`
        }
      }
    case 'installments':
      return {
        module_key: 'installments',
        order_id: 921,
        modality: 'REL_PERT',
        external_order_id: 'DEMO-ORD-001',
        total_amount_cents: 150000,
        parcel_count: 12,
        order_situation: 'ACTIVE',
        next_parcel_id: 922,
        next_parcel_due_at: '2026-08-15T00:00:00.000Z',
        next_parcel_amount_cents: 12500,
        overdue_parcels: 1,
        links: {
          orders: `/api/v1/fiscal/installments/orders?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          parcels: `/api/v1/fiscal/installments/parcels?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          order: '/api/v1/fiscal/installments/orders/921'
        }
      }
    case 'sitfis':
      return {
        module_key: 'sitfis',
        snapshot_id: 931,
        observed_at: FISCAL_FIXTURE_NOW,
        age_seconds: 3600,
        ttl_seconds: 86400,
        is_expired: false,
        findings_count: 1,
        pending_count: 1,
        links: {
          sitfis: `/api/v1/fiscal/sitfis?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          findings: `/api/v1/fiscal/findings?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          pending_items: `/api/v1/fiscal/pending-items?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          snapshot: '/api/v1/fiscal/snapshots/931'
        }
      }
    case 'mailbox':
      return {
        module_key: 'mailbox',
        official_unread_count: 1,
        stored_message_count: 2,
        open_triage_count: 1,
        dte_status: 'ACTIVE',
        latest_message_id: 9001,
        latest_subject_preview: 'Intimação demonstrativa (fixture)',
        latest_received_at: FISCAL_FIXTURE_NOW,
        latest_due_at: '2026-07-30T00:00:00.000Z',
        links: {
          messages: `/api/v1/fiscal/mailbox/messages?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          state: `/api/v1/fiscal/mailbox/state?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          message: '/api/v1/fiscal/mailbox/messages/9001'
        }
      }
    case 'declarations':
      return {
        module_key: 'declarations',
        open_count: 2,
        next_projection_id: 951,
        next_obligation_code: 'DEFIS',
        next_period_key: '2025',
        next_due_at: '2026-03-31T00:00:00.000Z',
        next_delivery_status: 'PENDING',
        next_situation: 'PENDING',
        links: {
          declarations: `/api/v1/fiscal/declarations?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          summary: `/api/v1/fiscal/declarations/summary?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          projection: '/api/v1/fiscal/declarations/951'
        }
      }
    case 'guides':
      return {
        module_key: 'guides',
        guides_count: 2,
        open_count: 1,
        unpaid_amount_cents: 34567,
        next_guide_id: 961,
        next_due_at: '2026-07-20T00:00:00.000Z',
        next_amount_cents: 34567,
        next_payment_status: 'ISSUED',
        links: {
          guides: `/api/v1/fiscal/guides?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          guide: '/api/v1/fiscal/guides/961'
        }
      }
    case 'fgts':
      return {
        module_key: 'fgts',
        competence_period_key: FISCAL_FIXTURE_COMPETENCE,
        closure_status: 'CLOSED',
        totalization_status: 'RECEIVED',
        guide_status: 'UNSUPPORTED',
        payment_status: 'UNSUPPORTED',
        coverage: 'PARTIAL',
        last_synced_at: FISCAL_FIXTURE_NOW,
        partial_coverage_notice:
          'Guia e pagamento FGTS Digital permanecem UNSUPPORTED sem API pública M2M.',
        links: {
          coverage: '/api/v1/fiscal/fgts/coverage',
          competences: `/api/v1/fiscal/fgts/competences?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          events: `/api/v1/fiscal/fgts/events?client_id=${FISCAL_FIXTURE_CLIENT_ID}`,
          status: '/api/v1/fiscal/fgts/competences/971'
        }
      }
  }
}

export function buildFiscalModuleOverview(
  module: FiscalPortfolioModuleKey,
  options?: { data_origin?: 'DEMO' | 'SIMULATED' | 'LIVE', officeId?: number }
): FiscalModuleOverview {
  const origin = options?.data_origin ?? 'DEMO'
  const officeId = options?.officeId ?? FISCAL_FIXTURE_OFFICE_ID
  const tenantLabel = fiscalLegalNameForOffice(officeId)
  const counters = fiscalCounters(
    module === 'fgts'
      ? { up_to_date: 0, pending: 1, attention: 1, error: 0, processing: 0 }
      : undefined
  )
  return {
    module_key: module,
    module_label: FISCAL_MODULE_LABELS[module],
    data_origin: origin,
    data_origin_label: fiscalDataOriginLabel(origin),
    is_synthetic: isSyntheticFiscalOrigin(origin),
    coverage: module === 'fgts' ? 'PARTIAL' : 'FULL',
    source_label: origin === 'DEMO' ? 'Fixture demonstrativa' : 'Fonte produtiva',
    as_of: FISCAL_FIXTURE_NOW,
    total_clients: 2,
    counters,
    agenda: [{
      client_id: FISCAL_FIXTURE_CLIENT_ID,
      label: `Prazo ${FISCAL_MODULE_LABELS[module]} · ${tenantLabel}`,
      due_at: '2026-07-20T00:00:00.000Z',
      situation: 'PENDING',
      href: `/monitoring/clients/${FISCAL_FIXTURE_CLIENT_ID}`
    }],
    categories: [{
      id: categoryIdFor(module),
      code: categoryCodeFor(module),
      name: FISCAL_MODULE_LABELS[module],
      default_coverage: module === 'fgts' ? 'PARTIAL' : 'FULL',
      linked_clients: 2
    }],
    metrics: {
      total_clients: 2,
      partial_coverage: module === 'fgts',
      guide_payment_supported: module === 'guides',
      open_messages: module === 'mailbox' ? 1 : 0,
      unconfirmed_payment_guides: module === 'guides' ? 1 : 0
    }
  }
}

export function buildFiscalModuleClientRow(
  module: FiscalPortfolioModuleKey,
  options?: {
    client_id?: number
    situation?: string
    data_origin?: 'DEMO' | 'SIMULATED' | 'LIVE'
    legal_name?: string
    cnpj_raw?: string
    root_raw?: string
  }
): FiscalModuleClientRow {
  const clientId = options?.client_id ?? FISCAL_FIXTURE_CLIENT_ID
  const origin = options?.data_origin ?? 'DEMO'
  const cnpjRaw = options?.cnpj_raw
    ?? (clientId === FISCAL_FIXTURE_CLIENT_ID_B ? FISCAL_FIXTURE_CNPJ_B_RAW : FISCAL_FIXTURE_CNPJ_RAW)
  const rootRaw = options?.root_raw
    ?? (clientId === FISCAL_FIXTURE_CLIENT_ID_B ? FISCAL_FIXTURE_ROOT_B_RAW : FISCAL_FIXTURE_ROOT_RAW)
  const legalName = options?.legal_name
    ?? (clientId === FISCAL_FIXTURE_CLIENT_ID_B ? FISCAL_FIXTURE_LEGAL_NAME_B : FISCAL_FIXTURE_LEGAL_NAME)
  const situation = options?.situation
    ?? (clientId === FISCAL_FIXTURE_CLIENT_ID_B ? 'UP_TO_DATE' : 'PENDING')

  const base = {
    module_key: module,
    client_id: clientId,
    legal_name: legalName,
    display_name: null as string | null,
    name: legalName,
    cnpj_masked: maskCnpjFixture(cnpjRaw),
    root_cnpj_masked: maskRootCnpjFixture(rootRaw),
    competence: FISCAL_FIXTURE_COMPETENCE,
    situation,
    coverage: module === 'fgts' ? 'PARTIAL' : 'FULL',
    data_origin: origin,
    last_consulted_at: FISCAL_FIXTURE_NOW,
    next_deadline_at: '2026-07-20T00:00:00.000Z',
    next_action: situation === 'UP_TO_DATE' ? null : 'Revisar pendência',
    links: {
      client: `/api/v1/clients/${clientId}`
    },
    detail: buildModuleDetail(module)
  }

  return base as FiscalModuleClientRow
}

export function buildFiscalModuleClients(
  module: FiscalPortfolioModuleKey,
  options?: { data_origin?: 'DEMO' | 'SIMULATED' | 'LIVE', officeId?: number }
): FiscalModuleClientRow[] {
  const origin = options?.data_origin ?? 'DEMO'
  const officeId = options?.officeId ?? FISCAL_FIXTURE_OFFICE_ID
  const primaryName = fiscalLegalNameForOffice(officeId)
  const secondaryName = officeId === FISCAL_FIXTURE_OFFICE_B_ID
    ? `${FISCAL_FIXTURE_SENTINEL_LEGAL_NAME} Filial`
    : FISCAL_FIXTURE_LEGAL_NAME_B
  return [
    buildFiscalModuleClientRow(module, {
      client_id: FISCAL_FIXTURE_CLIENT_ID,
      situation: 'PENDING',
      data_origin: origin,
      legal_name: primaryName
    }),
    buildFiscalModuleClientRow(module, {
      client_id: FISCAL_FIXTURE_CLIENT_ID_B,
      situation: 'ATTENTION',
      data_origin: origin,
      legal_name: secondaryName
    })
  ]
}

export function buildFiscalModuleClientsPage(
  module: FiscalPortfolioModuleKey,
  scenario: FiscalListScenario = 'ready',
  options?: FiscalTenantContext
) {
  const rows = scenario === 'empty'
    ? []
    : buildFiscalModuleClients(module, { officeId: options?.officeId })
  return pageEnvelope(rows)
}

export function buildFiscalCategories(): FiscalCategory[] {
  return FISCAL_PORTFOLIO_MODULE_KEYS.map((module, index) => ({
    id: categoryIdFor(module),
    code: categoryCodeFor(module),
    name: FISCAL_MODULE_LABELS[module],
    module_key: categoryModuleKeyFor(module),
    default_coverage: module === 'fgts' ? 'PARTIAL' : 'FULL',
    default_mutability: 'READ_ONLY',
    system_code: systemCodeFor(module),
    service_code: serviceCodeFor(module),
    is_active: true,
    sort_order: index + 1,
    description: `Categoria fixture ${FISCAL_MODULE_LABELS[module]}`
  }))
}

export function buildFiscalRuns(tenant: FiscalTenantContext = {}): FiscalMonitoringRun[] {
  const officeId = tenant.officeId ?? FISCAL_FIXTURE_OFFICE_ID
  return [{
    id: 801,
    office_id: officeId,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    fiscal_category_id: categoryIdFor('sitfis'),
    system_code: 'INTEGRA_SITFIS',
    service_code: 'SITFIS',
    operation_code: 'MONITOR',
    trigger: 'SCHEDULED',
    status: 'COMPLETED',
    result: 'SUCCESS',
    situation: 'PENDING',
    coverage: 'FULL',
    mutability: 'READ_ONLY',
    attempt: 1,
    correlation_id: 'corr-demo-801',
    items_processed: 3,
    pages_processed: 1,
    started_at: '2026-07-14T14:00:00.000Z',
    finished_at: '2026-07-14T14:02:00.000Z',
    created_at: '2026-07-14T14:00:00.000Z'
  }, {
    id: 802,
    office_id: officeId,
    client_id: FISCAL_FIXTURE_CLIENT_ID_B,
    fiscal_category_id: categoryIdFor('dctfweb'),
    system_code: 'INTEGRA_DCTFWEB',
    service_code: 'DCTFWEB',
    operation_code: 'CONSULT',
    trigger: 'MANUAL',
    status: 'FAILED',
    result: 'ERROR',
    situation: 'ERROR',
    coverage: 'FULL',
    attempt: 2,
    correlation_id: 'corr-demo-802',
    items_processed: 0,
    pages_processed: 0,
    error_code: 'DEMO_SANITIZED',
    error_message: 'Falha sintética sanitizada na consulta.',
    started_at: '2026-07-14T13:00:00.000Z',
    finished_at: '2026-07-14T13:01:00.000Z',
    created_at: '2026-07-14T13:00:00.000Z'
  }]
}

export function buildFiscalSnapshots(): FiscalSnapshot[] {
  return [{
    id: 931,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    run_id: 801,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    competence_id: 901,
    evidence_artifact_id: 881,
    system_code: 'INTEGRA_SITFIS',
    service_code: 'SITFIS',
    operation_code: 'MONITOR',
    situation: 'PENDING',
    coverage: 'FULL',
    version: 1,
    is_current: true,
    normalized: {
      demo_fixture: true,
      protocol: 'DEMO-PROT-001',
      findings_count: 1,
      pending_count: 1
    },
    observed_at: FISCAL_FIXTURE_NOW,
    created_at: FISCAL_FIXTURE_NOW
  }]
}

export function buildFiscalFindings(tenant: FiscalTenantContext = {}): FiscalFinding[] {
  const officeId = tenant.officeId ?? FISCAL_FIXTURE_OFFICE_ID
  const name = fiscalLegalNameForOffice(officeId)
  return [{
    id: 851,
    office_id: officeId,
    snapshot_id: 931,
    run_id: 801,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    code: 'DEMO_PENDING',
    severity: 'HIGH',
    title: `Finding demo · ${name}`,
    detail: 'Fixture sanitizada — sem XML nem material de cofre.',
    situation: 'ATTENTION',
    is_active: true,
    resolved_at: null,
    created_at: FISCAL_FIXTURE_NOW
  }, {
    id: 852,
    office_id: officeId,
    snapshot_id: 931,
    run_id: 801,
    client_id: FISCAL_FIXTURE_CLIENT_ID_B,
    code: 'DEMO_INFO',
    severity: 'LOW',
    title: `Finding demo informativo · ${name}`,
    detail: 'Segundo contribuinte em atenção leve.',
    situation: 'ATTENTION',
    is_active: true,
    resolved_at: null,
    created_at: FISCAL_FIXTURE_NOW
  }]
}

export function buildFiscalPendingItems(tenant: FiscalTenantContext = {}): FiscalPendingItem[] {
  const officeId = tenant.officeId ?? FISCAL_FIXTURE_OFFICE_ID
  const name = fiscalLegalNameForOffice(officeId)
  return [{
    id: 861,
    office_id: officeId,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    snapshot_id: 931,
    run_id: 801,
    fiscal_category_id: categoryIdFor('declarations'),
    competence_id: 901,
    code: 'DEMO_PENDING_DECL',
    title: `Pendência demo declarações · ${name}`,
    detail: 'Entrega pendente no período demonstrativo.',
    severity: 'MEDIUM',
    status: 'OPEN',
    situation: 'PENDING',
    due_at: '2026-07-25T00:00:00.000Z',
    resolved_at: null,
    logical_key: 'demo.pending.declarations.2026-07',
    created_at: FISCAL_FIXTURE_NOW
  }, {
    id: 862,
    office_id: officeId,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    snapshot_id: 931,
    run_id: 801,
    fiscal_category_id: categoryIdFor('sitfis'),
    code: 'DEMO_PENDING_SITFIS',
    title: `Pendência demo SITFIS · ${name}`,
    detail: 'Pendência normalizada na situação fiscal.',
    severity: 'HIGH',
    status: 'OPEN',
    situation: 'ATTENTION',
    due_at: '2026-07-18T00:00:00.000Z',
    resolved_at: null,
    logical_key: 'demo.pending.sitfis.p1',
    created_at: FISCAL_FIXTURE_NOW
  }]
}

/** IDs estáveis para E2E visual (mailbox mestre–detalhe). */
export const FISCAL_MAILBOX_MESSAGE_ID = 9001
export const FISCAL_MAILBOX_SECOND_MESSAGE_ID = 9002

export function buildMailboxMessages(tenant: FiscalTenantContext = {}) {
  const officeId = tenant.officeId ?? FISCAL_FIXTURE_OFFICE_ID
  const name = fiscalLegalNameForOffice(officeId)
  return [{
    id: FISCAL_MAILBOX_MESSAGE_ID,
    office_id: officeId,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    external_id: 'demo-msg-9001',
    source: 'CAIXA_POSTAL',
    sensitivity_class: 'STANDARD',
    category_code: 'INTIMACAO',
    category_label: 'Intimação',
    sender_code: 'RFB',
    sender_label: 'Receita Federal (fixture)',
    subject_preview: `DEMONSTRAÇÃO — SEM VALIDADE FISCAL · Intimação de prazo · ${name}`,
    received_at_official: FISCAL_FIXTURE_NOW,
    due_at: '2026-07-30T00:00:00.000Z',
    severity_hint: 'HIGH',
    official_read_indicator: 'UNREAD',
    triage_status: 'NEW',
    has_body: true,
    attachment_count: 1,
    body_byte_size: 512,
    created_at: FISCAL_FIXTURE_NOW,
    updated_at: FISCAL_FIXTURE_NOW
  }, {
    id: FISCAL_MAILBOX_SECOND_MESSAGE_ID,
    office_id: officeId,
    client_id: FISCAL_FIXTURE_CLIENT_ID_B,
    external_id: 'demo-msg-9002',
    source: 'CAIXA_POSTAL',
    sensitivity_class: 'STANDARD',
    category_code: 'COMUNICADO',
    category_label: 'Comunicado',
    sender_code: 'RFB',
    sender_label: 'Receita Federal (fixture)',
    subject_preview: `DEMONSTRAÇÃO — SEM VALIDADE FISCAL · Comunicado resolvido · ${name}`,
    received_at_official: '2026-07-10T12:00:00.000Z',
    due_at: null,
    severity_hint: 'LOW',
    official_read_indicator: 'READ',
    triage_status: 'RESOLVED',
    has_body: true,
    attachment_count: 0,
    body_byte_size: 256,
    created_at: '2026-07-10T12:00:00.000Z',
    updated_at: '2026-07-11T09:00:00.000Z'
  }]
}

export function buildMailboxMessageDetail(
  id = FISCAL_MAILBOX_MESSAGE_ID,
  tenant: FiscalTenantContext = {}
) {
  const list = buildMailboxMessages(tenant)
  const base = list.find(m => m.id === id) || list[0]!
  return {
    ...base,
    body_content_type: 'text/plain',
    body_sha256: 'e'.repeat(64),
    retention_until: '2027-07-14T00:00:00.000Z',
    official_read_observed_at: null,
    triage_by: null,
    triage_at: null,
    triage_note: null,
    // Anexos: metadados apenas — sem bytes, vault path ou PEM
    attachments: base.attachment_count
      ? [{
          id: 9101,
          filename: 'anexo-demo.txt',
          name: 'anexo-demo.txt',
          content_type: 'text/plain',
          byte_size: 64,
          content_sha256: 'f'.repeat(64)
        }]
      : []
  }
}

export function buildGuides() {
  return [{
    id: 961,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    establishment_id: 11,
    system_code: 'INTEGRA_PGDAS',
    service_code: 'DAS',
    operation_code: 'EMITIR_GUIA',
    competence_period_key: FISCAL_FIXTURE_COMPETENCE,
    debit_ref: 'DEMO-DAS-001',
    logical_key: 'demo.guide.das.2026-07',
    payment_status: 'ISSUED',
    payment_confirmed_at: null,
    payment_source: null,
    amount_cents: 34567,
    currency: 'BRL',
    due_at: '2026-07-20T00:00:00.000Z',
    identifier_code: 'DEMO-GUIDE-961',
    current_version_id: 962,
    current_version: {
      id: 962,
      version: 1,
      status: 'AVAILABLE',
      content_sha256: 'a1'.repeat(32),
      byte_size: 1024,
      content_type: 'application/pdf',
      issued_at: FISCAL_FIXTURE_NOW
    },
    created_at: FISCAL_FIXTURE_NOW,
    data_origin: 'DEMO',
    is_synthetic: true
  }, {
    id: 963,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID_B,
    establishment_id: null,
    system_code: 'INTEGRA_DCTFWEB',
    service_code: 'DARF',
    operation_code: 'EMITIR_GUIA',
    competence_period_key: '2026-06',
    debit_ref: 'DEMO-DARF-002',
    logical_key: 'demo.guide.darf.2026-06',
    payment_status: 'CONFIRMED',
    payment_confirmed_at: '2026-06-28T10:00:00.000Z',
    payment_source: 'MANUAL',
    amount_cents: 12000,
    currency: 'BRL',
    due_at: '2026-06-20T00:00:00.000Z',
    identifier_code: 'DEMO-GUIDE-963',
    current_version_id: 964,
    current_version: {
      id: 964,
      version: 1,
      status: 'AVAILABLE',
      content_sha256: 'b2'.repeat(32),
      byte_size: 800,
      content_type: 'application/pdf',
      issued_at: '2026-06-10T10:00:00.000Z'
    },
    created_at: '2026-06-10T10:00:00.000Z',
    data_origin: 'DEMO',
    is_synthetic: true
  }]
}

export function buildGuideDetail(id = 961) {
  const guides = buildGuides()
  const base = guides.find(g => g.id === id) || guides[0]!
  return {
    ...base,
    payment_confirmations: base.payment_status === 'CONFIRMED'
      ? [{
          id: 965,
          guide_id: base.id,
          confirmed_at: base.payment_confirmed_at,
          source: 'MANUAL',
          amount_cents: base.amount_cents,
          note: 'Confirmação demo'
        }]
      : [],
    versions: base.current_version ? [base.current_version] : []
  }
}

export function buildDeclarations() {
  return [{
    id: 951,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    obligation_definition_id: 71,
    obligation_code: 'DEFIS',
    obligation_name: 'DEFIS (fixture)',
    module_key: 'declarations',
    system_code: 'DECLARACOES',
    service_code: 'DEFIS',
    obligation_version_id: 72,
    calendar_version_id: 73,
    competence_id: 901,
    period_key: '2025',
    period_year: 2025,
    period_month: null,
    applicability: 'APPLICABLE',
    situation: 'PENDING',
    delivery_status: 'PENDING',
    due_at: '2026-03-31T00:00:00.000Z',
    applicability_basis: { source: 'demo' },
    is_open: true,
    closed_at: null,
    conclusive_evidence_id: null,
    evidence_artifact_id: null,
    deep_links: {
      client: `/monitoring/clients/${FISCAL_FIXTURE_CLIENT_ID}?tab=declarations`
    },
    data_origin: 'DEMO',
    is_synthetic: true
  }, {
    id: 952,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID_B,
    obligation_definition_id: 74,
    obligation_code: 'PGDASD',
    obligation_name: 'PGDAS-D (fixture)',
    module_key: 'declarations',
    system_code: 'SIMPLES',
    service_code: 'PGDASD',
    period_key: FISCAL_FIXTURE_COMPETENCE,
    period_year: 2026,
    period_month: 7,
    applicability: 'APPLICABLE',
    situation: 'UP_TO_DATE',
    delivery_status: 'DELIVERED',
    due_at: '2026-07-20T00:00:00.000Z',
    is_open: false,
    closed_at: FISCAL_FIXTURE_NOW,
    conclusive_evidence_id: 882,
    evidence_artifact_id: 882,
    deep_links: {
      client: `/monitoring/clients/${FISCAL_FIXTURE_CLIENT_ID_B}?tab=declarations`
    },
    data_origin: 'DEMO',
    is_synthetic: true
  }]
}

export function buildDeclarationsSummary() {
  return [
    {
      obligation_code: 'DEFIS',
      obligation_name: 'DEFIS (fixture)',
      open_count: 1,
      pending_count: 1,
      delivered_count: 0,
      attention_count: 0,
      error_count: 0
    },
    {
      obligation_code: 'PGDASD',
      obligation_name: 'PGDAS-D (fixture)',
      open_count: 0,
      pending_count: 0,
      delivered_count: 1,
      attention_count: 0,
      error_count: 0
    }
  ]
}

export function buildInstallmentOrders() {
  return [{
    id: 921,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    modality: 'REL_PERT',
    regime: 'GERAL',
    external_order_id: 'DEMO-ORD-001',
    situation: 'ACTIVE',
    source_status: 'ATIVO',
    requested_at: '2026-01-10T00:00:00.000Z',
    consolidated_at: '2026-01-15T00:00:00.000Z',
    parcel_count: 12,
    total_amount_cents: 150000,
    source_system: 'INTEGRA',
    source_service: 'PARCELAMENTOS',
    observed_at: FISCAL_FIXTURE_NOW,
    created_at: FISCAL_FIXTURE_NOW,
    data_origin: 'DEMO',
    is_synthetic: true
  }]
}

export function buildInstallmentParcels() {
  return [{
    id: 922,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    order_id: 921,
    parcel_number: 3,
    due_at: '2026-08-15T00:00:00.000Z',
    amount_cents: 12500,
    status: 'PENDING',
    situation: 'PENDING',
    paid_at: null,
    created_at: FISCAL_FIXTURE_NOW
  }, {
    id: 923,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    order_id: 921,
    parcel_number: 2,
    due_at: '2026-07-15T00:00:00.000Z',
    amount_cents: 12500,
    status: 'ATTENTION',
    situation: 'ATTENTION',
    paid_at: null,
    created_at: FISCAL_FIXTURE_NOW
  }]
}

export function buildInstallmentModalities() {
  return [{
    code: 'REL_PERT',
    name: 'Parcelamento especial (fixture)',
    active: true
  }, {
    code: 'ORDINARIO',
    name: 'Ordinário (fixture)',
    active: true
  }]
}

export function buildFgtsCoverage(): FgtsCoverageManifest {
  return {
    module: 'fgts',
    coverage: 'PARTIAL',
    coverage_label: 'FGTS (parcial eSocial)',
    system_code: 'ESOCIAL',
    service_code: 'FGTS',
    supported_events: [
      { code: 'S-1299', label: 'Fechamento dos eventos periódicos' },
      { code: 'S-5003', label: 'Informações do FGTS por trabalhador' }
    ],
    independent_states: {
      closure: 'Fechamento eSocial (S-1299) — independente de guia/pagamento',
      totalization: 'Totalização (S-5003/S-5013) — base conhecida',
      guide: 'Guia FGTS Digital — UNSUPPORTED (sem API pública)',
      payment: 'Pagamento FGTS Digital — UNSUPPORTED (sem API pública)'
    },
    limitations: [
      'Sem API pública M2M para FGTS Digital (guia/pagamento).',
      'Cobertura parcial via eventos eSocial suportados.',
      'Portal humano e scraping são proibidos.'
    ],
    declares_fgts_digital_debt: false,
    scraping_allowed: false,
    portal_fallback: false,
    totalizer_absence_window_hours: 72
  }
}

export function buildFgtsCompetences() {
  return [{
    id: 971,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    establishment_id: 11,
    competence_period_key: FISCAL_FIXTURE_COMPETENCE,
    closure_status: 'CLOSED',
    closure_status_label: 'Fechado',
    totalization_status: 'RECEIVED',
    totalization_status_label: 'Recebido',
    guide_status: 'UNSUPPORTED',
    guide_status_label: 'Não suportado',
    payment_status: 'UNSUPPORTED',
    payment_status_label: 'Não suportado',
    coverage: 'PARTIAL',
    situation: 'ATTENTION',
    closure_observed_at: FISCAL_FIXTURE_NOW,
    totalizer_observed_at: FISCAL_FIXTURE_NOW,
    totalizer_due_by: '2026-07-17T00:00:00.000Z',
    last_synced_at: FISCAL_FIXTURE_NOW,
    limitations: [
      'Guia e pagamento FGTS Digital permanecem UNSUPPORTED sem API pública M2M.'
    ],
    partial_coverage: true,
    declares_fgts_digital_debt: false,
    run_id: 803,
    snapshot_id: 932,
    data_origin: 'DEMO',
    is_synthetic: true
  }]
}

export function buildFgtsEvents() {
  return [{
    id: 981,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    competence_period_key: FISCAL_FIXTURE_COMPETENCE,
    event_code: 'S-1299',
    event_label: 'Fechamento dos eventos periódicos',
    observed_at: FISCAL_FIXTURE_NOW,
    content_sha256: 'c3'.repeat(32),
    byte_size: 256,
    data_origin: 'DEMO',
    is_synthetic: true
  }]
}

export function buildSitfisView(clientId = FISCAL_FIXTURE_CLIENT_ID) {
  const snap = buildFiscalSnapshots()[0]!
  return {
    snapshot: { ...snap, client_id: clientId },
    age_seconds: 3600,
    observed_at: FISCAL_FIXTURE_NOW,
    expires_at: '2026-07-15T15:00:00.000Z',
    ttl_seconds: 86400,
    is_within_ttl: true,
    is_negative_certificate: false,
    disclaimer: 'Ausência de pendência reconhecida não equivale a certidão negativa.',
    active_run: null,
    cache_key_hint: `sitfis:snap:${clientId}`,
    data_origin: 'DEMO',
    is_synthetic: true
  }
}

export function buildDctfwebDeclarations() {
  return [{
    id: 911,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    period_key: FISCAL_FIXTURE_COMPETENCE,
    transmission_status: 'TRANSMITTED',
    payment_status: 'PENDING',
    receipt_number: 'DEMO-RX-001',
    situation: 'PENDING',
    data_origin: 'DEMO',
    is_synthetic: true,
    created_at: FISCAL_FIXTURE_NOW
  }]
}

export function buildMitApuracoes() {
  return [{
    id: 912,
    office_id: FISCAL_FIXTURE_OFFICE_ID,
    client_id: FISCAL_FIXTURE_CLIENT_ID,
    period_key: FISCAL_FIXTURE_COMPETENCE,
    encerramento_status: 'OPEN',
    dctfweb_transmission_status: 'PENDING',
    situation: 'ATTENTION',
    data_origin: 'DEMO',
    is_synthetic: true,
    created_at: FISCAL_FIXTURE_NOW
  }]
}

export function buildSimplesMeiCatalog() {
  return [
    { code: 'PGDASD', name: 'PGDAS-D', module_key: 'simples_mei' },
    { code: 'PGMEI', name: 'PGMEI', module_key: 'simples_mei' },
    { code: 'DASN_SIMEI', name: 'DASN-SIMEI', module_key: 'simples_mei' }
  ]
}

export function buildDeclarationsCatalog() {
  return [
    { code: 'DEFIS', name: 'DEFIS', module_key: 'declarations' },
    { code: 'PGDASD', name: 'PGDAS-D', module_key: 'declarations' }
  ]
}

// ---------------------------------------------------------------------------
// Contract assertions (unit / integration)
// ---------------------------------------------------------------------------

export function collectObjectKeys(value: unknown, acc = new Set<string>(), depth = 0): Set<string> {
  if (depth > 8 || value == null) return acc
  if (Array.isArray(value)) {
    for (const item of value) collectObjectKeys(item, acc, depth + 1)
    return acc
  }
  if (typeof value === 'object') {
    for (const [k, v] of Object.entries(value as Record<string, unknown>)) {
      acc.add(k)
      collectObjectKeys(v, acc, depth + 1)
    }
  }
  return acc
}

export function assertFiscalPayloadSanitized(payload: unknown, label = 'payload'): void {
  const keys = collectObjectKeys(payload)
  for (const forbidden of FISCAL_FORBIDDEN_KEYS) {
    if (keys.has(forbidden)) {
      throw new Error(`${label}: campo proibido "${forbidden}" presente no payload fiscal.`)
    }
  }
  const serialized = JSON.stringify(payload)
  for (const pattern of FISCAL_FORBIDDEN_CONTENT) {
    if (pattern.test(serialized)) {
      throw new Error(`${label}: conteúdo proibido detectado (${pattern}).`)
    }
  }
  // CNPJ completo de 14 chars sem máscara não deve aparecer se houver cnpj_masked
  if (serialized.includes(FISCAL_FIXTURE_CNPJ_RAW) && keys.has('cnpj_masked')) {
    throw new Error(`${label}: CNPJ completo não mascarado exposto junto a cnpj_masked.`)
  }
}

export function assertOverviewEnvelope(
  module: FiscalPortfolioModuleKey,
  body: unknown
): asserts body is { data: FiscalModuleOverview } {
  if (!body || typeof body !== 'object') {
    throw new Error(`overview ${module}: envelope ausente (esperado objeto com data).`)
  }
  const data = (body as { data?: unknown }).data
  if (!data || typeof data !== 'object') {
    throw new Error(`overview ${module}: data ausente no envelope.`)
  }
  const o = data as Record<string, unknown>
  if (o.module_key !== module) {
    throw new Error(
      `overview ${module}: module_key incompatível (recebido "${String(o.module_key)}").`
    )
  }
  if (typeof o.total_clients !== 'number') {
    throw new Error(`overview ${module}: total_clients deve ser number.`)
  }
  const counters = o.counters as Record<string, unknown> | undefined
  if (!counters || typeof counters !== 'object') {
    throw new Error(`overview ${module}: counters ausentes.`)
  }
  for (const key of ['up_to_date', 'processing', 'pending', 'attention', 'error'] as const) {
    if (typeof counters[key] !== 'number') {
      throw new Error(`overview ${module}: counters.${key} deve ser number.`)
    }
  }
  if (o.data_origin != null && !['DEMO', 'SIMULATED', 'LIVE'].includes(String(o.data_origin))) {
    throw new Error(`overview ${module}: data_origin inválido "${String(o.data_origin)}".`)
  }
  assertFiscalPayloadSanitized(body, `overview ${module}`)
}

export function assertClientRowEnvelope(
  module: FiscalPortfolioModuleKey,
  row: unknown
): asserts row is FiscalModuleClientRow {
  if (!row || typeof row !== 'object') {
    throw new Error(`client row ${module}: linha ausente.`)
  }
  const r = row as Record<string, unknown>
  if (r.module_key !== module) {
    throw new Error(
      `client row ${module}: module_key incompatível (recebido "${String(r.module_key)}").`
    )
  }
  if (typeof r.client_id !== 'number') {
    throw new Error(`client row ${module}: client_id deve ser number.`)
  }
  if (typeof r.legal_name !== 'string' || !r.legal_name) {
    throw new Error(`client row ${module}: legal_name obrigatório.`)
  }
  if (typeof r.cnpj_masked !== 'string' || !String(r.cnpj_masked).includes('*')) {
    throw new Error(`client row ${module}: cnpj_masked deve conter máscara.`)
  }
  if (typeof r.situation !== 'string') {
    throw new Error(`client row ${module}: situation obrigatório.`)
  }
  if (typeof r.coverage !== 'string') {
    throw new Error(`client row ${module}: coverage obrigatório.`)
  }
  if (!r.detail || typeof r.detail !== 'object') {
    throw new Error(`client row ${module}: detail discriminado obrigatório.`)
  }
  const detail = r.detail as Record<string, unknown>
  if (detail.module_key != null && detail.module_key !== module) {
    throw new Error(
      `client row ${module}: detail.module_key incompatível (recebido "${String(detail.module_key)}").`
    )
  }
  assertFiscalPayloadSanitized(row, `client row ${module}`)
}

export function assertClientsPageEnvelope(
  module: FiscalPortfolioModuleKey,
  body: unknown
): void {
  if (!body || typeof body !== 'object') {
    throw new Error(`clients ${module}: envelope ausente.`)
  }
  const data = (body as { data?: unknown }).data
  if (!Array.isArray(data)) {
    throw new Error(`clients ${module}: data deve ser array.`)
  }
  for (const row of data) {
    assertClientRowEnvelope(module, row)
  }
  // Nenhum registro de outro módulo
  for (const row of data as Array<{ module_key?: string }>) {
    if (row.module_key !== module) {
      throw new Error(
        `clients ${module}: registro de outro module_key "${row.module_key}".`
      )
    }
  }
  assertFiscalPayloadSanitized(body, `clients ${module}`)
}

export function assertListEnvelope(label: string, body: unknown): void {
  if (!body || typeof body !== 'object') {
    throw new Error(`${label}: envelope ausente.`)
  }
  const data = (body as { data?: unknown }).data
  if (!Array.isArray(data)) {
    throw new Error(`${label}: data deve ser array.`)
  }
  assertFiscalPayloadSanitized(body, label)
}

/** Monta resposta de overview/clients conforme cenário (sem I/O). */
export function fiscalModuleOverviewResponse(
  module: FiscalPortfolioModuleKey,
  scenario: FiscalListScenario = 'ready',
  tenant: FiscalTenantContext = {}
): { data: FiscalModuleOverview } | { message: string } {
  if (scenario === 'error') {
    return { message: 'Falha sintética sanitizada no overview fiscal.' }
  }
  if (scenario === 'empty') {
    return {
      data: {
        ...buildFiscalModuleOverview(module, { officeId: tenant.officeId }),
        total_clients: 0,
        counters: fiscalCounters({
          up_to_date: 0,
          processing: 0,
          pending: 0,
          attention: 0,
          error: 0
        }),
        agenda: [],
        metrics: { total_clients: 0, partial_coverage: module === 'fgts' }
      }
    }
  }
  return { data: buildFiscalModuleOverview(module, { officeId: tenant.officeId }) }
}

export function fiscalModuleClientsResponse(
  module: FiscalPortfolioModuleKey,
  scenario: FiscalListScenario = 'ready',
  tenant: FiscalTenantContext = {}
) {
  if (scenario === 'error') {
    return { message: 'Falha sintética sanitizada na carteira fiscal.' }
  }
  return buildFiscalModuleClientsPage(module, scenario, tenant)
}

export function isPortfolioModulePathSegment(segment: string): segment is FiscalPortfolioModuleKey {
  return isFiscalPortfolioModule(segment)
}

// ---------------------------------------------------------------------------
// helpers internos
// ---------------------------------------------------------------------------

function categoryIdFor(module: FiscalPortfolioModuleKey): number {
  const map: Record<FiscalPortfolioModuleKey, number> = {
    simples_mei: 101,
    dctfweb: 102,
    installments: 103,
    sitfis: 104,
    mailbox: 105,
    declarations: 106,
    guides: 107,
    fgts: 108
  }
  return map[module]
}

function categoryCodeFor(module: FiscalPortfolioModuleKey): string {
  const map: Record<FiscalPortfolioModuleKey, string> = {
    simples_mei: 'SIMPLES_MEI',
    dctfweb: 'DCTFWEB',
    installments: 'PARCELAMENTOS',
    sitfis: 'SITFIS',
    mailbox: 'CAIXA_POSTAL',
    declarations: 'DECLARACOES',
    guides: 'GUIAS',
    fgts: 'FGTS'
  }
  return map[module]
}

function categoryModuleKeyFor(module: FiscalPortfolioModuleKey): string {
  // Alinha a feature flags / fiscal_categories.module_key do backend
  const map: Record<FiscalPortfolioModuleKey, string> = {
    simples_mei: 'simples_mei',
    dctfweb: 'dctfweb_mit',
    installments: 'parcelamentos',
    sitfis: 'sitfis',
    mailbox: 'mailbox',
    declarations: 'declaracoes',
    guides: 'guias',
    fgts: 'fgts'
  }
  return map[module]
}

function systemCodeFor(module: FiscalPortfolioModuleKey): string {
  const map: Record<FiscalPortfolioModuleKey, string> = {
    simples_mei: 'INTEGRA_SIMPLES',
    dctfweb: 'INTEGRA_DCTFWEB',
    installments: 'INTEGRA_PARC',
    sitfis: 'INTEGRA_SITFIS',
    mailbox: 'INTEGRA_CAIXA',
    declarations: 'DECLARACOES',
    guides: 'INTEGRA_GUIAS',
    fgts: 'ESOCIAL'
  }
  return map[module]
}

function serviceCodeFor(module: FiscalPortfolioModuleKey): string {
  const map: Record<FiscalPortfolioModuleKey, string> = {
    simples_mei: 'PGDASD',
    dctfweb: 'DCTFWEB',
    installments: 'PARCELAMENTOS',
    sitfis: 'SITFIS',
    mailbox: 'CAIXA_POSTAL',
    declarations: 'OBRIGACOES',
    guides: 'GUIAS',
    fgts: 'FGTS'
  }
  return map[module]
}
