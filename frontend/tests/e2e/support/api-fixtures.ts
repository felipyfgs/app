import type { Page, Route } from '@playwright/test'
import type {
  Client,
  ClientCredential,
  ExportJob,
  MeUser,
  NfseNote,
  OfficeRole,
  OperationsSummary,
  SyncRun
} from '../../../app/types/api'
import { tryFulfillMonitoringApi } from './monitoring-fixtures'

const FIXED_NOW = '2026-07-14T15:00:00.000Z'
export const NOTE_ACCESS_KEY = 'NFS20260714000000000000000000000000000000000001'
export const SECOND_NOTE_ACCESS_KEY = 'NFS20260714000000000000000000000000000000000002'
export const NFE_ACCESS_KEY = '35240111222333000181550010000000011000000010'
/** Chave sintética de CT-e 57 (44 chars) para qualidade redigida / catálogo. */
export const CTE_ACCESS_KEY = '35260712ABC345678900570010000000111234567890'
/** Mensagem de mailbox demo (list/detail). */
export const MAILBOX_MESSAGE_ID = 9001
export const MAILBOX_SECOND_MESSAGE_ID = 9002
/** Nomes estáveis para assert de troca de office (não misturar tenants). */
export const FISCAL_OFFICE_A_NAME = 'Escritório Contábil Modelo'
export const FISCAL_OFFICE_B_NAME = 'Escritório Sentinela'
export const FISCAL_CLIENT_OFFICE_A = 'Cliente Demonstração Segura'
export const FISCAL_CLIENT_OFFICE_B = 'Cliente Tenant Sentinela'
export type ListScenario = 'ready' | 'empty' | 'error' | 'slow'

/** Reexports dos builders fiscais (E2E + contract tests). */
export {
  buildFiscalModuleClientRow,
  buildFiscalModuleClients,
  buildFiscalModuleOverview,
  buildFiscalCategories,
  buildFiscalFindings,
  buildFiscalPendingItems,
  buildFiscalRuns,
  buildFiscalSnapshots,
  buildGuides,
  buildMailboxMessages,
  assertClientRowEnvelope,
  assertClientsPageEnvelope,
  assertFiscalPayloadSanitized,
  assertOverviewEnvelope,
  assertListEnvelope,
  fiscalModuleClientsResponse,
  fiscalModuleOverviewResponse,
  FISCAL_FIXTURE_CNPJ_MASKED,
  FISCAL_FIXTURE_NOW,
  FISCAL_MAILBOX_MESSAGE_ID
} from './fiscal-fixtures'

const clients: Client[] = [{
  id: 1,
  name: 'Cliente Demonstração Segura',
  legal_name: 'Cliente Demonstração Segura',
  display_name: null,
  root_cnpj: '12ABC345',
  notes: 'Fixture sintética para regressão visual.',
  is_active: true,
  registration_source: 'LEGACY',
  establishments_count: 2,
  credential_summary: {
    status: 'ACTIVE',
    valid_to: '2027-01-01T00:00:00.000Z',
    expires_alert_30: false,
    expires_alert_7: false,
    expires_alert_1: false
  },
  capture_summary: {
    enabled: true,
    status: 'PARTIAL',
    establishments_total: 2,
    establishments_enabled: 1
  },
  sync_summary: {
    status: 'IDLE',
    last_success_at: FIXED_NOW,
    has_cursor: true
  },
  establishments: [{
    id: 11,
    client_id: 1,
    cnpj: '12ABC345678900',
    trade_name: 'Matriz MA',
    is_matrix: true,
    is_active: true,
    capture_enabled: true,
    registration_status: 'UNKNOWN',
    registration_source: 'LEGACY',
    address: {
      state: 'MA',
      city: 'Sao Luis',
      country: 'BR'
    },
    capture_eligibility: {
      eligible: true,
      reasons: [],
      reasons_codes: [],
      channels: {
        NFSE_ADN: { label: 'NFS-e ADN', enabled: true, eligible: true },
        NFE_DISTDFE: { label: 'NF-e DistDFe', enabled: false, eligible: false },
        CTE_DISTDFE: { label: 'CT-e DistDFe', enabled: false, eligible: false },
        MA_OUTBOUND: { label: 'Saídas MA (NF-e/NFC-e)', enabled: false, eligible: false }
      }
    }
  }, {
    id: 12,
    client_id: 1,
    cnpj: '12ABC345678901',
    trade_name: 'Filial',
    is_matrix: false,
    is_active: true,
    capture_enabled: false,
    registration_status: 'SUSPENDED',
    registration_source: 'MANUAL',
    address: {
      state: 'SP',
      city: 'São Paulo',
      country: 'BR'
    },
    capture_eligibility: {
      eligible: false,
      reasons: ['Captura desabilitada para este estabelecimento.'],
      reasons_codes: ['capture_disabled'],
      channels: {
        NFSE_ADN: { label: 'NFS-e ADN', enabled: true, eligible: false },
        NFE_DISTDFE: { label: 'NF-e DistDFe', enabled: false, eligible: false },
        CTE_DISTDFE: { label: 'CT-e DistDFe', enabled: false, eligible: false },
        MA_OUTBOUND: { label: 'Saídas MA (NF-e/NFC-e)', enabled: false, eligible: false }
      }
    }
  }],
  contacts: [],
  created_at: FIXED_NOW,
  updated_at: FIXED_NOW
}]

const credential: ClientCredential = {
  id: 21,
  client_id: 1,
  status: 'ACTIVE',
  subject_name: 'Certificado sintético',
  holder_cnpj: '12ABC345678900',
  fingerprint_sha256: 'a'.repeat(64),
  valid_from: '2026-01-01T00:00:00.000Z',
  valid_to: '2027-01-01T00:00:00.000Z',
  activated_at: FIXED_NOW,
  expires_alert_30: false,
  expires_alert_7: false,
  expires_alert_1: false
}

const notes: NfseNote[] = [{
  id: 31,
  kind: 'NFSE',
  kind_label: 'NFS-e',
  source: 'ADN',
  access_key: NOTE_ACCESS_KEY,
  number: '1001',
  issuer_cnpj: '12ABC345678900',
  issuer_name: 'Emitente Demo Ltda',
  taker_cnpj: '98XYZ765432100',
  taker_name: 'Tomador Exemplo SA',
  fiscal_role: 'ISSUER',
  competence: '2026-07',
  issued_at: FIXED_NOW,
  service_amount: '1250.00',
  issue_location: 'São Paulo/SP',
  service_location: 'Campinas/SP',
  status: 'ACTIVE',
  official_status_code: '100',
  document: {
    id: 41,
    sha256: 'b'.repeat(64),
    document_type: 'NFSE',
    schema_version: '1.00',
    access_key: NOTE_ACCESS_KEY,
    byte_size: 2048,
    parse_status: 'PARSED'
  }
}, {
  id: 32,
  kind: 'NFSE',
  kind_label: 'NFS-e',
  source: 'ADN',
  access_key: SECOND_NOTE_ACCESS_KEY,
  number: '1002',
  issuer_cnpj: '12ABC345678901',
  issuer_name: 'Outro Emitente ME',
  taker_cnpj: '98XYZ765432100',
  taker_name: 'Tomador Exemplo SA',
  fiscal_role: 'TAKER',
  competence: '2026-06',
  issued_at: '2026-06-14T15:00:00.000Z',
  service_amount: '850.00',
  issue_location: 'Rio de Janeiro/RJ',
  status: 'CANCELLED',
  official_status_code: '101',
  document: {
    id: 42,
    sha256: 'c'.repeat(64),
    document_type: 'NFSE',
    schema_version: '1.00',
    access_key: SECOND_NOTE_ACCESS_KEY,
    byte_size: 1536,
    parse_status: 'PARSED'
  }
}, {
  id: 33,
  kind: 'NFE',
  kind_label: 'NF-e',
  source: 'SEFAZ',
  access_key: NFE_ACCESS_KEY,
  number: '1',
  issuer_cnpj: '11222333000181',
  issuer_name: 'Fornecedor SEFAZ Demo',
  taker_cnpj: '12ABC345678900',
  taker_name: 'Cliente Demonstração Segura',
  fiscal_role: 'TAKER',
  direction: 'IN',
  direction_label: 'Entrada',
  competence: '2026-07',
  issued_at: FIXED_NOW,
  service_amount: '500.00',
  status: 'SUMMARY',
  status_label: 'Somente resumo',
  is_summary: true,
  has_full_xml: false,
  xml_completeness: 'SUMMARY_ONLY',
  manifestation_status: 'PENDING_MANIFESTATION',
  document: {
    id: 43,
    sha256: 'd'.repeat(64),
    document_type: 'NFE',
    schema_version: 'resNFe_v1.01.xsd',
    access_key: NFE_ACCESS_KEY,
    byte_size: 1024,
    parse_status: 'OK'
  }
}, {
  id: 34,
  kind: 'CTE',
  kind_label: 'CT-e',
  source: 'SEFAZ',
  access_key: CTE_ACCESS_KEY,
  number: '57',
  issuer_cnpj: '11222333000181',
  issuer_name: 'Transportadora Demo CT-e',
  taker_cnpj: '12ABC345678900',
  taker_name: 'Cliente Demonstração Segura',
  fiscal_role: 'RECIPIENT',
  direction: 'IN',
  direction_label: 'Entrada',
  competence: '2026-07',
  issued_at: FIXED_NOW,
  service_amount: '320.50',
  status: 'AUTHORIZED',
  status_label: 'Autorizada',
  acquisition_source: 'CTE_AUTXML_DIST_NSU',
  acquisition_source_label: 'DistDFe autXML CT-e (NSU)',
  artifact_quality: 'AUTXML_REDACTED',
  artifact_quality_label: 'Oficial redigido',
  signature_result: 'NOT_VERIFIABLE_OFFICIAL_REDACTION',
  signature_result_label: 'Redação oficial não verificável',
  is_autxml_redacted: true,
  autxml_redacted_notice: 'Visão oficial redigida: referências 999… preservadas. Solicite o original ao emissor.',
  coverage_status: 'CAPTURED_AUTXML_REDACTED',
  coverage_status_label: 'Capturado (autXML redigido)',
  capture_available: true,
  document: {
    id: 44,
    sha256: 'e'.repeat(64),
    document_type: 'CTE',
    schema_version: 'cteProc_v4.00.xsd',
    access_key: CTE_ACCESS_KEY,
    byte_size: 4096,
    parse_status: 'OK'
  }
}]

const syncRuns: SyncRun[] = [{
  id: 51,
  sync_cursor_id: 61,
  status: 'COMPLETED',
  trigger: 'SCHEDULED',
  pages_processed: 2,
  documents_persisted: 4,
  from_nsu: 100,
  to_nsu: 104,
  started_at: '2026-07-14T14:00:00.000Z',
  finished_at: '2026-07-14T14:02:00.000Z',
  created_at: '2026-07-14T14:00:00.000Z'
}, {
  id: 52,
  sync_cursor_id: 62,
  status: 'FAILED',
  trigger: 'MANUAL',
  pages_processed: 0,
  documents_persisted: 0,
  from_nsu: 105,
  to_nsu: 105,
  error_message: 'Cursor bloqueado após cinco falhas consecutivas de decodificação.',
  started_at: '2026-07-14T13:00:00.000Z',
  finished_at: '2026-07-14T13:01:00.000Z',
  created_at: '2026-07-14T13:00:00.000Z'
}, {
  id: 53,
  sync_cursor_id: 63,
  status: 'COMPLETED',
  trigger: 'SCHEDULED',
  pages_processed: 1,
  documents_persisted: 3,
  from_nsu: 0,
  to_nsu: 96,
  started_at: '2026-07-14T14:10:00.000Z',
  finished_at: '2026-07-14T14:12:00.000Z',
  created_at: '2026-07-14T14:10:00.000Z'
}]

const exports: ExportJob[] = [{
  id: 71,
  status: 'READY',
  filters: { competence: '2026-07' },
  include_events: true,
  files_count: 4,
  byte_size: 4096,
  completed_at: '2026-07-14T14:30:00.000Z',
  expires_at: '2026-07-21T14:30:00.000Z',
  created_at: '2026-07-14T14:20:00.000Z'
}, {
  id: 72,
  status: 'PROCESSING',
  filters: {},
  include_events: false,
  files_count: 0,
  created_at: '2026-07-14T14:40:00.000Z'
}, {
  id: 73,
  status: 'FAILED',
  filters: { fiscal_role: 'TAKER' },
  include_events: false,
  files_count: 0,
  error_message: 'Falha sanitizada ao montar o pacote.',
  created_at: '2026-07-14T13:40:00.000Z'
}, {
  id: 74,
  status: 'EXPIRED',
  filters: { competence: '2026-06' },
  include_events: false,
  files_count: 2,
  byte_size: 2048,
  completed_at: '2026-06-14T14:30:00.000Z',
  expires_at: '2026-06-15T14:30:00.000Z',
  created_at: '2026-06-14T14:20:00.000Z'
}]

const summary: OperationsSummary = {
  clients: 12,
  establishments: 18,
  notes: 324,
  exports_ready: 3,
  exports_pending: 1,
  sync_due: 2,
  sync_blocked: 1,
  sync_failures_24h: 2,
  credentials_expiring_30d: 1,
  inbox_critical: 1,
  inbox_high: 2,
  inbox_total: 4,
  backup: {
    last_success_at: '2026-06-14T12:00:00.000Z',
    last_status: 'SUCCESS',
    last_restore_drill_at: '2026-06-14T13:00:00.000Z',
    last_restore_drill_status: 'SUCCESS',
    stale: false,
    never: false
  },
  generated_at: FIXED_NOW
}

function officeMeta(officeId: number) {
  if (officeId === 2) {
    return { id: 2, name: FISCAL_OFFICE_B_NAME, slug: 'escritorio-sentinela' }
  }
  return { id: 1, name: FISCAL_OFFICE_A_NAME, slug: 'escritorio-modelo' }
}

function identity(role: OfficeRole, officeId = 1): MeUser {
  return {
    id: role === 'ADMIN' ? 1 : role === 'OPERATOR' ? 2 : 3,
    name: role === 'ADMIN' ? 'Ana Administradora' : role === 'OPERATOR' ? 'Olívia Operadora' : 'Vítor Visualizador',
    email: `${role.toLowerCase()}@fixture.invalid`,
    two_factor_confirmed: role === 'ADMIN',
    two_factor_required: role === 'ADMIN',
    requires_two_factor_setup: false,
    office: officeMeta(officeId),
    role
  }
}

async function fulfill(route: Route, body: unknown, status = 200) {
  await route.fulfill({
    status,
    contentType: 'application/json; charset=utf-8',
    body: JSON.stringify(body)
  })
}

export async function installApiFixtures(
  page: Page,
  role: OfficeRole = 'ADMIN',
  colorMode: 'light' | 'dark' = 'light',
  listScenario: ListScenario = 'ready'
) {
  /** Office ativo da fixture — atualizado em POST /tenants/switch (sobrevive a location.assign). */
  let activeOfficeId = 1

  await page.addInitScript(({ now, mode }) => {
    const NativeDate = Date
    const fixed = new NativeDate(now).valueOf()

    class FixedDate extends NativeDate {
      constructor(...args: ConstructorParameters<typeof Date>) {
        super(...(args.length ? args : [fixed]))
      }

      static now() {
        return fixed
      }
    }

    Object.defineProperty(window, 'Date', { value: FixedDate })
    localStorage.setItem('nuxt-color-mode', mode)
  }, { now: FIXED_NOW, mode: colorMode })

  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const pathname = new URL(request.url()).pathname
    const method = request.method()

    if (pathname.endsWith('/api/v1/me')) {
      return fulfill(route, { data: identity(role, activeOfficeId) })
    }
    if (pathname.endsWith('/api/v1/tenants/memberships') && method === 'GET') {
      return fulfill(route, {
        data: {
          current_office_id: activeOfficeId,
          memberships: [
            {
              office_id: 1,
              office_name: FISCAL_OFFICE_A_NAME,
              office_slug: 'escritorio-modelo',
              role,
              is_current: activeOfficeId === 1
            },
            {
              office_id: 2,
              office_name: FISCAL_OFFICE_B_NAME,
              office_slug: 'escritorio-sentinela',
              role,
              is_current: activeOfficeId === 2
            }
          ]
        }
      })
    }
    if (pathname.endsWith('/api/v1/tenants/switch') && method === 'POST') {
      const body = request.postDataJSON() as { office_id?: number }
      const nextId = Number(body?.office_id)
      if (nextId !== 1 && nextId !== 2) {
        return fulfill(route, { message: 'Escritório não autorizado.' }, 403)
      }
      activeOfficeId = nextId
      return fulfill(route, {
        data: {
          office: officeMeta(activeOfficeId),
          role
        }
      })
    }
    if (pathname.endsWith('/api/v1/operations/summary')) {
      return fulfill(route, { data: summary })
    }
    if (pathname.endsWith('/api/v1/operations/inbox')) {
      return fulfill(route, {
        data: [{
          id: 'inbox-fixture-blocked',
          type: 'cursor_blocked',
          severity: 'critical',
          title: 'Cursor bloqueado: Cliente Fixture',
          body: 'Cursor bloqueado. Intervenção necessária antes de retomar a captura.',
          reasons: ['cursor_blocked'],
          client_id: 1,
          establishment_id: 1,
          occurred_at: FIXED_NOW,
          links: {
            client: '/clients/1',
            sync: '/clients/1/sincronizacao',
            credential: '/clients/1/certificado'
          },
          actions: [{ type: 'open', label: 'Abrir' }]
        }, {
          id: 'inbox-fixture-outbound-gap',
          type: 'outbound_gap_exhausted',
          severity: 'high',
          title: 'Lacuna esgotada (nNF 42): Cliente Fixture',
          body: 'Série 1 esgotou tentativas. Posição nNF — não é NSU.',
          reasons: ['outbound_gap_exhausted'],
          client_id: 1,
          establishment_id: 11,
          occurred_at: FIXED_NOW,
          links: { client: '/clients/1', sync: '/clients/1/saidas' },
          actions: [{ type: 'open', label: 'Abrir' }]
        }, {
          id: 'inbox-fixture-outbound-incident',
          type: 'outbound_authorized_unexpected',
          severity: 'critical',
          title: 'Incidente fiscal MA: Cliente Fixture',
          body: 'Autorização inesperada — canal bloqueado. Documento preservado.',
          reasons: ['outbound_authorized_unexpected'],
          client_id: 1,
          establishment_id: 11,
          occurred_at: FIXED_NOW,
          links: { client: '/clients/1' },
          actions: [{ type: 'open', label: 'Abrir' }]
        }],
        meta: { next_cursor: null, total_estimate: 3, generated_at: FIXED_NOW }
      })
    }

    // --- CT-e: onboarding, saúde, cobertura e pendências (sempre sanitizados) ---
    if (pathname.endsWith('/api/v1/cte/onboarding') && method === 'GET') {
      const cnpj = activeOfficeId === 1 ? '12ABC345678900' : '98XYZ765432100'
      return fulfill(route, {
        data: {
          office_cnpj: cnpj,
          identity: {
            id: activeOfficeId,
            cnpj,
            root_cnpj: cnpj.slice(0, 8),
            status: 'ACTIVE',
            legal_name: activeOfficeId === 1 ? FISCAL_OFFICE_A_NAME : FISCAL_OFFICE_B_NAME,
            activated_at: FIXED_NOW,
            deactivated_at: null
          },
          credential: {
            id: activeOfficeId,
            office_fiscal_identity_id: activeOfficeId,
            purpose: 'SEFAZ_DISTRIBUTION',
            status: 'ACTIVE',
            subject_name: 'A1 operacional (metadados)',
            holder_cnpj: cnpj,
            fingerprint_sha256: 'f'.repeat(64),
            valid_from: FIXED_NOW,
            valid_to: '2027-07-14T15:00:00.000Z',
            activated_at: FIXED_NOW,
            last_used_at: FIXED_NOW,
            expires_alert_30: false,
            expires_alert_7: false,
            expires_alert_1: false
          },
          enabled: true,
          instructions: {
            include_before_authorization: true,
            not_retroactive: true,
            message: 'Inclua o CNPJ completo do escritório em autXML antes de autorizar o CT-e.',
            issuer_fallback: 'Use XML/ZIP ou envio do emissor para documentos anteriores.'
          }
        }
      })
    }
    if (pathname.endsWith('/api/v1/cte/health') && method === 'GET') {
      return fulfill(route, {
        data: {
          channels: {
            CTE_DISTDFE: [{
              id: activeOfficeId * 10 + 1,
              channel: 'CTE_DISTDFE',
              establishment_id: activeOfficeId * 10 + 1,
              client_id: activeOfficeId,
              client_name: activeOfficeId === 1 ? FISCAL_CLIENT_OFFICE_A : FISCAL_CLIENT_OFFICE_B,
              status: 'IDLE',
              last_nsu: 42,
              max_nsu_seen: 42,
              last_cstat: '138',
              retry_allowed: true,
              circuit_open: false
            }],
            CTE_AUTXML_DISTDFE: [{
              id: activeOfficeId * 10 + 2,
              channel: 'CTE_AUTXML_DISTDFE',
              environment: 'production',
              status: 'IDLE',
              last_nsu: 11,
              max_nsu_seen: 11,
              last_cstat: '137',
              // Futuro estável para o card exibir quiet (cStat 137) em qualquer data de CI.
              next_sync_at: '2099-01-01T00:00:00.000Z',
              circuit_open: false,
              retry_allowed: false
            }]
          },
          summary: { client_streams: 1, office_streams: 1, blocked: 0 }
        }
      })
    }
    if (pathname.endsWith('/api/v1/cte/coverage') && method === 'GET') {
      return fulfill(route, {
        data: [{
          client_id: activeOfficeId,
          client_name: activeOfficeId === 1 ? FISCAL_CLIENT_OFFICE_A : FISCAL_CLIENT_OFFICE_B,
          period: '2026-07',
          status: 'CAPTURED_ORIGINAL',
          status_label: 'Capturado (original)',
          documents_count: 1,
          original_count: 1,
          autxml_redacted_count: 0,
          pending_import_count: 0,
          computed_at: FIXED_NOW
        }],
        meta: {
          period: '2026-07',
          statuses: [
            { value: 'CAPTURED_ORIGINAL', label: 'Capturado (original)' },
            { value: 'CAPTURED_AUTXML_REDACTED', label: 'Capturado (autXML redigido)' },
            { value: 'PENDING_IMPORT', label: 'Pendente de importação' },
            { value: 'HISTORICAL_GAP', label: 'Lacuna histórica' },
            { value: 'BLOCKED', label: 'Bloqueado' },
            { value: 'NO_ACTIVITY', label: 'Sem atividade observada' }
          ]
        }
      })
    }
    if (pathname.endsWith('/api/v1/cte/pending') && method === 'GET') {
      return fulfill(route, {
        data: activeOfficeId === 1
          ? [{
              id: 901,
              sha256: 'a'.repeat(64),
              byte_size: 2048,
              access_key: CTE_ACCESS_KEY,
              issuer_cnpj: '11222333000181',
              recipient_cnpj: null,
              model: '57',
              schema_family: 'cteProc',
              reason: 'UNMATCHED_ISSUER',
              reason_label: 'Emitente sem vínculo no escritório',
              source: 'CTE_AUTXML_DIST_NSU',
              channel: 'CTE_AUTXML_DISTDFE',
              nsu: 11,
              resolution_status: 'OPEN',
              created_at: FIXED_NOW
            }]
          : []
      })
    }
    if (pathname.endsWith('/api/v1/cte/repairs') && method === 'POST') {
      if (role === 'VIEWER') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, {
        data: {
          queued: true,
          cursor_id: 1,
          nsu: 1,
          correlation_id: 'corr-fixture-cte',
          cursor_last_nsu: 42
        }
      }, 202)
    }
    if (pathname.endsWith('/api/v1/operations/quarantine') && method === 'GET') {
      return fulfill(route, { data: [] })
    }
    if (/\/api\/v1\/operations\/quarantine\/\d+\/resolve$/.test(pathname) && method === 'POST') {
      if (role === 'VIEWER') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, {
        data: {
          id: 901,
          sha256: 'a'.repeat(64),
          byte_size: 2048,
          resolution_status: 'RESOLVED',
          reason: 'UNMATCHED_ISSUER',
          reason_label: 'Emitente sem vínculo no escritório',
          source: 'CTE_AUTXML_DIST_NSU'
        }
      })
    }

    // --- Captura de saídas MA (nNF) ---
    if (pathname.endsWith('/api/v1/outbound/kill-switch') && method === 'GET') {
      return fulfill(route, {
        data: {
          global_active: false,
          config_flag: false,
          enabled: false,
          protocol_query_enabled: false,
          m2m_status: 'NO_GO_M2M',
          mutating_probe_enabled: false
        }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/kill-switch') && method === 'POST') {
      if (role === 'VIEWER' || role === 'OPERATOR') {
        return fulfill(route, { message: 'Forbidden' }, 403)
      }
      return fulfill(route, {
        data: { global_active: true, position_kind: 'nNF' }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/profiles') && method === 'GET') {
      return fulfill(route, {
        data: [{
          id: 501,
          client_id: 1,
          establishment_id: 11,
          uf: 'MA',
          environment: 'homologation',
          model: '55',
          mode: 'ASSISTED',
          status: 'SEED_READY',
          consent_recorded: false,
          mandate_reference: null,
          allowlisted: false,
          kill_switch: false,
          csc: { configured: false, csc_id: null, configured_at: null },
          activated_at: null
        }, {
          id: 502,
          client_id: 1,
          establishment_id: 11,
          uf: 'MA',
          environment: 'homologation',
          model: '65',
          mode: 'ASSISTED',
          status: 'ACTIVE',
          consent_recorded: true,
          mandate_reference: 'CONTRATO-FIXTURE',
          allowlisted: true,
          kill_switch: false,
          csc: { configured: false, csc_id: null, configured_at: null },
          activated_at: FIXED_NOW
        }]
      })
    }
    if (/\/api\/v1\/outbound\/profiles\/\d+\/series$/.test(pathname) && method === 'GET') {
      const profileId = Number(pathname.match(/profiles\/(\d+)/)?.[1] || 501)
      return fulfill(route, {
        data: [{
          id: profileId === 502 ? 602 : 601,
          profile_id: profileId,
          establishment_id: 11,
          environment: 'homologation',
          model: profileId === 502 ? '65' : '55',
          series: 1,
          seed_nnf: 10,
          discovery_position: 15,
          position_kind: 'nNF',
          highest_confirmed_nnf: 12,
          status: profileId === 502 ? 'IDLE' : 'SEED_READY',
          tp_emis: '1',
          seed_access_key: '21260712ABC345678900550010000000101234567890',
          seed_issued_at: FIXED_NOW,
          next_run_at: FIXED_NOW,
          last_run_at: null,
          series_closed_for_mutation: false
        }]
      })
    }
    if (/\/api\/v1\/outbound\/series\/\d+\/numbers/.test(pathname) && method === 'GET') {
      return fulfill(route, {
        data: [{
          id: 701,
          series: 1,
          nnf: 13,
          status: 'EXHAUSTED_VISIBLE',
          candidate_access_key: '21260712ABC345678900550010000000131234567890',
          discovered_access_key: null,
          last_cstat: '217',
          attempts: 10,
          next_attempt_at: null,
          key_discovered_at: null,
          xml_captured_at: null,
          has_full_xml: false
        }, {
          id: 702,
          series: 1,
          nnf: 14,
          status: 'XML_PENDING',
          candidate_access_key: '21260712ABC345678900550010000000141234567890',
          discovered_access_key: '21260712ABC345678900550010000000149876543210',
          last_cstat: '562',
          attempts: 1,
          next_attempt_at: null,
          key_discovered_at: FIXED_NOW,
          xml_captured_at: null,
          has_full_xml: false
        }]
      })
    }
    if (/\/api\/v1\/outbound\/establishments\/\d+\/seed$/.test(pathname) && method === 'POST') {
      if (role === 'VIEWER') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, {
        data: {
          profile: {
            id: 501,
            client_id: 1,
            establishment_id: 11,
            uf: 'MA',
            environment: 'homologation',
            model: '55',
            mode: 'ASSISTED',
            status: 'SEED_READY',
            consent_recorded: false,
            allowlisted: false,
            kill_switch: false,
            csc: { configured: false }
          },
          series: {
            id: 601,
            profile_id: 501,
            series: 1,
            seed_nnf: 10,
            discovery_position: 11,
            position_kind: 'nNF',
            status: 'SEED_READY'
          }
        }
      }, 201)
    }
    if (/\/api\/v1\/outbound\/profiles\/\d+\/package$/.test(pathname) && method === 'POST') {
      if (role === 'VIEWER') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, { data: { imported: 1, skipped: 0, quarantined: 0, errors: 0 } })
    }
    if (/\/api\/v1\/outbound\/profiles\/\d+\/csc$/.test(pathname) && method === 'GET') {
      if (role !== 'ADMIN') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, { data: { configured: false, csc_id: null, configured_at: null } })
    }
    if (/\/api\/v1\/outbound\/profiles\/\d+\/csc$/.test(pathname) && method === 'POST') {
      if (role !== 'ADMIN') return fulfill(route, { message: 'Forbidden' }, 403)
      // Nunca ecoar o valor do CSC
      return fulfill(route, { data: { configured: true, csc_id: '000001', configured_at: FIXED_NOW } })
    }
    if (/\/api\/v1\/outbound\/profiles\/\d+\/activate$/.test(pathname) && method === 'POST') {
      if (role !== 'ADMIN') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, {
        data: {
          id: 501,
          status: 'ACTIVE',
          allowlisted: true,
          consent_recorded: true,
          mandate_reference: 'CONTRATO-FIXTURE',
          mode: 'ASSISTED'
        }
      })
    }
    if (/\/api\/v1\/outbound\/series\/\d+\/trigger-query$/.test(pathname) && method === 'POST') {
      if (role === 'VIEWER') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, { data: { queued: true, series_id: 601 } })
    }
    if (/\/api\/v1\/outbound\/series\/\d+\/reset$/.test(pathname) && method === 'POST') {
      if (role !== 'ADMIN') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, {
        data: {
          id: 601,
          discovery_position: 20,
          position_kind: 'nNF',
          status: 'IDLE'
        }
      })
    }

    // --- SVRS NFC-e XML recovery ---
    if (pathname.endsWith('/api/v1/outbound/svrs-nfce/summary') && method === 'GET') {
      return fulfill(route, {
        data: {
          retrieval_enabled: false,
          auto_queue_enabled: false,
          nfe55_retrieval_enabled: false,
          nfe55_auto_queue_enabled: false,
          pilot_allowlist_only: false,
          kill_switch: { active: false, source: null },
          breaker_global: { state: 'closed', open_until: null, failures: 0 },
          backlog: 0,
          oldest_pending_at: null,
          parser_version: '1',
          host: 'dfe-portal.svrs.rs.gov.br',
          egress_cohort: {
            cohort_id: 'svrs-portal-shared-egress',
            state: 'closed',
            cause: null,
            tier: 0,
            next_probe_at: null,
            exchanges_hour_remaining: 8,
            exchanges_day_remaining: 30,
            inflight: 0,
            budgets_are_preventive: true
          }
        }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/svrs-portal/egress') && method === 'GET') {
      return fulfill(route, {
        data: {
          cohort_id: 'svrs-portal-shared-egress',
          state: 'closed',
          cause: null,
          tier: 0,
          opened_at: null,
          next_probe_at: null,
          canary_key_mask: null,
          exchanges_hour: 0,
          exchanges_day: 0,
          exchanges_hour_remaining: 8,
          exchanges_day_remaining: 30,
          inflight: 0,
          budgets_are_preventive: true
        }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/svrs-portal/egress/extend-cooldown') && method === 'POST') {
      if (role !== 'ADMIN') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, { data: { cohort_id: 'svrs-portal-shared-egress', state: 'open', exchanges_hour_remaining: 8, exchanges_day_remaining: 30, inflight: 0, budgets_are_preventive: true } })
    }
    if (pathname.endsWith('/api/v1/outbound/svrs-portal/egress/select-canary') && method === 'POST') {
      if (role !== 'ADMIN') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, { data: { cohort_id: 'svrs-portal-shared-egress', state: 'half_open', exchanges_hour_remaining: 8, exchanges_day_remaining: 30, inflight: 0, budgets_are_preventive: true } })
    }
    if (pathname.endsWith('/api/v1/outbound/svrs-nfce/recoveries') && method === 'GET') {
      return fulfill(route, { data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    }
    if (pathname.endsWith('/api/v1/outbound/svrs-nfce/kill-switch') && method === 'GET') {
      return fulfill(route, { data: { active: false, source: null } })
    }
    if (pathname.endsWith('/api/v1/outbound/svrs-nfce/breaker') && method === 'GET') {
      return fulfill(route, { data: { global: { state: 'closed' } } })
    }
    if (pathname.endsWith('/api/v1/outbound/svrs-nfce/kill-switch') && method === 'POST') {
      if (role !== 'ADMIN') return fulfill(route, { message: 'Forbidden' }, 403)
      return fulfill(route, { data: { active: true, source: 'runtime' } })
    }

    if (pathname.endsWith('/api/v1/outbound/runs') && method === 'GET') {
      return fulfill(route, {
        data: [{
          id: 801,
          profile_id: 501,
          series_cursor_id: 601,
          run_type: 'SEQUENCE_QUERY',
          status: 'COMPLETED',
          position_kind: 'nNF',
          nnf_start: 11,
          nnf_end: 15,
          numbers_consulted: 5,
          keys_discovered: 1,
          xml_persisted: 0,
          gaps_open: 2,
          attempts_total: 5,
          result_summary: 'consulted=5 discovered=1 gaps=2',
          started_at: FIXED_NOW,
          finished_at: FIXED_NOW,
          triggered_by: 'operator'
        }]
      })
    }
    if (pathname.endsWith('/api/v1/clients') && method === 'GET') {
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      if (listScenario === 'slow') await new Promise(resolve => setTimeout(resolve, 1500))
      return fulfill(route, {
        data: listScenario === 'empty' ? [] : clients,
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: listScenario === 'empty' ? 0 : clients.length,
          stats: listScenario === 'empty'
            ? { total: 0, active: 0, with_credential: 0, without_credential: 0, credential_expiring_30d: 0, credential_expired: 0, capture_problem: 0 }
            : { total: clients.length, active: 1, with_credential: 1, without_credential: 0, credential_expiring_30d: 0, credential_expired: 0, capture_problem: 0 }
        }
      })
    }
    if (/\/api\/v1\/clients\/\d+$/.test(pathname) && method === 'GET') {
      const clientId = Number(pathname.match(/clients\/(\d+)$/)?.[1] || 1)
      const client = clients.find(item => item.id === clientId) || clients[0]
      return fulfill(route, { data: client })
    }
    if (/\/api\/v1\/cnpj\/11222333000181\/lookup$/.test(pathname) && method === 'GET') {
      return fulfill(route, {
        data: {
          source: 'CNPJ_WS',
          source_updated_at: '2026-07-10T12:00:00.000Z',
          client: {
            root_cnpj: '11222333',
            legal_name: 'Empresa Consultada LTDA',
            legal_nature_code: '2062',
            legal_nature_name: 'Sociedade Empresária Limitada',
            company_size_code: '03',
            company_size_name: 'Empresa de Pequeno Porte'
          },
          establishment: {
            cnpj: '11222333000181',
            trade_name: 'Empresa Consultada',
            is_matrix: true,
            registration_status: 'ACTIVE',
            registration_status_at: '2020-01-02',
            registration_status_reason: null,
            activity_started_at: '2019-03-04',
            main_cnae_code: '8211300',
            main_cnae_name: 'Serviços combinados de escritório',
            address: {
              postal_code: '01001000',
              street_type: 'Praça',
              street: 'da Sé',
              number: '100',
              complement: '10º andar',
              district: 'Sé',
              city: 'São Paulo',
              city_ibge_code: '3550308',
              state: 'SP',
              country: 'BR'
            },
            public_email: 'publico@empresa.invalid',
            public_phone: '1130000000',
            source_updated_at: '2026-07-10T12:00:00.000Z'
          }
        }
      })
    }
    if (pathname.endsWith('/api/v1/clients') && method === 'POST') {
      const body = request.postDataJSON()
      return fulfill(route, {
        data: {
          client: {
            ...clients[0],
            id: 2,
            name: body.display_name || body.legal_name,
            legal_name: body.legal_name,
            display_name: body.display_name,
            root_cnpj: '11222333',
            registration_source: 'CNPJ_WS'
          },
          establishment: {
            id: 22,
            client_id: 2,
            cnpj: body.cnpj,
            trade_name: body.trade_name,
            is_matrix: body.is_matrix,
            is_active: body.establishment_is_active,
            registration_status: body.registration_status,
            capture_enabled: body.capture_enabled
          },
          contact: body.initial_contact ? { id: 23, client_id: 2, ...body.initial_contact, is_active: true } : null
        }
      }, 201)
    }
    if (/\/api\/v1\/clients\/2$/.test(pathname)) {
      return fulfill(route, {
        data: {
          ...clients[0],
          id: 2,
          name: 'Empresa Consultada LTDA',
          legal_name: 'Empresa Consultada LTDA',
          root_cnpj: '11222333'
        }
      })
    }
    if (/\/api\/v1\/clients\/1\/credential$/.test(pathname)) {
      return fulfill(route, { data: credential })
    }
    if (/\/api\/v1\/clients\/1$/.test(pathname)) {
      const base = clients[0]!
      if (activeOfficeId === 2) {
        return fulfill(route, {
          data: {
            ...base,
            name: FISCAL_CLIENT_OFFICE_B,
            legal_name: FISCAL_CLIENT_OFFICE_B,
            notes: 'Fixture do tenant sentinela — isolamento multi-office.'
          }
        })
      }
      return fulfill(route, { data: base })
    }
    // Catálogo unificado: /documents (canônico) e /notes (alias)
    if (pathname.endsWith('/api/v1/documents/insights') || pathname.endsWith('/api/v1/notes/insights')) {
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return fulfill(route, {
        data: listScenario === 'empty'
          ? { total: 0, active: 0, cancelled: 0, review: 0, missing_party_name: 0, competence_current: 0, competence_current_label: '2026-07' }
          : { total: notes.length, active: 1, cancelled: 1, review: 0, substitute: 0, superseded: 0, missing_party_name: 0, competence_current: 1, competence_current_label: '2026-07' }
      })
    }
    if (pathname.endsWith('/api/v1/documents/by-client') || pathname.endsWith('/api/v1/notes/by-client')) {
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return fulfill(route, {
        data: listScenario === 'empty'
          ? []
          : [{
              client_id: 1,
              legal_name: clients[0]!.legal_name,
              display_name: null,
              name: clients[0]!.name,
              root_cnpj: clients[0]!.root_cnpj,
              cnpj: clients[0]!.establishments?.[0]?.cnpj,
              notes_count: notes.length,
              service_amount_sum: '2100.00',
              cancelled_count: 1,
              review_count: 0,
              last_issued_at: FIXED_NOW
            }],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 20,
          total: listScenario === 'empty' ? 0 : 1,
          total_clients: listScenario === 'empty' ? 0 : 1
        }
      })
    }
    if (pathname.endsWith('/api/v1/documents') || pathname.endsWith('/api/v1/notes')) {
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      if (listScenario === 'slow') await new Promise(resolve => setTimeout(resolve, 1500))
      return fulfill(route, {
        data: listScenario === 'empty' ? [] : notes,
        meta: { next_cursor: null, total: listScenario === 'empty' ? 0 : notes.length, per_page: 25 }
      })
    }
    const noteMatch = pathname.match(/\/api\/v1\/(?:documents|notes)\/([^/]+)$/)
    if (noteMatch && !['by-client', 'insights', 'import-batches'].includes(noteMatch[1] || '')) {
      const matchedNote = notes.find(note => note.access_key === decodeURIComponent(noteMatch[1]!))
      if (!matchedNote) return fulfill(route, { message: 'Nota não encontrada.' }, 404)
      return fulfill(route, {
        data: {
          note: matchedNote,
          events: [{ id: 81, access_key: matchedNote.access_key, event_type: 'AUTHORIZED', event_at: FIXED_NOW, status: 'ACTIVE' }],
          document: matchedNote.document
        }
      })
    }
    if (pathname.endsWith('/api/v1/sync-runs')) {
      if (method === 'POST') return fulfill(route, { data: { sync_cursor_id: 61 } }, 201)
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      if (listScenario === 'slow') await new Promise(resolve => setTimeout(resolve, 1500))
      return fulfill(route, { data: listScenario === 'empty' ? [] : syncRuns, meta: { next_cursor: null } })
    }
    if (pathname.endsWith('/api/v1/exports')) {
      if (method === 'POST') return fulfill(route, { data: exports[0] }, 202)
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      if (listScenario === 'slow') await new Promise(resolve => setTimeout(resolve, 1500))
      return fulfill(route, {
        data: listScenario === 'empty' ? [] : exports,
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 20,
          total: listScenario === 'empty' ? 0 : exports.length
        }
      })
    }
    if (pathname.endsWith('/api/v1/documents/import-batches')) {
      return fulfill(route, {
        data: listScenario === 'empty'
          ? []
          : [{
              id: 1,
              public_id: 'batch-fixture-001',
              status: 'COMPLETED',
              file_count: 3,
              processed_count: 3,
              imported_count: 2,
              item_count: 3,
              upload_complete: true,
              processing_complete: true,
              is_terminal: true,
              created_at: FIXED_NOW
            }],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: listScenario === 'empty' ? 0 : 1 }
      })
    }
    if (pathname.match(/\/api\/v1\/documents\/import-batches\/[^/]+$/) && method === 'GET') {
      return fulfill(route, {
        data: {
          id: 1,
          public_id: 'batch-fixture-001',
          status: 'COMPLETED',
          file_count: 3,
          item_count: 3,
          processed_count: 3,
          imported_count: 2,
          unmatched_count: 0,
          duplicate_count: 1,
          invalid_count: 0,
          quarantined_count: 0,
          failed_count: 0,
          upload_complete: true,
          processing_complete: true,
          is_terminal: true,
          created_at: FIXED_NOW
        }
      })
    }
    if (pathname.match(/\/api\/v1\/documents\/import-batches\/[^/]+\/items$/) && method === 'GET') {
      return fulfill(route, {
        data: [
          {
            id: 1,
            item_index: 1,
            source_name: 'nfe-demo.xml',
            model: '55',
            kind: 'NFE',
            status: 'IMPORTED',
            access_key: NFE_ACCESS_KEY,
            issuer_cnpj: '11222333000181',
            result_message: 'NF-e importada'
          },
          {
            id: 2,
            item_index: 2,
            source_name: 'cte-demo.xml',
            model: '57',
            kind: 'CTE',
            status: 'IMPORTED',
            access_key: CTE_ACCESS_KEY,
            issuer_cnpj: '11222333000181',
            result_message: 'CT-e importado'
          },
          {
            id: 3,
            item_index: 3,
            source_name: 'nfe-dup.xml',
            model: '55',
            kind: 'NFE',
            status: 'DUPLICATE',
            access_key: NFE_ACCESS_KEY,
            issuer_cnpj: '11222333000181',
            result_message: 'Duplicata'
          }
        ],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 3 }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/deadline/competence')) {
      return fulfill(route, {
        data: {
          competence: '2026-07',
          known_total: 3,
          captured_total: 2,
          pending_total: 1,
          contingency_total: 0,
          overdue_total: 0,
          completeness_scope: 'known_documents_only',
          readiness: null,
          alerts: []
        }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/deadline/capacity')) {
      return fulfill(route, {
        data: {
          competence: '2026-07',
          projection: {
            target_at: FIXED_NOW,
            due_at: FIXED_NOW,
            estimated_completion_at: FIXED_NOW,
            automatic_capacity: 100,
            required_requests: 1,
            spare_capacity: 99,
            auto_queue_fraction: 0.6
          }
        }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/deadline/pending')) {
      return fulfill(route, {
        data: [{
          id: 1,
          urgency_band: 'ATTENTION',
          access_key_masked: '3526••••••••0001',
          due_at: FIXED_NOW,
          target_at: FIXED_NOW,
          capacity_at_risk: false,
          next_step: 'PREPARE_ASSISTED_BATCH'
        }],
        meta: { current_page: 1, last_page: 1, per_page: 50, total: 1 }
      })
    }
    if (pathname.endsWith('/api/v1/outbound/deadline/metrics')) {
      return fulfill(route, { data: { competence: '2026-07', pending_total: 1 } })
    }

    // Hub de monitoramento fiscal (carteiras, mailbox, guias, FGTS, etc.)
    // Cenários ready|empty|error|slow alinhados às listagens legadas.
    if (await tryFulfillMonitoringApi(route, pathname, method, listScenario, activeOfficeId)) {
      return
    }

    return fulfill(route, { message: 'Endpoint não previsto pela fixture sintética.' }, 404)
  })
}

export async function stabilizeVisualPage(page: Page) {
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-duration: 0s !important;
        animation-delay: 0s !important;
        transition-duration: 0s !important;
        caret-color: transparent !important;
      }
    `
  })
  await page.evaluate(async () => {
    await document.fonts.ready
  })
}
