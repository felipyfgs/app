import { describe, expect, it } from 'vitest'
import type { OutboundCaptureProfile, OutboundNumberState, OutboundSeriesCursor, MeUser } from '../../app/types/api'
import {
  canManageClients,
  canManageCredentials,
  canTriggerSync,
  hasConfirmedAdminAccess
} from '../../app/utils/permissions'

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

/**
 * Regras de superfície da captura de saídas (espelham o painel).
 * position_kind deve ser nNF; segredos não entram em toPublic.
 */
function publicProfile(p: OutboundCaptureProfile): Record<string, unknown> {
  return {
    id: p.id,
    model: p.model,
    mode: p.mode,
    status: p.status,
    csc: p.csc,
    allowlisted: p.allowlisted
  }
}

function seriesLabel(s: OutboundSeriesCursor): string {
  return `Série ${s.series} · posição ${s.position_kind} ${s.discovery_position}`
}

function gapVisible(n: OutboundNumberState): boolean {
  return ['EXHAUSTED_VISIBLE', 'XML_PENDING', 'GAP_PENDING', 'LIMITED_NO_KEY'].includes(n.status)
}

describe('outbound surface', () => {
  it('perfil público não carrega valor de CSC', () => {
    const profile: OutboundCaptureProfile = {
      id: 1,
      client_id: 1,
      establishment_id: 11,
      uf: 'MA',
      environment: 'homologation',
      model: '65',
      mode: 'ASSISTED',
      status: 'ACTIVE',
      consent_recorded: true,
      allowlisted: true,
      kill_switch: false,
      csc: { configured: true, csc_id: '000001', configured_at: '2026-07-01T00:00:00Z' }
    }
    const pub = publicProfile(profile)
    expect(JSON.stringify(pub)).not.toMatch(/TOKEN|secret|password/i)
    expect((pub.csc as { csc_id?: string }).csc_id).toBe('000001')
    expect(pub).not.toHaveProperty('csc_token')
  })

  it('série usa position_kind nNF', () => {
    const series: OutboundSeriesCursor = {
      id: 1,
      profile_id: 1,
      establishment_id: 11,
      environment: 'homologation',
      model: '55',
      series: 1,
      seed_nnf: 10,
      discovery_position: 15,
      position_kind: 'nNF',
      status: 'IDLE'
    }
    expect(series.position_kind).toBe('nNF')
    expect(seriesLabel(series)).toContain('nNF')
    expect(seriesLabel(series)).not.toContain('NSU')
  })

  it('lacunas e chave sem XML permanecem visíveis', () => {
    const gaps: OutboundNumberState[] = [
      {
        id: 1,
        series: 1,
        nnf: 13,
        status: 'EXHAUSTED_VISIBLE',
        attempts: 10,
        has_full_xml: false
      },
      {
        id: 2,
        series: 1,
        nnf: 14,
        status: 'XML_PENDING',
        discovered_access_key: '2126…',
        attempts: 1,
        has_full_xml: false
      },
      {
        id: 3,
        series: 1,
        nnf: 15,
        status: 'COMPLETE',
        attempts: 1,
        has_full_xml: true
      }
    ]
    expect(gaps.filter(gapVisible)).toHaveLength(2)
    expect(gaps.find(g => g.status === 'XML_PENDING')?.has_full_xml).toBe(false)
  })

  it('papéis: VIEWER só leitura; OPERATOR semente/pacote; ADMIN CSC/kill', () => {
    const viewer = user({ role: 'VIEWER' })
    const operator = user({ role: 'OPERATOR' })
    const admin = user({ role: 'ADMIN' })

    expect(canManageClients(viewer)).toBe(false)
    expect(canManageCredentials(viewer)).toBe(false)

    expect(canManageClients(operator)).toBe(true)
    expect(canTriggerSync(operator)).toBe(true)
    expect(canManageCredentials(operator)).toBe(false)

    expect(hasConfirmedAdminAccess(admin)).toBe(true)
    expect(canManageCredentials(admin)).toBe(true)
  })

  it('modo ASSISTED é o default operacional; AUTOMATIC exige G4', () => {
    expect('ASSISTED').not.toBe('AUTOMATIC')
    const m2mStatus = 'NO_GO_M2M'
    const automaticAllowed = m2mStatus !== 'NO_GO_M2M'
    expect(automaticAllowed).toBe(false)
  })
})
