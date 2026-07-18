import { describe, expect, it, vi } from 'vitest'
import { createAuthApi } from '../../app/composables/api/createAuthApi'
import { createDocumentsApi } from '../../app/composables/api/createDocumentsApi'
import { createFiscalApi } from '../../app/composables/api/createFiscalApi'
import { createOfficeApi } from '../../app/composables/api/createOfficeApi'
import { createOperationsApi } from '../../app/composables/api/createOperationsApi'
import { createPlatformApi } from '../../app/composables/api/createPlatformApi'
import { createWorkApi } from '../../app/composables/api/createWorkApi'

type Call = { path: string, opts?: Record<string, unknown> }

function mockClient(payload: Record<string, unknown> = { data: [], meta: {} }) {
  const calls: Call[] = []
  const client = vi.fn(async (path: string, opts?: Record<string, unknown>) => {
    calls.push({ path, opts })
    return payload
  })
  return { client, calls }
}

const apiUrl = (path: string) => `https://api.test${path}`

describe('composables/api factories (runtime)', () => {
  it('account.update usa o perfil global autenticado', async () => {
    const { client, calls } = mockClient()
    const api = createAuthApi(client as never)

    await api.account.update({ name: 'Viewer', email: 'viewer@example.com' })

    expect(calls[0]?.path).toBe('/api/v1/account')
    expect(calls[0]?.opts?.method).toBe('PATCH')
    expect(calls[0]?.opts?.body).toEqual({
      name: 'Viewer',
      email: 'viewer@example.com'
    })
  })

  it('operations.inbox e summary batem nos paths canônicos', async () => {
    const { client, calls } = mockClient()
    const api = createOperationsApi(client as never, apiUrl)

    await api.operations.inbox({ limit: 10, severity: 'high' })
    await api.operations.summary()

    expect(calls[0]?.path).toBe('/api/v1/operations/inbox')
    expect(calls[0]?.opts?.query).toEqual({ limit: 10, severity: 'high' })
    expect(calls[1]?.path).toBe('/api/v1/operations/summary')
  })

  it('documents.list e download url builders', async () => {
    const { client, calls } = mockClient()
    const api = createDocumentsApi(client as never, apiUrl)

    await api.documents.list({ q: 'ACME', kind: 'NFSE' })
    expect(calls[0]?.path).toBe('/api/v1/documents')
    expect(calls[0]?.opts?.query).toEqual({ q: 'ACME', kind: 'NFSE' })

    expect(api.documents.xmlUrl('KEY123')).toBe('https://api.test/api/v1/documents/KEY123/xml')
  })

  it('work.processes.list usa path de processos', async () => {
    const { client, calls } = mockClient()
    const api = createWorkApi(client as never, apiUrl)

    await api.work.processes.list({ page: 1 })
    expect(calls[0]?.path).toBe('/api/v1/work/processes')
    expect(calls[0]?.opts?.query).toEqual({ page: 1 })
  })

  it('officeAutXml.overview encaminha recorte incremental e cancelamento', async () => {
    const { client, calls } = mockClient()
    const api = createOfficeApi(client as never)
    const controller = new AbortController()

    await api.officeAutXml.overview({ page: 2, per_page: 25 }, { signal: controller.signal })

    expect(calls[0]?.path).toBe('/api/v1/office/autxml')
    expect(calls[0]?.opts?.query).toEqual({ page: 2, per_page: 25 })
    expect(calls[0]?.opts?.signal).toBe(controller.signal)
  })

  it('platform.serpro usa paths /api/v1/platform/serpro/*', async () => {
    const { client, calls } = mockClient()
    const api = createPlatformApi(client as never)

    await api.platform.serpro.health({ environment: 'TRIAL' })
    await api.platform.serpro.killSwitch.set({ active: true, reason: 'teste' })
    await api.platform.serpro.usage.consolidation({ year: 2026, month: 1 })
    await api.platform.serpro.productionOnboarding.show()
    await api.platform.serpro.productionOnboarding.submit(new FormData(), 'prod-onboard-test')

    expect(calls[0]?.path).toBe('/api/v1/platform/serpro/health')
    expect(calls[1]?.path).toBe('/api/v1/platform/serpro/kill-switch')
    expect(calls[1]?.opts?.method).toBe('POST')
    expect(calls[2]?.path).toBe('/api/v1/platform/serpro-usage/consolidation')
    expect(calls[3]?.path).toBe('/api/v1/platform/serpro/production-onboarding')
    expect(calls[4]?.path).toBe('/api/v1/platform/serpro/production-onboarding')
    expect(calls[4]?.opts?.method).toBe('POST')
    expect(calls[4]?.opts?.headers).toEqual({ 'Idempotency-Key': 'prod-onboard-test' })
  })

  it('platform.owner usa recurso singular /api/v1/platform/owner', async () => {
    const { client, calls } = mockClient()
    const api = createPlatformApi(client as never)

    await api.platform.owner.show()
    await api.platform.owner.update({ name: 'Proprietário', email: 'owner@example.com' })

    expect(calls[0]?.path).toBe('/api/v1/platform/owner')
    expect(calls[1]?.path).toBe('/api/v1/platform/owner')
    expect(calls[1]?.opts?.method).toBe('PATCH')
    expect(calls[1]?.opts?.body).toEqual({ name: 'Proprietário', email: 'owner@example.com' })
    expect(api.platform).not.toHaveProperty('admins')
  })

  it('office.profile e platform.offices usam paths unificados', async () => {
    const { client, calls } = mockClient({
      data: { profile: { cnpj: null }, credential: null }
    })
    const officeApi = createOfficeApi(client as never)
    await officeApi.office.profile.show()
    await officeApi.office.canonicalCredential.show()
    expect(calls[0]?.path).toBe('/api/v1/office/settings')
    expect(calls[1]?.path).toBe('/api/v1/office/settings/credential')

    const { client: pClient, calls: pCalls } = mockClient()
    const platformApi = createPlatformApi(pClient as never)
    await platformApi.platform.offices.select(7)
    expect(pCalls[0]?.path).toBe('/api/v1/platform/offices/select')
    expect(pCalls[0]?.opts?.method).toBe('POST')
  })

  it('simplesMei não expõe consumer operacional de guide-stubs', () => {
    const { client } = mockClient()
    const api = createFiscalApi(client as never, apiUrl)

    expect(api.fiscal.simplesMei).not.toHaveProperty('guideStubs')
  })

  it('PNR usa rotas tenant-scoped e não envia office_id', async () => {
    const { client, calls } = mockClient()
    const api = createFiscalApi(client as never, apiUrl)

    await api.fiscal.pnrRenunciations.forClient(42)
    await api.fiscal.pnrRenunciations.history(42, { page: 0, page_size: 10 })
    await api.fiscal.pnrRenunciations.status(42, 'SOL-123')
    await api.fiscal.pnrRenunciations.receipt(42, 88)

    expect(calls[0]?.path).toBe('/api/v1/fiscal/clients/42/pnr-renunciations')
    expect(calls[1]?.opts?.body).toEqual({ page: 0, page_size: 10 })
    expect(calls[2]?.opts?.body).toEqual({ id_solicitacao: 'SOL-123' })
    expect(calls[3]?.opts?.body).toEqual({ renunciation_id: 88 })
    expect(JSON.stringify(calls)).not.toContain('office_id')
  })

  it('certificado CCMEI usa rotas do cliente, confirmação explícita e download sem dados fiscais', async () => {
    const { client, calls } = mockClient()
    const api = createFiscalApi(client as never, apiUrl)

    await api.fiscal.ccmei.issuedCertificates.history(42)
    await api.fiscal.ccmei.issuedCertificates.issue(42)

    expect(calls[0]?.path).toBe('/api/v1/fiscal/simples-mei/ccmei/clients/42/issued-certificates')
    expect(calls[1]?.path).toBe('/api/v1/fiscal/simples-mei/ccmei/clients/42/issued-certificates')
    expect(calls[1]?.opts).toEqual({ method: 'POST', body: { confirmed: true } })
    expect(api.fiscal.ccmei.issuedCertificates.downloadPath(42, 9))
      .toBe('/api/v1/fiscal/simples-mei/ccmei/clients/42/issued-certificates/9/download')
    expect(JSON.stringify(calls)).not.toContain('office_id')
  })

  it('MIT agenda somente a lista de apurações com filtros de negócio', async () => {
    const { client, calls } = mockClient()
    const api = createFiscalApi(client as never, apiUrl)

    await api.fiscal.mit.enqueueListaApuracoes({
      client_id: 42,
      anoApuracao: 2026,
      mesApuracao: 6,
      situacaoApuracao: 2
    })

    expect(calls[0]?.path).toBe('/api/v1/fiscal/mit/lista-apuracoes')
    expect(calls[0]?.opts).toEqual({
      method: 'POST',
      body: { client_id: 42, anoApuracao: 2026, mesApuracao: 6, situacaoApuracao: 2 }
    })
    expect(JSON.stringify(calls)).not.toContain('office_id')
  })
})
