/**
 * Fixtures mínimas e determinísticas usadas pelos testes unitários do contrato fiscal.
 * Mantidas aqui para que o gate unitário não dependa da suíte de browser removida.
 */
import {
  FISCAL_MODULE_LABELS,
  FISCAL_PORTFOLIO_MODULE_KEYS
} from '../../../app/types/fiscal-modules'
import type {
  FiscalModuleClientRow,
  FiscalModuleCounters,
  FiscalModuleOverview,
  FiscalPortfolioModuleKey
} from '../../../app/types/fiscal-modules'

export const FISCAL_FIXTURE_CNPJ_RAW = '12ABC345678900'
export const FISCAL_FIXTURE_CNPJ_MASKED = maskCnpj(FISCAL_FIXTURE_CNPJ_RAW)

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

type FiscalScenario = 'ready' | 'empty' | 'error'

function maskCnpj(cnpj: string): string {
  const clean = cnpj.toUpperCase().replace(/[^0-9A-Z]/g, '')
  return `${clean.slice(0, 4)}${'*'.repeat(Math.max(0, clean.length - 8))}${clean.slice(-4)}`
}

function counters(overrides: Partial<FiscalModuleCounters> = {}): FiscalModuleCounters {
  return {
    up_to_date: 1,
    processing: 0,
    pending: 1,
    attention: 1,
    error: 0,
    blocked: 0,
    unknown: 0,
    unsupported: 0,
    not_applicable: 0,
    ...overrides
  }
}

export function buildFiscalModuleOverview(
  module: FiscalPortfolioModuleKey
): FiscalModuleOverview {
  return {
    module_key: module,
    module_label: FISCAL_MODULE_LABELS[module],
    data_origin: 'DEMO',
    data_origin_label: 'Demonstração',
    is_synthetic: true,
    coverage: module === 'fgts' ? 'PARTIAL' : 'FULL',
    source_label: 'Fixture unitária',
    as_of: '2026-07-14T15:00:00.000Z',
    total_clients: 2,
    counters: counters(),
    agenda: [],
    categories: [],
    metrics: { total_clients: 2 }
  }
}

export function buildFiscalModuleClientRow(
  module: FiscalPortfolioModuleKey,
  clientId = 1
): FiscalModuleClientRow {
  const legalName = clientId === 1 ? 'Cliente Demonstração Segura' : 'Segundo Cliente Demo'

  return {
    module_key: module,
    client_id: clientId,
    legal_name: legalName,
    display_name: null,
    name: legalName,
    cnpj_masked: clientId === 1 ? FISCAL_FIXTURE_CNPJ_MASKED : '98XY******2100',
    root_cnpj_masked: clientId === 1 ? '12AB****' : '98XY****',
    competence: '2026-07',
    situation: clientId === 1 ? 'PENDING' : 'ATTENTION',
    coverage: module === 'fgts' ? 'PARTIAL' : 'FULL',
    data_origin: 'DEMO',
    last_consulted_at: '2026-07-14T15:00:00.000Z',
    detail: { module_key: module }
  } as FiscalModuleClientRow
}

export function buildFiscalModuleClients(
  module: FiscalPortfolioModuleKey
): FiscalModuleClientRow[] {
  return [
    buildFiscalModuleClientRow(module, 1),
    buildFiscalModuleClientRow(module, 2)
  ]
}

export function fiscalModuleOverviewResponse(
  module: FiscalPortfolioModuleKey,
  scenario: FiscalScenario = 'ready'
) {
  if (scenario === 'error') {
    return { message: 'Falha sintética sanitizada no overview fiscal.' }
  }

  const overview = buildFiscalModuleOverview(module)
  if (scenario === 'empty') {
    overview.total_clients = 0
    overview.counters = counters({
      up_to_date: 0,
      processing: 0,
      pending: 0,
      attention: 0,
      error: 0
    })
  }

  return { data: overview }
}

export function fiscalModuleClientsResponse(
  module: FiscalPortfolioModuleKey,
  scenario: FiscalScenario = 'ready'
) {
  const data = scenario === 'empty' ? [] : buildFiscalModuleClients(module)
  return {
    data,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: data.length
    }
  }
}

export function buildFiscalRuns() {
  return [
    { id: 801, client_id: 1, status: 'COMPLETED', result: 'SUCCESS' },
    { id: 802, client_id: 2, status: 'FAILED', result: 'ERROR' }
  ]
}

export function buildFiscalSnapshots() {
  return [{
    id: 931,
    client_id: 1,
    situation: 'PENDING',
    normalized: { demo_fixture: true },
    observed_at: '2026-07-14T15:00:00.000Z'
  }]
}

export function buildFiscalFindings() {
  return [{
    id: 851,
    client_id: 1,
    code: 'DEMO_PENDING',
    severity: 'HIGH',
    title: 'Finding demonstrativo',
    detail: 'Fixture sanitizada.',
    is_active: true
  }]
}

export function buildFiscalPendingItems() {
  return [{
    id: 861,
    client_id: 1,
    code: 'DEMO_PENDING_DECL',
    title: 'Pendência demonstrativa',
    status: 'OPEN',
    situation: 'PENDING'
  }]
}

export function buildMailboxMessages() {
  return [{
    id: 9001,
    client_id: 1,
    subject_preview: 'Mensagem demonstrativa',
    has_body: true,
    attachment_count: 1
  }]
}

export function buildMailboxMessageDetail(id = 9001) {
  return {
    id,
    client_id: 1,
    subject_preview: 'Mensagem demonstrativa',
    has_body: true,
    attachment_count: 1,
    body_content_type: 'text/plain',
    body_sha256: 'e'.repeat(64),
    attachments: [{
      id: 9101,
      filename: 'anexo-demo.txt',
      content_type: 'text/plain',
      byte_size: 64,
      content_sha256: 'f'.repeat(64)
    }]
  }
}

export function buildGuides() {
  return [{
    id: 961,
    client_id: 1,
    amount_cents: 34567,
    current_version: {
      id: 962,
      content_sha256: 'a1'.repeat(32),
      content_type: 'application/pdf'
    },
    data_origin: 'DEMO',
    is_synthetic: true
  }]
}

export function buildGuideDetail() {
  return {
    ...buildGuides()[0]!,
    versions: [buildGuides()[0]!.current_version]
  }
}

export function buildDeclarations() {
  return [{
    id: 951,
    client_id: 1,
    obligation_code: 'DEFIS',
    module_key: 'declarations',
    data_origin: 'DEMO',
    is_synthetic: true
  }]
}

export function buildFgtsCoverage() {
  return {
    module: 'fgts',
    coverage: 'PARTIAL',
    declares_fgts_digital_debt: false,
    scraping_allowed: false,
    portal_fallback: false,
    limitations: ['Cobertura parcial via eventos suportados.']
  }
}

export function buildFgtsCompetences() {
  return [{
    id: 971,
    client_id: 1,
    guide_status: 'UNSUPPORTED',
    payment_status: 'UNSUPPORTED',
    coverage: 'PARTIAL'
  }]
}

export function buildSitfisView() {
  return {
    snapshot: buildFiscalSnapshots()[0],
    is_negative_certificate: false,
    disclaimer: 'Ausência de pendência reconhecida não equivale a certidão negativa.',
    data_origin: 'DEMO',
    is_synthetic: true
  }
}

export function buildFiscalCategories() {
  return FISCAL_PORTFOLIO_MODULE_KEYS.map((module, index) => ({
    id: index + 101,
    code: module.toUpperCase(),
    name: FISCAL_MODULE_LABELS[module],
    module_key: module,
    default_coverage: module === 'fgts' ? 'PARTIAL' : 'FULL',
    is_active: true
  }))
}

function collectObjectKeys(value: unknown, keys = new Set<string>(), depth = 0): Set<string> {
  if (value == null || depth > 8) return keys

  if (Array.isArray(value)) {
    for (const item of value) collectObjectKeys(item, keys, depth + 1)
    return keys
  }

  if (typeof value === 'object') {
    for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
      keys.add(key)
      collectObjectKeys(child, keys, depth + 1)
    }
  }

  return keys
}

export function assertFiscalPayloadSanitized(payload: unknown, label = 'payload'): void {
  const keys = collectObjectKeys(payload)
  for (const forbidden of FISCAL_FORBIDDEN_KEYS) {
    if (keys.has(forbidden)) {
      throw new Error(`${label}: campo proibido "${forbidden}" presente no payload fiscal.`)
    }
  }

  const serialized = JSON.stringify(payload)
  if (/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(serialized)) {
    throw new Error(`${label}: conteúdo proibido detectado.`)
  }
  if (serialized.includes(FISCAL_FIXTURE_CNPJ_RAW) && keys.has('cnpj_masked')) {
    throw new Error(`${label}: CNPJ completo não mascarado exposto junto a cnpj_masked.`)
  }
}

export function assertOverviewEnvelope(
  module: FiscalPortfolioModuleKey,
  body: unknown
): asserts body is { data: FiscalModuleOverview } {
  if (!body || typeof body !== 'object') {
    throw new Error(`overview ${module}: envelope ausente.`)
  }

  const data = (body as { data?: unknown }).data
  if (!data || typeof data !== 'object') {
    throw new Error(`overview ${module}: data ausente no envelope.`)
  }

  const overview = data as Record<string, unknown>
  if (overview.module_key !== module) {
    throw new Error(`overview ${module}: module_key incompatível.`)
  }
  if (typeof overview.total_clients !== 'number') {
    throw new Error(`overview ${module}: total_clients deve ser number.`)
  }

  const values = overview.counters as Record<string, unknown> | undefined
  if (!values) throw new Error(`overview ${module}: counters ausentes.`)
  for (const key of ['up_to_date', 'processing', 'pending', 'attention', 'error']) {
    if (typeof values[key] !== 'number') {
      throw new Error(`overview ${module}: counters.${key} deve ser number.`)
    }
  }

  assertFiscalPayloadSanitized(body, `overview ${module}`)
}

export function assertClientRowEnvelope(
  module: FiscalPortfolioModuleKey,
  row: unknown
): asserts row is FiscalModuleClientRow {
  if (!row || typeof row !== 'object') throw new Error(`client row ${module}: linha ausente.`)

  const client = row as Record<string, unknown>
  if (client.module_key !== module) throw new Error(`client row ${module}: module_key incompatível.`)
  if (typeof client.client_id !== 'number') throw new Error(`client row ${module}: client_id inválido.`)
  if (typeof client.legal_name !== 'string') throw new Error(`client row ${module}: legal_name obrigatório.`)
  if (typeof client.cnpj_masked !== 'string' || !client.cnpj_masked.includes('*')) {
    throw new Error(`client row ${module}: cnpj_masked deve conter máscara.`)
  }

  const detail = client.detail as Record<string, unknown> | undefined
  if (!detail) throw new Error(`client row ${module}: detail obrigatório.`)
  if (detail.module_key !== module) {
    throw new Error(`client row ${module}: detail.module_key incompatível.`)
  }

  assertFiscalPayloadSanitized(row, `client row ${module}`)
}

export function assertClientsPageEnvelope(module: FiscalPortfolioModuleKey, body: unknown): void {
  if (!body || typeof body !== 'object') throw new Error(`clients ${module}: envelope ausente.`)

  const data = (body as { data?: unknown }).data
  if (!Array.isArray(data)) throw new Error(`clients ${module}: data deve ser array.`)
  for (const row of data) assertClientRowEnvelope(module, row)

  assertFiscalPayloadSanitized(body, `clients ${module}`)
}

export function assertListEnvelope(label: string, body: unknown): void {
  if (!body || typeof body !== 'object' || !Array.isArray((body as { data?: unknown }).data)) {
    throw new Error(`${label}: data deve ser array.`)
  }
  assertFiscalPayloadSanitized(body, label)
}
