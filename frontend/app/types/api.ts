export type OfficeRole = 'ADMIN' | 'OPERATOR' | 'VIEWER'
export type FiscalRole = 'ISSUER' | 'TAKER' | 'INTERMEDIARY'

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
  requires_two_factor_setup: boolean
  office: Office | null
  role: OfficeRole | null
}

export interface MeResponse {
  data: MeUser
}

export interface Establishment {
  id: number
  client_id: number
  cnpj: string
  trade_name?: string | null
  is_matrix: boolean
  is_active: boolean
  created_at?: string | null
  updated_at?: string | null
}

export interface Client {
  id: number
  name: string
  root_cnpj: string
  notes?: string | null
  is_active: boolean
  establishments_count?: number
  establishments?: Establishment[]
  created_at?: string | null
  updated_at?: string | null
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
