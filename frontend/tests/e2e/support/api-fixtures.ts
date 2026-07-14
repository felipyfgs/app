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

const FIXED_NOW = '2026-07-14T15:00:00.000Z'
export const NOTE_ACCESS_KEY = 'NFS20260714000000000000000000000000000000000001'
export const SECOND_NOTE_ACCESS_KEY = 'NFS20260714000000000000000000000000000000000002'
export type ListScenario = 'ready' | 'empty' | 'error' | 'slow'

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
  establishments: [{
    id: 11,
    client_id: 1,
    cnpj: '12ABC345678900',
    trade_name: 'Matriz',
    is_matrix: true,
    is_active: true,
    capture_enabled: true,
    registration_status: 'UNKNOWN',
    registration_source: 'LEGACY',
    capture_eligibility: { eligible: true, reasons: [], reasons_codes: [] }
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
    capture_eligibility: {
      eligible: false,
      reasons: ['Captura desabilitada para este estabelecimento.'],
      reasons_codes: ['capture_disabled']
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
  access_key: NOTE_ACCESS_KEY,
  issuer_cnpj: '12ABC345678900',
  taker_cnpj: '98XYZ765432100',
  fiscal_role: 'ISSUER',
  competence: '2026-07',
  issued_at: FIXED_NOW,
  service_amount: '1250.00',
  status: 'ACTIVE',
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
  access_key: SECOND_NOTE_ACCESS_KEY,
  issuer_cnpj: '12ABC345678901',
  taker_cnpj: '98XYZ765432100',
  fiscal_role: 'TAKER',
  competence: '2026-06',
  issued_at: '2026-06-14T15:00:00.000Z',
  service_amount: '850.00',
  status: 'CANCELLED',
  document: {
    id: 42,
    sha256: 'c'.repeat(64),
    document_type: 'NFSE',
    schema_version: '1.00',
    access_key: SECOND_NOTE_ACCESS_KEY,
    byte_size: 1536,
    parse_status: 'PARSED'
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
  generated_at: FIXED_NOW
}

function identity(role: OfficeRole): MeUser {
  return {
    id: role === 'ADMIN' ? 1 : role === 'OPERATOR' ? 2 : 3,
    name: role === 'ADMIN' ? 'Ana Administradora' : role === 'OPERATOR' ? 'Olívia Operadora' : 'Vítor Visualizador',
    email: `${role.toLowerCase()}@fixture.invalid`,
    two_factor_confirmed: role === 'ADMIN',
    two_factor_required: role === 'ADMIN',
    requires_two_factor_setup: false,
    office: { id: 1, name: 'Escritório Contábil Modelo', slug: 'escritorio-modelo' },
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
      return fulfill(route, { data: identity(role) })
    }
    if (pathname.endsWith('/api/v1/operations/summary')) {
      return fulfill(route, { data: summary })
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
            ? { total: 0, active: 0, without_credential: 0, credential_expiring_30d: 0, credential_expired: 0 }
            : { total: clients.length, active: 1, without_credential: 0, credential_expiring_30d: 0, credential_expired: 0 }
        }
      })
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
      return fulfill(route, { data: clients[0] })
    }
    if (pathname.endsWith('/api/v1/notes')) {
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      if (listScenario === 'slow') await new Promise(resolve => setTimeout(resolve, 1500))
      return fulfill(route, { data: listScenario === 'empty' ? [] : notes, meta: { next_cursor: null } })
    }
    const noteMatch = pathname.match(/\/api\/v1\/notes\/([^/]+)$/)
    if (noteMatch) {
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
      if (method === 'POST') return fulfill(route, { data: exports[0] }, 201)
      if (listScenario === 'error') return fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      if (listScenario === 'slow') await new Promise(resolve => setTimeout(resolve, 1500))
      return fulfill(route, { data: listScenario === 'empty' ? [] : exports })
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
