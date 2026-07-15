export type OfficeRole = 'ADMIN' | 'OPERATOR' | 'VIEWER'
export type FiscalRole = 'ISSUER' | 'TAKER' | 'INTERMEDIARY'

/** Direção fiscal no catálogo: entrada / saída. */
export type DocumentDirection = 'IN' | 'OUT' | 'UNKNOWN'
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

/** Resumo de captura ADN na listagem (sem material de certificado). */
export interface ClientCaptureSummary {
  enabled: boolean
  /** ON | OFF | PARTIAL | NONE */
  status: string
  establishments_total: number
  establishments_enabled: number
}

/** Pior status de cursor entre estabelecimentos do cliente. */
export interface ClientSyncSummary {
  /** IDLE | RUNNING | WAITING | BLOCKED | ERROR | NONE */
  status: string
  last_success_at?: string | null
  has_cursor: boolean
}

export interface ClientListStats {
  total: number
  active: number
  /** Clientes com credencial ACTIVE. */
  with_credential?: number
  without_credential: number
  credential_expiring_30d: number
  credential_expired: number
  /** Clientes com cursor BLOCKED/ERROR. */
  capture_problem?: number
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
  capture_summary?: ClientCaptureSummary | null
  sync_summary?: ClientSyncSummary | null
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

/** Tipos DF-e pertencentes ao catálogo escritural. */
export type DocumentKind = 'NFSE' | 'NFE' | 'NFCE' | 'CTE'

export type DocumentSource = 'ADN' | 'SEFAZ' | string

/** Item do catálogo unificado (hoje projeção NFS-e + kind). */
export interface FiscalDocument {
  id: number
  /** Tipo DF-e (NFSE, NFE, CTE, …). */
  kind?: DocumentKind | string | null
  kind_label?: string | null
  /** Fonte de captura (ADN, SEFAZ, …). */
  source?: DocumentSource | null
  /** Captura habilitada no backend para o tipo desta linha. */
  capture_available?: boolean
  access_key: string
  /** Número do documento (nNFSe / nNF / nCT). */
  number?: string | null
  issuer_cnpj?: string | null
  issuer_name?: string | null
  taker_cnpj?: string | null
  taker_name?: string | null
  intermediary_cnpj?: string | null
  intermediary_name?: string | null
  fiscal_role?: FiscalRole | null
  /** Entrada (IN) / Saída (OUT) / Indefinida. */
  direction?: DocumentDirection | null
  direction_label?: string | null
  competence?: string | null
  issued_at?: string | null
  service_amount?: string | null
  issue_location?: string | null
  service_location?: string | null
  status: string
  /** Label operacional (Autorizada / Cancelada / Em revisão). */
  status_label?: string | null
  /** cStat oficial do XML (ex.: 100). */
  official_status_code?: string | null
  /** Descrição oficial curta (cStat ou nuance granular). */
  official_status_label?: string | null
  /** NF-e DistDFe: true se só resNFe. */
  is_summary?: boolean | null
  /** true se procNFe (full) disponível no vault. */
  has_full_xml?: boolean | null
  /** FULL | SUMMARY_ONLY */
  xml_completeness?: string | null
  manifestation_status?: string | null
  /** COMMERCIAL | TECHNICAL (sonda/autorização inesperada). */
  purpose?: 'COMMERCIAL' | 'TECHNICAL' | string | null
  /** Proveniência (IMPORT, MA_OFFICIAL_PACKAGE, …). */
  acquisition_source?: string | null
  document?: DfeDocumentMetadata
}

/** @deprecated Preferir FiscalDocument — alias de compat. */
export type NfseNote = FiscalDocument

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
  /** Seleção em lote (teto no backend, ex. 100). */
  access_keys?: string[]
  issuer_cnpj?: string
  taker_cnpj?: string
  competence?: string
  status?: string
  issued_from?: string
  issued_to?: string
  fiscal_role?: FiscalRole | ''
  direction?: Exclude<DocumentDirection, 'UNKNOWN'> | ''
  client_id?: number
  establishment_id?: number
}

/** Agregação por cliente do escritório (aba Clientes). */
export interface NoteClientAggregate {
  client_id: number
  legal_name: string
  display_name?: string | null
  name: string
  root_cnpj: string
  cnpj?: string | null
  notes_count: number
  service_amount_sum?: string | null
  cancelled_count?: number
  review_count?: number
  last_issued_at?: string | null
}

/** Contagens reais de triagem (chips) no escopo dos filtros. */
export interface NotesInsights {
  total: number
  active: number
  cancelled: number
  review: number
  missing_party_name: number
  competence_current: number
  competence_current_label: string
  superseded?: number
  substitute?: number
  /** Contagens brutas por kind no escritório (quando a API expõe). */
  by_kind?: Partial<Record<DocumentKind | string, number>> | null
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

/** Captura de saídas MA (posição nNF). */
export interface OutboundCaptureProfile {
  id: number
  client_id: number
  establishment_id: number
  uf: string
  environment: string
  model: string
  mode: string
  status: string
  consent_recorded: boolean
  mandate_reference?: string | null
  allowlisted: boolean
  kill_switch: boolean
  csc: { configured: boolean, csc_id?: string | null, configured_at?: string | null }
  activated_at?: string | null
}

export interface OutboundSeriesCursor {
  id: number
  profile_id: number
  establishment_id: number
  environment: string
  model: string
  series: number
  seed_nnf: number
  discovery_position: number
  position_kind: 'nNF'
  highest_confirmed_nnf?: number | null
  status: string
  tp_emis?: string
  seed_access_key?: string | null
  seed_issued_at?: string | null
  next_run_at?: string | null
  last_run_at?: string | null
  series_closed_for_mutation?: boolean
}

export interface OutboundNumberState {
  id: number
  series: number
  nnf: number
  status: string
  candidate_access_key?: string | null
  discovered_access_key?: string | null
  last_cstat?: string | null
  attempts: number
  next_attempt_at?: string | null
  key_discovered_at?: string | null
  xml_captured_at?: string | null
  has_full_xml: boolean
}

export interface OutboundCaptureRun {
  id: number
  profile_id: number
  series_cursor_id?: number | null
  run_type: string
  status: string
  position_kind: 'nNF'
  nnf_start?: number | null
  nnf_end?: number | null
  numbers_consulted: number
  keys_discovered: number
  xml_persisted: number
  gaps_open: number
  attempts_total: number
  result_summary?: string | null
  started_at?: string | null
  finished_at?: string | null
  triggered_by?: string
}

export interface OutboundKillSwitchStatus {
  global_active: boolean
  config_flag: boolean
  enabled: boolean
  protocol_query_enabled: boolean
  m2m_status: string
  mutating_probe_enabled: boolean
}

export type InboxItemType
  = | 'cursor_blocked'
    | 'cursor_error'
    | 'sync_failed_recent'
    | 'credential_expired'
    | 'credential_expiring_7d'
    | 'credential_expiring_30d'
    | 'backup_stale'
    | 'backup_never'
    | 'outbound_gap_exhausted'
    | 'outbound_562_no_key'
    | 'outbound_656'
    | 'outbound_retrieval_expired'
    | 'outbound_xml_divergent'
    | 'outbound_authorized_unexpected'
    | 'outbound_cancel_failed'
    | 'svrs_nfce_a1'
    | 'svrs_nfce_auth'
    | 'svrs_nfce_rate_limit'
    | 'svrs_nfce_contract_changed'
    | 'svrs_nfce_xml_signature'
    | 'svrs_nfce_divergent'
    | 'svrs_nfce_breaker'
    | 'svrs_nfce_exhausted'

/** Recovery SVRS NFC-e (DTOs sanitizados — sem HTML/XML/PFX). */
export interface SvrsNfceChannelSummary {
  retrieval_enabled: boolean
  auto_queue_enabled: boolean
  pilot_allowlist_only: boolean
  kill_switch: { active: boolean, source?: string | null }
  breaker_global: { state: string, open_until?: number | null, failures?: number }
  backlog: number
  oldest_pending_at?: string | null
  parser_version?: string
  host?: string
}

export interface SvrsNfceRecovery {
  id: number
  profile_id: number
  establishment_id: number
  environment: string
  model: string
  origin?: string
  access_key_masked?: string | null
  recovery_status?: string | null
  failure_reason?: string | null
  failure_label?: string | null
  attempt_count?: number
  next_attempt_at?: string | null
  correlation_id?: string | null
  sha256?: string | null
}

export interface SvrsNfceProfileSummary {
  profile_id: number
  model: string
  eligible_model: boolean
  allowlisted: boolean
  flags: {
    retrieval_enabled: boolean
    auto_queue_enabled: boolean
    pilot_allowlist_only: boolean
    kill_switch: boolean
  }
  breaker_root: { state: string, open_until?: number | null, failures?: number }
  breaker_global: { state: string, open_until?: number | null, failures?: number }
  recent: SvrsNfceRecovery[]
  last_captured?: SvrsNfceRecovery | null
}

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
  svrs_nfce?: {
    retrieval_enabled: boolean
    auto_queue_enabled: boolean
    kill_switch: boolean
    breaker_global: string
    backlog: number
  }
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
  color?: 'error' | 'warning' | 'info' | 'neutral'
}

export interface TwoFactorQrCode {
  svg: string
}

export interface LoginResponse {
  two_factor?: boolean
}
