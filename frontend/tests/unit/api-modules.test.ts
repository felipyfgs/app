import { describe, expect, it, vi } from 'vitest'
import { createDocumentsApi } from '../../app/composables/api/createDocumentsApi'
import { createOfficeApi } from '../../app/composables/api/createOfficeApi'
import { createOperationsApi } from '../../app/composables/api/createOperationsApi'
import { createPlatformApi } from '../../app/composables/api/createPlatformApi'
import { createWorkApi } from '../../app/composables/api/createWorkApi'

type Call = { path: string, opts?: Record<string, unknown> }

function mockClient() {
  const calls: Call[] = []
  const client = vi.fn(async (path: string, opts?: Record<string, unknown>) => {
    calls.push({ path, opts })
    return { data: [], meta: {} }
  })
  return { client, calls }
}

const apiUrl = (path: string) => `https://api.test${path}`

describe('composables/api factories (runtime)', () => {
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

    expect(calls[0]?.path).toBe('/api/v1/platform/serpro/health')
    expect(calls[1]?.path).toBe('/api/v1/platform/serpro/kill-switch')
    expect(calls[1]?.opts?.method).toBe('POST')
    expect(calls[2]?.path).toBe('/api/v1/platform/serpro-usage/consolidation')
  })
})
