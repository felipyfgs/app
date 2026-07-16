import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { flattenDestinations, mainDestinations } from '../../app/utils/navigation'
import type { MeUser } from '../../app/types/api'

const APP_ROOT = resolve(__dirname, '../../app')

function user(role: MeUser['role'], confirmed = true): MeUser {
  return {
    id: 1,
    name: 'Teste',
    email: 't@example.com',
    two_factor_confirmed: confirmed,
    two_factor_required: true,
    requires_two_factor_setup: false,
    office: { id: 1, name: 'Escritório', slug: 'escritorio' },
    role
  }
}

describe('superfície CT-e no catálogo', () => {
  it('middleware redireciona /settings/cte para o catálogo e remove exceção de gate', () => {
    const middleware = readFileSync(resolve(APP_ROOT, 'middleware/auth.global.ts'), 'utf8')
    expect(middleware).toContain('to.path === \'/settings/cte\'')
    expect(middleware).toContain('path: \'/docs/catalog\'')
    expect(middleware).toContain('kind: \'CTE\'')
    // Gate normal de /settings sem liberar CT-e como página Settings.
    expect(middleware).toMatch(/to\.path\.startsWith\('\/settings'\)/)
    expect(middleware).not.toMatch(/to\.path !== '\/settings\/cte'/)
  })

  it('alias /settings/cte só redireciona (sem shell Settings)', () => {
    const page = readFileSync(resolve(APP_ROOT, 'pages/settings/cte.vue'), 'utf8')
    expect(page).toContain('path: \'/docs/catalog\'')
    expect(page).toContain('kind: \'CTE\'')
    expect(page).toContain('replace: true')
    expect(page).not.toContain('cte-onboarding-page')
    expect(page).not.toContain('api.cte.onboarding')
  })

  it('settings layout não lista CT-e', () => {
    const settings = readFileSync(resolve(APP_ROOT, 'pages/settings.vue'), 'utf8')
    expect(settings).not.toContain('/settings/cte')
    expect(settings).not.toContain('label: \'CT-e\'')
    expect(settings).not.toContain('isCteReadOnlyPage')
  })

  it('NotesWorkspace monta contexto CT-e no catálogo', () => {
    const workspace = readFileSync(resolve(APP_ROOT, 'components/notes/NotesWorkspace.vue'), 'utf8')
    expect(workspace).toContain('NotesCteCatalogContext')
    expect(workspace).toContain('showCteContext')
    expect(workspace).toContain('filters.kind === \'CTE\'')
    expect(workspace).toContain('hydrateFiltersFromQuery')
    expect(workspace).toContain('sessionEpoch')
  })

  it('contexto CT-e não carrega saúde de cursor nem material sensível', () => {
    const ctx = readFileSync(resolve(APP_ROOT, 'components/notes/NotesCteCatalogContext.vue'), 'utf8')
    expect(ctx).toContain('api.cte.onboarding')
    expect(ctx).toContain('api.cte.pending')
    expect(ctx).not.toContain('api.cte.health')
    expect(ctx).toContain('sessionEpoch')
    // Pode mencionar "nunca PFX" na UI; não embute material criptográfico real.
    expect(ctx).not.toMatch(/-----BEGIN (RSA |EC )?PRIVATE KEY-----/)
    expect(ctx).not.toMatch(/-----BEGIN CERTIFICATE-----/)
    expect(ctx).not.toContain('vault_object')
    expect(ctx).toMatch(/nunca PFX|sem material sensível/i)
    expect(ctx).toContain('cte-catalog-context')
    expect(ctx).toContain('cte-office-cnpj')
    expect(ctx).toContain('cte-pending-panel')
  })

  it('sincronizações apontam deep-link documental para o catálogo', () => {
    const syncs = readFileSync(resolve(APP_ROOT, 'pages/syncs/index.vue'), 'utf8')
    expect(syncs).toContain('/docs/catalog?kind=CTE')
    expect(syncs).not.toContain('to="/settings/cte"')
  })

  it('navegação não expõe /settings/cte em nenhum papel', () => {
    for (const role of ['VIEWER', 'OPERATOR', 'ADMIN'] as const) {
      const flat = flattenDestinations(mainDestinations(user(role, true)))
      expect(flat.map(d => d.to)).not.toContain('/settings/cte')
      expect(flat.map(d => d.id)).not.toContain('settings-cte')
      expect(flat.map(d => d.id)).not.toContain('cte-onboarding')
    }
  })
})
