import { describe, expect, it } from 'vitest'
import {
  buildSerproOnboardingChecklist,
  primaryNextActions
} from '../../app/utils/serpro-checklist'
import type { OfficeSerproAuthorization } from '../../app/types/api'

function auth(partial: Partial<OfficeSerproAuthorization> = {}): OfficeSerproAuthorization {
  return {
    id: 1,
    office_id: 1,
    environment: 'TRIAL',
    status: 'DRAFT',
    author_identity_type: 'CNPJ',
    author_identity_masked: null,
    certificate_mode: 'EXTERNAL_SIGNATURE',
    has_managed_a1: false,
    has_termo: false,
    has_procurador_token: false,
    ...partial
  }
}

describe('checklist onboarding Integra Contador', () => {
  it('ordena ambiente → autor → cert/Termo → token → procuração → cliente', () => {
    const steps = buildSerproOnboardingChecklist({ auth: null, role: 'ADMIN' })
    expect(steps.map(s => s.id)).toEqual([
      'environment',
      'author',
      'certificate_termo',
      'token',
      'proxy_power',
      'client_operation'
    ])
    expect(steps[0]?.status).toBe('current')
    // Apenas o primeiro incompleto é "current"; os demais aguardam.
    expect(steps[1]?.status).toBe('pending')
    expect(steps.every((s, i) => i === 0 || s.status !== 'current')).toBe(true)
  })

  it('marca passos concluídos e expõe próxima ação por papel ADMIN', () => {
    const steps = buildSerproOnboardingChecklist({
      auth: auth({
        author_identity_masked: '12.***.***/****-35',
        has_termo: true,
        termo_authorization_state: 'SERPRO_ACCEPTED',
        has_procurador_token: true,
        status: 'TOKEN_ACTIVE'
      }),
      hasActiveProxyPower: false,
      role: 'ADMIN'
    })
    expect(steps.find(s => s.id === 'author')?.status).toBe('done')
    expect(steps.find(s => s.id === 'certificate_termo')?.status).toBe('done')
    expect(steps.find(s => s.id === 'token')?.status).toBe('done')
    expect(steps.find(s => s.id === 'proxy_power')?.status).toBe('current')

    const next = primaryNextActions(steps, 'ADMIN')
    expect(next.some(a => a.code === 'IMPORT_PROXY' || a.code === 'SYNC_PROXY')).toBe(true)
  })

  it('VIEWER só vê ações de leitura no passo cliente/operação', () => {
    const steps = buildSerproOnboardingChecklist({
      auth: auth({
        author_identity_masked: '***.***.***-**',
        has_termo: true,
        termo_authorization_state: 'LOCAL_VALIDATED',
        has_procurador_token: true,
        status: 'TOKEN_ACTIVE'
      }),
      hasActiveProxyPower: true,
      hasClientOperationReady: false,
      role: 'VIEWER'
    })
    const current = steps.find(s => s.status === 'current')
    expect(current?.id).toBe('client_operation')
    const next = primaryNextActions(steps, 'VIEWER')
    expect(next.every(a => !a.roles || a.roles.includes('VIEWER'))).toBe(true)
    expect(next.some(a => a.href === '/monitoring')).toBe(true)
  })

  it('exige A1 gerenciado quando certificate_mode=MANAGED_A1', () => {
    const steps = buildSerproOnboardingChecklist({
      auth: auth({
        author_identity_masked: 'masked',
        certificate_mode: 'MANAGED_A1',
        has_managed_a1: false,
        has_termo: true,
        termo_authorization_state: 'LOCAL_VALIDATED'
      }),
      role: 'ADMIN'
    })
    const cert = steps.find(s => s.id === 'certificate_termo')
    expect(cert?.status).toBe('current')
    expect(cert?.reasons.some(r => /A1/i.test(r))).toBe(true)
  })
})
