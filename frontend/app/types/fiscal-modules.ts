/**
 * Espelho TypeScript dos enums e DTOs de módulo / situação / cobertura / origem
 * e dos read models de carteira (overview + clients).
 *
 * Fonte canônica: backend App\Enums\Fiscal* e App\DTO\Fiscal\Module\*.
 * Discriminados por `module_key` — não usar Record<string, unknown> nos fluxos principais.
 */

// ---------------------------------------------------------------------------
// Enums / catálogos
// ---------------------------------------------------------------------------

/** Chaves de módulo da carteira (read model + rotas /monitoring). */
export type FiscalModuleKey
  = | 'dashboard'
    | 'simples_mei'
    | 'dctfweb'
    | 'installments'
    | 'sitfis'
    | 'mailbox'
    | 'declarations'
    | 'guides'
    | 'fgts'

/** Módulos com overview/carteira REST (exclui dashboard agregado). */
export type FiscalPortfolioModuleKey = Exclude<FiscalModuleKey, 'dashboard'>

export const FISCAL_MODULE_KEYS: readonly FiscalModuleKey[] = [
  'dashboard',
  'simples_mei',
  'dctfweb',
  'installments',
  'sitfis',
  'mailbox',
  'declarations',
  'guides',
  'fgts'
] as const

export const FISCAL_PORTFOLIO_MODULE_KEYS: readonly FiscalPortfolioModuleKey[] = [
  'simples_mei',
  'dctfweb',
  'installments',
  'sitfis',
  'mailbox',
  'declarations',
  'guides',
  'fgts'
] as const

export const FISCAL_MODULE_LABELS: Record<FiscalModuleKey, string> = {
  dashboard: 'Dashboard fiscal',
  simples_mei: 'Simples / MEI',
  dctfweb: 'DCTFWeb / MIT',
  installments: 'Parcelamentos',
  sitfis: 'Situação fiscal',
  mailbox: 'Caixa postal',
  declarations: 'Declarações',
  guides: 'Guias',
  fgts: 'FGTS / eSocial'
}

export const FISCAL_MODULE_PATHS: Record<FiscalModuleKey, string> = {
  dashboard: '/monitoring',
  simples_mei: '/monitoring/simples-mei',
  dctfweb: '/monitoring/dctfweb',
  installments: '/monitoring/installments',
  sitfis: '/monitoring/sitfis',
  mailbox: '/monitoring/mailbox',
  declarations: '/monitoring/declarations',
  guides: '/monitoring/guides',
  fgts: '/monitoring/fgts'
}

/** Segmento de rota API (`/fiscal/modules/{segment}/…`). */
export const FISCAL_MODULE_API_SEGMENTS: Record<FiscalPortfolioModuleKey, string> = {
  simples_mei: 'simples_mei',
  dctfweb: 'dctfweb',
  installments: 'installments',
  sitfis: 'sitfis',
  mailbox: 'mailbox',
  declarations: 'declarations',
  guides: 'guides',
  fgts: 'fgts'
}

/** Situação fiscal — espelho de App\Enums\FiscalSituation. */
export type FiscalSituationCode
  = | 'UP_TO_DATE'
    | 'PENDING'
    | 'PROCESSING'
    | 'ATTENTION'
    | 'ERROR'
    | 'NOT_APPLICABLE'
    | 'UNKNOWN'
    | 'UNSUPPORTED'
    | 'BLOCKED'

export const FISCAL_SITUATION_CODES: readonly FiscalSituationCode[] = [
  'UP_TO_DATE',
  'PENDING',
  'PROCESSING',
  'ATTENTION',
  'ERROR',
  'NOT_APPLICABLE',
  'UNKNOWN',
  'UNSUPPORTED',
  'BLOCKED'
] as const

/** Cobertura — espelho de App\Enums\FiscalCoverage. */
export type FiscalCoverageCode
  = | 'FULL'
    | 'PARTIAL'
    | 'UNSUPPORTED'
    | 'UNKNOWN'
    | 'NOT_APPLICABLE'

export const FISCAL_COVERAGE_CODES: readonly FiscalCoverageCode[] = [
  'FULL',
  'PARTIAL',
  'UNSUPPORTED',
  'UNKNOWN',
  'NOT_APPLICABLE'
] as const

/** Origem do dado — espelho de App\Enums\FiscalDataOrigin. */
export type FiscalDataOrigin = 'DEMO' | 'SIMULATED' | 'LIVE'

export const FISCAL_DATA_ORIGINS: readonly FiscalDataOrigin[] = [
  'DEMO',
  'SIMULATED',
  'LIVE'
] as const

export function isSyntheticFiscalOrigin(origin?: string | null): boolean {
  const v = String(origin || '').toUpperCase()
  return v === 'DEMO' || v === 'SIMULATED'
}

export function isFiscalPortfolioModule(value: string): value is FiscalPortfolioModuleKey {
  return (FISCAL_PORTFOLIO_MODULE_KEYS as readonly string[]).includes(value)
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

export interface FiscalModuleCounters {
  up_to_date: number
  processing: number
  pending: number
  attention: number
  error: number
}

export interface FiscalModuleAgendaItem {
  client_id?: number | null
  label?: string | null
  due_at?: string | null
  situation?: string | null
  href?: string | null
}

export interface FiscalModuleCategorySummary {
  id: number
  code: string
  name: string
  default_coverage?: string | null
  linked_clients: number
}

/** KPI acionável da faixa (Total + contadores). */
export type FiscalKpiKey
  = | 'total'
    | 'up_to_date'
    | 'processing'
    | 'pending'
    | 'attention'
    | 'error'

export interface FiscalModuleMetrics {
  total_clients?: number
  partial_coverage?: boolean
  guide_payment_supported?: boolean
  open_messages?: number
  unconfirmed_payment_guides?: number
}

export interface FiscalModuleOverviewBase<M extends FiscalPortfolioModuleKey = FiscalPortfolioModuleKey> {
  module_key: M
  module_label?: string
  data_origin?: FiscalDataOrigin | string | null
  data_origin_label?: string | null
  is_synthetic?: boolean
  coverage?: string | null
  source_label?: string | null
  as_of?: string | null
  total_clients: number
  counters: FiscalModuleCounters
  agenda?: FiscalModuleAgendaItem[]
  categories?: FiscalModuleCategorySummary[]
  metrics?: FiscalModuleMetrics
}

export type FiscalModuleOverview<M extends FiscalPortfolioModuleKey = FiscalPortfolioModuleKey>
  = FiscalModuleOverviewBase<M>

// ---------------------------------------------------------------------------
// Client rows (carteira) — detail discriminado por module_key
// ---------------------------------------------------------------------------

export interface FiscalClientRowBase<
  M extends FiscalPortfolioModuleKey,
  D extends FiscalModuleClientDetail
> {
  module_key: M
  client_id: number
  legal_name: string
  display_name?: string | null
  /** display_name || legal_name */
  name?: string | null
  cnpj_masked: string
  root_cnpj_masked?: string | null
  competence?: string | null
  situation: string
  coverage: string
  data_origin?: FiscalDataOrigin | string | null
  last_consulted_at?: string | null
  next_deadline_at?: string | null
  next_action?: string | null
  links?: Record<string, string | null | undefined>
  detail: D
}

export interface SimplesMeiClientDetail {
  module_key?: 'simples_mei' | string
  submodule?: string | null
  period_key?: string | null
  competence_id?: number | null
  links?: Record<string, string | null>
}

export interface DctfwebClientDetail {
  module_key?: 'dctfweb' | string
  submodule?: string | null
  dctfweb?: {
    id: number
    period_key?: string | null
    transmission_status?: string | null
    payment_status?: string | null
    receipt_number?: string | null
    situation?: string | null
  } | null
  mit?: {
    id: number
    period_key?: string | null
    encerramento_status?: string | null
    dctfweb_transmission_status?: string | null
    situation?: string | null
  } | null
  links?: Record<string, string | null>
}

export interface InstallmentsClientDetail {
  module_key?: 'installments' | string
  order_id?: number | null
  modality?: string | null
  external_order_id?: string | null
  total_amount_cents?: number | null
  parcel_count?: number | null
  order_situation?: string | null
  next_parcel_id?: number | null
  next_parcel_due_at?: string | null
  next_parcel_amount_cents?: number | null
  overdue_parcels?: number
  links?: Record<string, string | null>
}

export interface SitfisClientDetail {
  module_key?: 'sitfis' | string
  snapshot_id?: number | null
  observed_at?: string | null
  age_seconds?: number | null
  ttl_seconds?: number | null
  is_expired?: boolean
  findings_count?: number
  pending_count?: number
  links?: Record<string, string | null>
}

export interface MailboxClientDetail {
  module_key?: 'mailbox' | string
  official_unread_count?: number
  stored_message_count?: number
  open_triage_count?: number
  dte_status?: string | null
  latest_message_id?: number | null
  latest_subject_preview?: string | null
  latest_received_at?: string | null
  latest_due_at?: string | null
  links?: Record<string, string | null>
}

export interface DeclarationsClientDetail {
  module_key?: 'declarations' | string
  open_count?: number
  next_projection_id?: number | null
  next_obligation_code?: string | null
  next_period_key?: string | null
  next_due_at?: string | null
  next_delivery_status?: string | null
  next_situation?: string | null
  links?: Record<string, string | null>
}

export interface GuidesClientDetail {
  module_key?: 'guides' | string
  guides_count?: number
  open_count?: number
  unpaid_amount_cents?: number
  next_guide_id?: number | null
  next_due_at?: string | null
  next_amount_cents?: number | null
  next_payment_status?: string | null
  links?: Record<string, string | null>
}

export interface FgtsClientDetail {
  module_key?: 'fgts' | string
  competence_period_key?: string | null
  closure_status?: string | null
  totalization_status?: string | null
  guide_status?: string | null
  payment_status?: string | null
  coverage?: string | null
  last_synced_at?: string | null
  partial_coverage_notice?: string | null
  links?: Record<string, string | null>
}

export type FiscalModuleClientDetail
  = | SimplesMeiClientDetail
    | DctfwebClientDetail
    | InstallmentsClientDetail
    | SitfisClientDetail
    | MailboxClientDetail
    | DeclarationsClientDetail
    | GuidesClientDetail
    | FgtsClientDetail

export type SimplesMeiClientRow = FiscalClientRowBase<'simples_mei', SimplesMeiClientDetail>
export type DctfwebClientRow = FiscalClientRowBase<'dctfweb', DctfwebClientDetail>
export type InstallmentsClientRow = FiscalClientRowBase<'installments', InstallmentsClientDetail>
export type SitfisClientRow = FiscalClientRowBase<'sitfis', SitfisClientDetail>
export type MailboxClientRow = FiscalClientRowBase<'mailbox', MailboxClientDetail>
export type DeclarationsClientRow = FiscalClientRowBase<'declarations', DeclarationsClientDetail>
export type GuidesClientRow = FiscalClientRowBase<'guides', GuidesClientDetail>
export type FgtsClientRow = FiscalClientRowBase<'fgts', FgtsClientDetail>

export type FiscalModuleClientRow
  = | SimplesMeiClientRow
    | DctfwebClientRow
    | InstallmentsClientRow
    | SitfisClientRow
    | MailboxClientRow
    | DeclarationsClientRow
    | GuidesClientRow
    | FgtsClientRow

export type FiscalModuleClientRowFor<M extends FiscalPortfolioModuleKey>
  = Extract<FiscalModuleClientRow, { module_key: M }>

// ---------------------------------------------------------------------------
// Filtros / paginação / respostas API
// ---------------------------------------------------------------------------

export interface FiscalModulePortfolioFilters {
  page?: number
  per_page?: number
  q?: string
  situation?: string
  competence?: string
  submodule?: string
  delivery_status?: string
  /** Filtro opcional por cliente (picker / deep-link). */
  client_id?: number
  sort?: 'legal_name' | 'display_name' | 'situation' | 'last_consulted_at' | 'competence' | 'id' | string
  sort_direction?: 'asc' | 'desc'
}

/** Alias usado por useApi / páginas. */
export type FiscalModuleClientsParams = FiscalModulePortfolioFilters

export interface FiscalModuleClientsPage<M extends FiscalPortfolioModuleKey = FiscalPortfolioModuleKey> {
  data: FiscalModuleClientRowFor<M>[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  current_page?: number
  last_page?: number
  total?: number
  per_page?: number
}

export interface FiscalModuleOverviewResponse<M extends FiscalPortfolioModuleKey = FiscalPortfolioModuleKey> {
  data: FiscalModuleOverview<M>
}

/** Estado de empty/erro da carteira (UI). */
export type FiscalTableEmptyKind
  = | 'loading'
    | 'empty'
    | 'error'
    | 'unsupported'
    | 'blocked'
    | 'filtered'

/** Submódulos de UI (tabs) → valor de filtro da API. */
export const SIMPLES_MEI_TABS = [
  { label: 'PGDAS-D', value: 'PGDASD' },
  { label: 'PGMEI', value: 'PGMEI' },
  { label: 'DASN-SIMEI', value: 'DASN_SIMEI' },
  { label: 'Regime', value: 'REGIME' }
] as const

export const DCTFWEB_TABS = [
  { label: 'DCTFWeb', value: 'DCTFWEB' },
  { label: 'MIT', value: 'MIT' }
] as const

export const MAILBOX_TRIAGE_ITEMS = [
  { label: 'Todas', value: 'all' },
  { label: 'Nova', value: 'NEW' },
  { label: 'Em análise', value: 'IN_REVIEW' },
  { label: 'Resolvida', value: 'RESOLVED' }
] as const

export function fiscalKpiSituationFilter(key: FiscalKpiKey): string | null {
  switch (key) {
    case 'total':
      return null
    case 'up_to_date':
      return 'UP_TO_DATE'
    case 'processing':
      return 'PROCESSING'
    case 'pending':
      return 'PENDING'
    case 'attention':
      return 'ATTENTION'
    case 'error':
      return 'ERROR'
    default:
      return null
  }
}

/**
 * Situação da URL/filtro → chave de KPI acionável.
 * `all` / vazio / códigos fora da faixa de KPI → `total`.
 */
export function fiscalSituationToKpiKey(situation?: string | null): FiscalKpiKey {
  const sit = String(situation || '').trim().toUpperCase()
  if (!sit || sit === 'ALL') return 'total'
  switch (sit) {
    case 'UP_TO_DATE':
      return 'up_to_date'
    case 'PROCESSING':
      return 'processing'
    case 'PENDING':
      return 'pending'
    case 'ATTENTION':
      return 'attention'
    case 'ERROR':
      return 'error'
    default:
      return 'total'
  }
}

export function fiscalDataOriginLabel(origin?: string | null): string {
  const v = String(origin || '').toUpperCase()
  switch (v) {
    case 'DEMO':
      return 'Dados demonstrativos'
    case 'SIMULATED':
      return 'Dados simulados'
    case 'LIVE':
      return 'Fonte produtiva'
    default:
      return origin || 'Origem desconhecida'
  }
}
