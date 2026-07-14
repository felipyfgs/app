export type OfficeRole = 'ADMIN' | 'OPERATOR' | 'VIEWER'
export type FiscalRole = 'ISSUER' | 'TAKER' | 'INTERMEDIARY'
export type RegistrationSource = 'LEGACY' | 'MANUAL' | 'CNPJ_WS'
export type RegistrationStatus = 'ACTIVE' | 'VOID' | 'SUSPENDED' | 'UNFIT' | 'CLOSED' | 'UNKNOWN'

export interface Office {
  id: number
  name: string
  slug: string
}

export interface MeUser {
  id: number
  name: string
  email: string
  two_factor_confirmed: boolean
  two_factor_required: boolean
  requires_two_factor_setup: boolean
  office: Office | null
  role: OfficeRole | null
}

export interface MeResponse {
  data: MeUser
}

export interface AddressPayload {
  postal_code?: string | null
  street_type?: string | null
  street?: string | null
  number?: string | null
  complement?: string | null
  district?: string | null
  city?: string | null
  city_ibge_code?: string | null
  state?: string | null
  country?: string | null
}

export interface CaptureEligibility {
  eligible: boolean
  reasons: string[]
  reasons_codes: string[]
}

export interface Establishment {
  id: number
  office_id?: number
  client_id: number
  cnpj: string
  trade_name?: string | null
  is_matrix: boolean
  is_active: boolean
  registration_status?: RegistrationStatus | string | null
  registration_status_at?: string | null
  registration_status_reason?: string | null
  activity_started_at?: string | null
  main_cnae_code?: string | null
  main_cnae_name?: string | null
  address?: AddressPayload | null
  public_email?: string | null
  public_phone?: string | null
  capture_enabled?: boolean
  registration_source?: RegistrationSource | string | null
  registration_refreshed_at?: string | null
  capture_eligibility?: CaptureEligibility
  created_at?: string | null
  updated_at?: string | null
}

export interface ClientContact {
  id: number
  client_id: number
  name: string
  role?: string | null
  email?: string | null
  phone?: string | null
  is_whatsapp: boolean
  is_primary: boolean
  receives_alerts: boolean
  notes?: string | null
  is_active: boolean
  created_at?: string | null
  updated_at?: string | null
}

export interface ClientCustomField {
  id: number
  label: string
  type: 'TEXT' | 'SECRET'
  value: string | null
  has_value: boolean
}

export interface ClientCredentialSummary {
  status: string
  valid_to?: string | null
  expires_alert_30: boolean
  expires_alert_7: boolean
  expires_alert_1: boolean
}

export interface ClientListStats {
  total: number
  active: number
  without_credential: number
  credential_expiring_30d: number
  credential_expired: number
}

/** Resumo de matriz/filial vinculada (cada uma tem cadastro próprio). */
export interface LinkedClientSummary {
  id: number
  legal_name: string
  display_name?: string | null
  name: string
  root_cnpj: string
  matrix_client_id?: number | null
  cnpj?: string | null
  trade_name?: string | null
  is_matrix: boolean
  is_active: boolean
  credential_summary?: { status: string, valid_to?: string | null } | null
}

export interface Client {
  id: number
  office_id?: number
  /** Preferencial para UI (display_name ou legal_name) — backend mantém compat */
  name: string
  legal_name: string
  display_name?: string | null
  root_cnpj: string
  /** Matriz vinculada (se este cliente for filial) */
  matrix_client_id?: number | null
  /** CNPJ completo do único estabelecimento (1 cliente = 1 CNPJ) */
  cnpj?: string | null
  trade_name?: string | null
  legal_nature_code?: string | null
  legal_nature_name?: string | null
  company_size_code?: string | null
  company_size_name?: string | null
  /** Regime tributário (ex.: Lucro Presumido, Simples Nacional) */
  tax_regime?: string | null
  notes?: string | null
  is_active: boolean
  inactive_reason?: string | null
  registration_source?: RegistrationSource | string | null
  registration_refreshed_at?: string | null
  establishments_count?: number
  /** Sempre 0 ou 1 no produto; mantido para captura ADN (cursor por CNPJ). */
  establishments?: Establishment[]
  /** Matriz (quando este registro é filial) */
  matrix?: LinkedClientSummary | null
  /** Filiais vinculadas a esta matriz (cada uma com cadastro próprio) */
  branches?: LinkedClientSummary[]
  contacts?: ClientContact[]
  custom_fields?: ClientCustomField[]
  credential_summary?: ClientCredentialSummary | null
  created_at?: string | null
  updated_at?: string | null
}

export interface CnpjLookupClient {
  root_cnpj: string
  legal_name: string
  legal_nature_code?: string | null
  legal_nature_name?: string | null
  company_size_code?: string | null
  company_size_name?: string | null
}

export interface CnpjLookupEstablishment {
  cnpj: string
  trade_name?: string | null
  is_matrix: boolean
  registration_status: RegistrationStatus | string
  registration_status_at?: string | null
  registration_status_reason?: string | null
  activity_started_at?: string | null
  main_cnae_code?: string | null
  main_cnae_name?: string | null
  address?: AddressPayload | null
  public_email?: string | null
  public_phone?: string | null
  source_updated_at?: string | null
}

export interface CnpjLookupResult {
  source: string
  source_updated_at?: string | null
  client: CnpjLookupClient
  establishment: CnpjLookupEstablishment
}

export interface CreateClientPayload {
  legal_name: string
  cnpj: string
  display_name?: string | null
  notes?: string | null
  is_active?: boolean
  inactive_reason?: string | null
  /** ID da matriz (cadastro de filial com vínculo) */
  matrix_client_id?: number | null
  trade_name?: string | null
  is_matrix?: boolean
  establishment_is_active?: boolean
  registration_status?: string | null
  registration_status_at?: string | null
  registration_status_reason?: string | null
  activity_started_at?: string | null
  main_cnae_code?: string | null
  main_cnae_name?: string | null
  public_email?: string | null
  public_phone?: string | null
  capture_enabled?: boolean
  legal_nature_code?: string | null
  legal_nature_name?: string | null
  company_size_code?: string | null
  company_size_name?: string | null
  tax_regime?: string | null
  address?: AddressPayload | null
  initial_contact?: {
    name: string
    role?: string | null
    email?: string | null
    phone?: string | null
    is_whatsapp?: boolean
    is_primary?: boolean
    receives_alerts?: boolean
    notes?: string | null
  } | null
  custom_fields?: Array<{
    label: string
    type: 'TEXT' | 'SECRET'
    value?: string | null
  }>
}

export interface CreateClientResponse {
  client: Client
  establishment: Establishment
  contact: ClientContact | null
  custom_fields: ClientCustomField[]
}

export interface ClientCredential {
  id: number
  client_id: number
  status: string
  subject_name: string
  holder_cnpj: string
  fingerprint_sha256: string
  valid_from?: string | null
  valid_to?: string | null
  activated_at?: string | null
  expires_alert_30: boolean
  expires_alert_7: boolean
  expires_alert_1: boolean
}

export interface DfeDocumentMetadata {
  id: number
  sha256: string
  document_type: string
  schema_version?: string | null
  access_key?: string | null
  byte_size: number
  parse_status: string
  parse_alert?: string | null
}

export interface NfseNote {
  id: number
  access_key: string
  issuer_cnpj?: string | null
  taker_cnpj?: string | null
  intermediary_cnpj?: string | null
  fiscal_role?: FiscalRole | null
  competence?: string | null
  issued_at?: string | null
  service_amount?: string | null
  status: string
  document?: DfeDocumentMetadata
}

export interface NfseEvent {
  id: number
  access_key: string
  event_type?: string | null
  event_at?: string | null
  status?: string | null
}

export interface NoteDetail {
  note: NfseNote
  events: NfseEvent[]
  document: DfeDocumentMetadata | null
}

export interface ExportFilters {
  access_key?: string
  issuer_cnpj?: string
  taker_cnpj?: string
  competence?: string
  status?: string
  issued_from?: string
  issued_to?: string
  fiscal_role?: FiscalRole | ''
}

export interface ExportJob {
  id: number
  status: 'PENDING' | 'PROCESSING' | 'READY' | 'FAILED' | 'EXPIRED' | string
  filters: ExportFilters
  include_events: boolean
  files_count: number
  byte_size?: number | null
  expires_at?: string | null
  completed_at?: string | null
  created_at?: string | null
  error_message?: string | null
}

export interface SyncRun {
  id: number
  sync_cursor_id?: number
  status: 'RUNNING' | 'COMPLETED' | 'FAILED' | string
  trigger: 'MANUAL' | 'SCHEDULED' | string
  pages_processed: number
  documents_persisted: number
  from_nsu: number
  to_nsu: number
  error_message?: string | null
  started_at?: string | null
  finished_at?: string | null
  created_at?: string | null
}

export type InboxSeverity = 'critical' | 'high' | 'medium' | 'low'
export type InboxItemType =
  | 'cursor_blocked'
  | 'cursor_error'
  | 'sync_failed_recent'
  | 'credential_expired'
  | 'credential_expiring_7d'
  | 'credential_expiring_30d'
  | 'backup_stale'
  | 'backup_never'

export interface InboxItemAction {
  type: 'open' | 'trigger_sync' | string
  label: string
  establishment_id?: number
}

export interface InboxItemLinks {
  client?: string
  sync?: string
  credential?: string
}

export interface InboxItem {
  id: string
  type: InboxItemType | string
  severity: InboxSeverity | string
  title: string
  body: string
  reasons: string[]
  client_id?: number | null
  establishment_id?: number | null
  occurred_at: string
  links: InboxItemLinks
  actions: InboxItemAction[]
}

export interface InboxMeta {
  next_cursor: string | null
  total_estimate?: number
  generated_at: string
}

export interface BackupStatus {
  last_success_at?: string | null
  last_full_success_at?: string | null
  last_kind?: string | null
  last_status?: string | null
  last_restore_drill_at?: string | null
  last_restore_drill_status?: string | null
  stale: boolean
  never: boolean
}

export interface OperationsSummary {
  clients: number
  establishments: number
  notes: number
  exports_ready: number
  exports_pending: number
  sync_due: number
  sync_blocked: number
  sync_failures_24h: number
  credentials_expiring_30d: number
  inbox_critical?: number
  inbox_high?: number
  inbox_total?: number
  backup?: BackupStatus
  generated_at: string
}

export interface CursorMeta {
  next_cursor: string | null
}

export interface PageMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  stats?: ClientListStats
}

export interface AppNotification {
  id: string
  title: string
  body: string
  date: string
  unread?: boolean
  to?: string
  color?: 'error' | 'warning' | 'info'
}

export interface TwoFactorQrCode {
  svg: string
}

export interface LoginResponse {
  two_factor?: boolean
}
