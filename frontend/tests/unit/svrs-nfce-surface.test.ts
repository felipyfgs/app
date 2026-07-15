import { describe, expect, it } from 'vitest'
import type { SvrsNfceChannelSummary, SvrsNfceRecovery } from '../../app/types/api'
import {
  canManageClients,
  canManageCredentials
} from '../../app/utils/permissions'
import type { MeUser } from '../../app/types/api'

function user(partial: Partial<MeUser> & Pick<MeUser, 'role'>): MeUser {
  return {
    id: 1,
    name: 'Teste',
    email: 't@example.com',
    two_factor_confirmed: true,
    two_factor_required: true,
    requires_two_factor_setup: false,
    office: { id: 1, name: 'Escritório', slug: 'escritorio' },
    ...partial
  }
}

function publicRecovery(r: SvrsNfceRecovery): Record<string, unknown> {
  return {
    id: r.id,
    recovery_status: r.recovery_status,
    access_key_masked: r.access_key_masked,
    failure_label: r.failure_label
  }
}

function statusVocabulary(status: string): string {
  // Alinhado ao enum PHP SvrsNfceRecoveryStatus + labels do painel
  const map: Record<string, string> = {
    ELIGIBLE: 'Elegível (XML pendente)',
    QUEUED: 'Na fila',
    RUNNING: 'Em recuperação',
    RETRY_SCHEDULED: 'Retry agendado',
    CAPTURED: 'Capturado',
    NOT_AVAILABLE_VISIBLE: 'Indisponível',
    BLOCKED: 'Bloqueado',
    RESOLVED_BY_OTHER_SOURCE: 'Fallback / outra fonte'
  }
  return map[status] || status
}

describe('svrs nfce surface', () => {
  it('summary público não expõe segredos', () => {
    const s: SvrsNfceChannelSummary = {
      retrieval_enabled: false,
      auto_queue_enabled: false,
      pilot_allowlist_only: false,
      kill_switch: { active: false },
      breaker_global: { state: 'closed' },
      backlog: 0,
      host: 'dfe-portal.svrs.rs.gov.br',
      egress_cohort: {
        cohort_id: 'cohort-fixture',
        state: 'closed',
        exchanges_hour_remaining: 8,
        exchanges_day_remaining: 30,
        inflight: 0,
        budgets_are_preventive: true
      }
    }
    const json = JSON.stringify(s)
    expect(json).not.toMatch(/pfx|password|private_key|cookie|vault_object|BEGIN CERTIFICATE/i)
  })

  it('saúde de egress expõe budget preventivo sem permitir inferir segredo', () => {
    const health = {
      state: 'open',
      next_probe_at: '2026-07-16T12:00:00Z',
      exchanges_hour_remaining: 0,
      exchanges_day_remaining: 20,
      budgets_are_preventive: true
    }
    expect(health.budgets_are_preventive).toBe(true)
    expect(JSON.stringify(health)).not.toMatch(/pfx|cookie|private|access_key/i)
  })

  it('recovery mascara chave e não inclui xml/html', () => {
    const r: SvrsNfceRecovery = {
      id: 1,
      profile_id: 2,
      establishment_id: 3,
      environment: 'homologation',
      model: '65',
      access_key_masked: '212607****7892',
      recovery_status: 'RETRY_SCHEDULED'
    }
    const pub = publicRecovery(r)
    expect(pub.access_key_masked).toContain('*')
    expect(pub).not.toHaveProperty('xml')
    expect(pub).not.toHaveProperty('html')
  })

  it('vocabulário visual distinto por estado', () => {
    expect(statusVocabulary('ELIGIBLE')).not.toBe(statusVocabulary('CAPTURED'))
    expect(statusVocabulary('BLOCKED')).toBe('Bloqueado')
    expect(statusVocabulary('RESOLVED_BY_OTHER_SOURCE')).toContain('Fallback')
    expect(statusVocabulary('RUNNING')).toContain('recuperação')
  })

  it('retry/operator vs kill switch admin', () => {
    const op = user({ role: 'OPERATOR' })
    const admin = user({ role: 'ADMIN', two_factor_confirmed: true })
    const viewer = user({ role: 'VIEWER' })
    expect(canManageClients(op)).toBe(true)
    expect(canManageCredentials(op)).toBe(false)
    expect(canManageCredentials(admin)).toBe(true)
    expect(canManageClients(viewer)).toBe(false)
  })
})
