import { describe, expect, it, vi } from 'vitest'
import { createOfficeApi } from '../../app/composables/api/createOfficeApi'
import { createPlatformApi } from '../../app/composables/api/createPlatformApi'

type Call = { path: string, opts?: Record<string, unknown> }

function mockClient() {
  const calls: Call[] = []
  const client = vi.fn(async (path: string, opts?: Record<string, unknown>) => {
    calls.push({ path, opts })
    return { data: {} }
  })
  return { client, calls }
}

describe('DTE canary API client', () => {
  it('platform dteCanary paths are global and sanitised surface', async () => {
    const { client, calls } = mockClient()
    const api = createPlatformApi(client as never)

    await api.platform.serpro.dteCanary.summary()
    await api.platform.serpro.dteCanary.create()
    await api.platform.serpro.dteCanary.selectTarget(1, { office_id: 2, client_id: 3 })
    await api.platform.serpro.dteCanary.execute(1)
    await api.platform.serpro.dteCanary.disable({
      confirmation_phrase: 'CONFIRMO-DTE-DISABLE',
      reason: 'test'
    })

    expect(calls.map(c => c.path)).toEqual([
      '/api/v1/platform/serpro/dte-canary',
      '/api/v1/platform/serpro/dte-canary',
      '/api/v1/platform/serpro/dte-canary/1/target',
      '/api/v1/platform/serpro/dte-canary/1/execute',
      '/api/v1/platform/serpro/dte-canary/disable'
    ])
    const targetBody = calls[2]?.opts?.body as Record<string, unknown>
    expect(targetBody).toEqual({ office_id: 2, client_id: 3 })
    expect(targetBody).not.toHaveProperty('operation_key')
    expect(targetBody).not.toHaveProperty('payload')
  })

  it('office dteCanary does not send office_id', async () => {
    const { client, calls } = mockClient()
    const api = createOfficeApi(client as never)

    await api.office.dteCanary.pending()
    await api.office.dteCanary.confirm(9)
    await api.office.dteCanary.result(9)

    expect(calls.map(c => c.path)).toEqual([
      '/api/v1/serpro/dte-canary/pending',
      '/api/v1/serpro/dte-canary/9/confirm',
      '/api/v1/serpro/dte-canary/9/result'
    ])
    const confirmBody = calls[1]?.opts?.body as Record<string, unknown>
    expect(confirmBody).not.toHaveProperty('office_id')
  })

  it('global UI page must not render fiscal_result field', async () => {
    const { readFileSync } = await import('node:fs')
    const { resolve } = await import('node:path')
    const page = readFileSync(
      resolve(__dirname, '../../app/pages/admin/serpro/dte-canary.vue'),
      'utf8'
    )
    expect(page).toContain('Sem payload fiscal')
    expect(page).not.toMatch(/fiscal_result/)
    expect(page).not.toMatch(/\bdados\b.*fiscal/)
  })
})
