import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const APP = resolve(__dirname, '../../app')

function source(path: string): string {
  return readFileSync(resolve(APP, path), 'utf8')
}

describe('largura responsiva do conteúdo autenticado', () => {
  it('centraliza as variantes em DashboardContent', () => {
    const component = source('components/dashboard/DashboardContent.vue')

    expect(component).toContain('comfortable: \'max-w-5xl\'')
    expect(component).toContain('wide: \'max-w-6xl\'')
    expect(component).toContain('full: \'max-w-none\'')
    expect(component).toContain('mx-auto flex w-full min-w-0 flex-col')
  })

  it.each([
    ['pages/conta.vue', 'comfortable'],
    ['pages/work/processes/[id].vue', 'comfortable'],
    ['pages/clients/[id].vue', 'wide'],
    ['pages/monitoring/clients/[clientId].vue', 'wide']
  ])('%s usa a variante %s', (path, width) => {
    const page = source(path)

    expect(page).toContain(`<DashboardContent width="${width}"`)
    expect(page).not.toMatch(/(?:lg:)?max-w-(?:2xl|3xl|4xl)/)
  })

  it.each([
    'pages/index.vue',
    'pages/clients/index.vue',
    'pages/work/calendar.vue',
    'pages/monitoring/mailbox.vue'
  ])('%s permanece fluida conforme seu arquétipo', (path) => {
    expect(source(path)).not.toContain('<DashboardContent')
  })

  it('mantém /admin apenas como entrada para as páginas internas', () => {
    const page = source('pages/admin/index.vue')

    expect(page).toContain('redirect: \'/admin/offices\'')
    expect(page).not.toContain('<DashboardContent')
  })

  it('adapta a largura do console SERPRO ao arquétipo da página interna', () => {
    const page = source('pages/admin/serpro.vue')

    expect(page).toContain('route.query.section')
    expect(page).toContain('section === \'coverage\'')
    expect(page).toContain('section === \'usage\'')
    expect(page).toContain('route.path === \'/admin/serpro/catalog\'')
    expect(page).toContain('route.path === \'/admin/serpro/usage\'')
    expect(page).toContain('<DashboardContent :width="contentWidth"')
  })
})
