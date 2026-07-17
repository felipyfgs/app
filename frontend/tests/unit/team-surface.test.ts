/**
 * Contrato da superfície de equipe (/conta/equipe → settings/team.vue).
 * Cards, filtros locais (nome/e-mail + papel) e estados distintos.
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import type { MeUser, OfficeMember } from '../../app/types/api'
import { filterTeamMembers, normalizeTeamSearch } from '../../app/utils/team-filter'
import { canManageOfficeTeam } from '../../app/utils/permissions'

const APP = resolve(__dirname, '../../app')

const page = readFileSync(resolve(APP, 'pages/settings/team.vue'), 'utf8')
const list = readFileSync(resolve(APP, 'components/settings/TeamList.vue'), 'utf8')
const alias = readFileSync(resolve(APP, 'pages/conta/equipe.vue'), 'utf8')
const filterUtil = readFileSync(resolve(APP, 'utils/team-filter.ts'), 'utf8')

function member(partial: Partial<OfficeMember> & Pick<OfficeMember, 'id' | 'role' | 'status'>): OfficeMember {
  return {
    name: partial.name ?? `Membro ${partial.id}`,
    email: partial.email ?? `m${partial.id}@example.com`,
    is_active: partial.is_active ?? partial.status === 'active',
    ...partial
  }
}

function user(partial: Partial<MeUser> = {}): MeUser {
  return {
    id: 1,
    name: 'Teste',
    email: 't@example.com',
    two_factor_confirmed: true,
    two_factor_required: true,
    requires_two_factor_setup: false,
    is_platform_admin: false,
    office: { id: 1, name: 'Escritório', slug: 'escritorio' },
    role: 'ADMIN',
    ...partial
  }
}

const sample: OfficeMember[] = [
  member({ id: 1, name: 'Ana Admin', email: 'ana@office.com', role: 'ADMIN', status: 'active' }),
  member({ id: 2, name: 'Bruno Operador', email: 'bruno@office.com', role: 'OPERATOR', status: 'active' }),
  member({ id: 3, name: 'Carla Viewer', email: 'carla@other.com', role: 'VIEWER', status: 'pending', is_active: false }),
  member({ id: 4, name: 'Diego', email: 'diego@office.com', role: 'OPERATOR', status: 'deactivated', is_active: false })
]

describe('superfície de equipe — arquétipo e rota', () => {
  it('mantém rota canônica /conta/equipe sobre settings/team', () => {
    expect(alias).toContain('settings/team.vue')
    expect(alias).toContain('OfficeTeamPage')
    expect(page).toContain('data-testid="settings-team"')
    expect(page).toMatch(/Arquétipo:.*members\.vue/)
    expect(list).toMatch(/MembersList\.vue/)
  })

  it('preserva ação primária, vagas e reconfirmação de senha', () => {
    expect(page).toContain('SettingsTeamAddModal')
    expect(page).toContain('team-seats')
    expect(page).toContain('seatsLabel')
    expect(page).toContain('team-action-reconfirm')
    expect(page).toContain('confirmPassword')
  })

  it('não introduz telefone, avatar persistido ou office_id do cliente', () => {
    expect(page + list).not.toMatch(/telefone|phone|avatar_url|avatarUrl/i)
    expect(page).not.toMatch(/office_id/)
    expect(page).toContain('api.office.members.list()')
  })
})

describe('superfície de equipe — filtro local', () => {
  it('normaliza busca sem RegExp da entrada', () => {
    expect(normalizeTeamSearch('  Ana  ')).toBe('ana')
    expect(filterUtil).not.toMatch(/new RegExp/)
    expect(page).toContain('filterTeamMembers')
    expect(page).not.toMatch(/new RegExp/)
  })

  it('combina pesquisa por nome/e-mail com filtro de papel', () => {
    expect(filterTeamMembers(sample, '', 'ALL')).toHaveLength(4)
    expect(filterTeamMembers(sample, 'bruno', 'ALL').map(m => m.id)).toEqual([2])
    expect(filterTeamMembers(sample, 'office.com', 'OPERATOR').map(m => m.id)).toEqual([2, 4])
    expect(filterTeamMembers(sample, 'ana', 'VIEWER')).toHaveLength(0)
    expect(filterTeamMembers(sample, '', 'ADMIN').map(m => m.id)).toEqual([1])
    // caracteres especiais da entrada não quebram (não são RegExp)
    expect(filterTeamMembers(sample, 'a+b(c)', 'ALL')).toHaveLength(0)
    expect(filterTeamMembers(sample, 'CARLA', 'VIEWER').map(m => m.id)).toEqual([3])
  })

  it('expõe controle Todos + papéis e data-testids de filtro', () => {
    expect(page).toContain('team-role-filter')
    expect(page).toContain('team-search')
    expect(page).toContain('label: \'Todos\'')
    expect(page).toContain('value: \'ALL\'')
    expect(page).toContain('value: \'ADMIN\'')
    expect(page).toContain('value: \'OPERATOR\'')
    expect(page).toContain('value: \'VIEWER\'')
    expect(page).toMatch(/aria-label="Filtrar por papel"/)
    expect(page).toMatch(/aria-label="Buscar por nome ou e-mail"/)
  })
})

describe('superfície de equipe — grade de cards', () => {
  it('usa grade responsiva de cards com identidade e status', () => {
    expect(list).toContain('data-testid="team-list"')
    expect(list).toContain('team-card-')
    expect(list).toMatch(/grid grid-cols-1/)
    expect(list).toMatch(/sm:grid-cols-2/)
    expect(list).toMatch(/xl:grid-cols-3/)
    expect(list).toContain('member.name')
    expect(list).toContain('member.email')
    expect(list).toContain('member.role')
    expect(list).toContain('member.status')
    expect(list).toContain('team-card-status')
    expect(list).not.toContain('divide-y')
  })

  it('preserva seletor de papel, menu e labels acessíveis condicionados a canMutate', () => {
    expect(list).toContain('canMutate')
    expect(list).toContain('USelect')
    expect(list).toContain('UDropdownMenu')
    expect(list).toContain('Regenerar acesso')
    expect(list).toContain('Desativar')
    expect(list).toContain('Reativar')
    expect(list).toMatch(/aria-label=.*Papel de/)
    expect(list).toMatch(/aria-label=.*Ações de/)
    expect(list).toContain('actingId')
    expect(list).toContain('team-actions-')
    // mutações só quando canMutate
    expect(list).toMatch(/v-if="props\.canMutate && menuItems/)
    expect(list).toContain('canChangeRole')
  })
})

describe('superfície de equipe — estados', () => {
  it('distingue loading, vazio, nenhum resultado, erro e acesso negado', () => {
    expect(page).toContain('team-loading')
    expect(page).toContain('team-empty')
    expect(page).toContain('team-search-empty')
    expect(page).toContain('team-load-error')
    expect(page).toContain('team-forbidden')
    expect(page).toContain('Nenhum membro')
    expect(page).toContain('Nenhum resultado')
    expect(page).toContain('emptyFilterDescription')
    expect(page).toContain('Tentar novamente')
    // skeleton em grade de cards (não lista linear)
    expect(page).toMatch(/grid grid-cols-1[\s\S]*data-testid="team-loading"/)
  })

  it('evita overflow horizontal e empilha controles no viewport estreito', () => {
    expect(page).toContain('overflow-x-hidden')
    expect(page).toContain('min-w-0')
    expect(page).toMatch(/flex-col gap-2 sm:flex-row/)
    expect(list).toContain('min-w-0')
    expect(list).toMatch(/grid-cols-1/)
  })
})

describe('superfície de equipe — autorização', () => {
  it('somente ADMIN real do office gerencia equipe; PLATFORM_ADMIN puro não', () => {
    expect(canManageOfficeTeam(user({ role: 'ADMIN' }))).toBe(true)
    expect(canManageOfficeTeam(user({ role: 'OPERATOR' }))).toBe(false)
    expect(canManageOfficeTeam(user({ role: 'VIEWER' }))).toBe(false)
    expect(canManageOfficeTeam(user({
      role: null,
      office: null,
      is_platform_admin: true
    }))).toBe(false)
    expect(page).toContain('canManageOfficeTeam')
    expect(page).toContain(':can-mutate="canMutate"')
    expect(page).toContain('v-if="canMutate && !forbidden"')
  })
})
