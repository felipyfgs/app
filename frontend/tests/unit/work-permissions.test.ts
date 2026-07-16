import { describe, expect, it } from 'vitest'
import type { MeUser } from '~/types/api'
import {
  canAdministerWork,
  canCreateWorkProcesses,
  canDownloadWorkEvidence,
  canExecuteWorkTasks,
  canExportWork,
  canManageWorkCatalog,
  canViewWork,
  isWorkOperator
} from '~/utils/permissions'
import { mainDestinations, quickActions } from '~/utils/navigation'

function user(role: MeUser['role'], extras: Partial<MeUser> = {}): MeUser {
  return {
    id: 1,
    name: 'Test',
    email: 't@example.com',
    role,
    office: { id: 1, name: 'Esc', slug: 'esc' },
    two_factor_required: role === 'ADMIN',
    two_factor_confirmed: role === 'ADMIN',
    requires_two_factor_setup: false,
    ...extras
  } as MeUser
}

describe('permissões do módulo Work', () => {
  it('congela matriz ADMIN / OPERATOR / VIEWER', () => {
    const admin = user('ADMIN')
    const op = user('OPERATOR')
    const viewer = user('VIEWER')

    expect(canManageWorkCatalog(admin)).toBe(true)
    expect(canManageWorkCatalog(op)).toBe(false)
    expect(canManageWorkCatalog(viewer)).toBe(false)

    expect(canCreateWorkProcesses(admin)).toBe(true)
    expect(canCreateWorkProcesses(op)).toBe(true)
    expect(canCreateWorkProcesses(viewer)).toBe(false)

    expect(canExecuteWorkTasks(op)).toBe(true)
    expect(canExecuteWorkTasks(viewer)).toBe(false)

    expect(canAdministerWork(admin)).toBe(true)
    expect(canAdministerWork(op)).toBe(false)

    expect(canViewWork(viewer)).toBe(true)
    expect(canExportWork(viewer)).toBe(false)
    expect(canDownloadWorkEvidence(viewer)).toBe(false)
    expect(isWorkOperator(op)).toBe(true)
  })

  it('ADMIN sem 2FA não administra catálogo (alinhado a hasConfirmedAdminAccess)', () => {
    const adminNo2fa = user('ADMIN', {
      two_factor_confirmed: false,
      requires_two_factor_setup: true
    })
    expect(canManageWorkCatalog(adminNo2fa)).toBe(false)
    expect(canAdministerWork(adminNo2fa)).toBe(false)
  })

  it('navegação inclui Trabalho e quick action da fila', () => {
    const op = user('OPERATOR')
    const nav = mainDestinations(op, { path: '/work' })
    const work = nav.find(i => i.id === 'work')
    expect(work?.children?.some(c => c.to === '/work')).toBe(true)
    expect(work?.children?.some(c => c.to === '/work/templates')).toBe(false)

    const admin = user('ADMIN')
    const adminNav = mainDestinations(admin, { path: '/work' })
    const adminWork = adminNav.find(i => i.id === 'work')
    expect(adminWork?.children?.some(c => c.to === '/work/templates')).toBe(true)

    expect(quickActions(op).some(a => a.id === 'work-queue')).toBe(true)
  })
})

describe('helpers de prioridade apresentada', () => {
  it('mapeia riscos para cor de badge', () => {
    const riskColor = (risks?: string[]) => {
      if (!risks?.length) return 'neutral'
      if (risks.includes('EM_MULTA')) return 'error'
      if (risks.includes('ATRASADA')) return 'warning'
      return 'info'
    }
    expect(riskColor(['EM_MULTA', 'ATRASADA'])).toBe('error')
    expect(riskColor(['ATRASADA'])).toBe('warning')
    expect(riskColor([])).toBe('neutral')
  })
})
