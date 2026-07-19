import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const root = (...parts: string[]) => resolve(process.cwd(), ...parts)

/** Listas N1 com campos primários de cards configurados. */
const MOBILE_CARD_SURFACES = [
  'app/pages/exports.vue',
  'app/pages/syncs.vue',
  'app/pages/closing.vue',
  'app/pages/health.vue',
  'app/pages/docs/imports/index.vue',
  'app/pages/docs/imports/[id].vue',
  'app/pages/work/processes/index.vue',
  'app/pages/work/templates/index.vue',
  'app/pages/admin/offices/index.vue',
  'app/pages/admin/serpro/catalog.vue',
  'app/pages/admin/serpro/contracts.vue',
  'app/pages/admin/serpro/usage.vue',
  'app/pages/settings/usage.vue',
  'app/components/docs/Catalog.vue',
  'app/components/docs/ByClient.vue',
  'app/components/docs/Detail.vue',
  'app/components/clients/ClientCatalogList.vue',
  'app/components/monitoring/ModuleDataTable.vue'
] as const

/** Mestre–detalhe que devem usar slideover/stack em &lt; lg. */
const SPLIT_SURFACES = [
  'app/pages/monitoring/mailbox.vue',
  'app/components/work/WorkQueueWorkspace.vue',
  'app/pages/work/calendar.vue'
] as const

function walkVueFiles(dir: string, out: string[] = []): string[] {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    const st = statSync(full)
    if (st.isDirectory()) {
      walkVueFiles(full, out)
      continue
    }
    if (entry.endsWith('.vue')) out.push(full)
  }
  return out
}

describe('painel-responsivo-mobile-gate', () => {
  it('kit shell expõe caminho de cards mobile', () => {
    const dataTable = readFileSync(root('app/components/shell/DataTable.vue'), 'utf8')
    const mobileCards = readFileSync(root('app/components/shell/MobileCards.vue'), 'utf8')
    const footer = readFileSync(root('app/components/shell/TableFooter.vue'), 'utf8')
    const tableUi = readFileSync(root('app/utils/table-ui.ts'), 'utf8')

    expect(dataTable).toContain('ShellMobileCards')
    expect(dataTable).toContain('mobileCards')
    expect(mobileCards).toContain('primaryColumnId')
    expect(tableUi).toContain('flex-col')
    expect(tableUi).toContain('sm:flex-row')
    expect(footer).toContain('smaller(\'sm\')')
    expect(footer).toContain('list-table-footer-controls')
  })

  it('catálogo N1 configura primary/status/summary nos ShellDataTable', () => {
    for (const rel of MOBILE_CARD_SURFACES) {
      const source = readFileSync(root(rel), 'utf8')
      expect(source, rel).toContain('ShellDataTable')
      expect(source, `${rel} primary`).toMatch(/primary-column-id|primaryColumnId/)
      expect(source, `${rel} status ou summary`).toMatch(
        /status-column-id|statusColumnId|summary-column-ids|summaryColumnIds/
      )
    }
  })

  it('splits N2 usam breakpoint lg + slideover', () => {
    for (const rel of SPLIT_SURFACES) {
      const source = readFileSync(root(rel), 'utf8')
      expect(source, rel).toMatch(/smaller\(['"]lg['"]\)|hidden lg:flex|lg:hidden/)
      expect(source, `${rel} slideover`).toContain('USlideover')
    }
  })

  it('conta segue arquétipo settings (toolbar UNavigationMenu + NuxtPage)', () => {
    const conta = readFileSync(root('app/pages/conta.vue'), 'utf8')
    expect(conta).toContain('ShellSettingsShell')
    expect(conta).toContain('UNavigationMenu')
    expect(conta).toContain('account-section-navigation')
    expect(conta).toContain('<NuxtPage')
    expect(conta).toContain('width="comfortable"')
    expect(conta).not.toContain('AccountDetailTabNav')
  })

  it('detalhe do cliente segue arquétipo settings (4 destinos + NuxtPage)', () => {
    const client = readFileSync(root('app/pages/clients/[id].vue'), 'utf8')
    expect(client).toContain('ShellSettingsShell')
    expect(client).toContain('UNavigationMenu')
    expect(client).toContain('client-section-navigation')
    expect(client).not.toContain('client-hub-navigation')
    expect(client).toContain('<NuxtPage')
    expect(client).toContain('width="comfortable"')
    expect(client).not.toContain('ClientDetailTabNav')
    expect(client).not.toContain('ClientDetailAside')
  })

  it('detalhe fiscal do cliente segue arquétipo settings (toolbar plana)', () => {
    const fiscal = readFileSync(root('app/pages/monitoring/clients/[clientId].vue'), 'utf8')
    expect(fiscal).toContain('ShellSettingsShell')
    expect(fiscal).toContain('UNavigationMenu')
    expect(fiscal).toContain('monitoring-client-section-navigation')
    expect(fiscal).toContain('width="wide"')
    expect(fiscal).not.toContain('SectionNavigation')
    expect(fiscal).not.toContain('section-nav-subtabs')
  })

  it('folhas do cliente e settings Conta usam ShellSectionHeader (chrome settings)', () => {
    const leafPages = [
      'app/pages/clients/[id]/cadastro.vue',
      'app/pages/clients/[id]/contato.vue',
      'app/pages/clients/[id]/departamento.vue',
      'app/pages/clients/[id]/configuracao.vue',
      'app/pages/settings/team.vue',
      'app/pages/settings/departments.vue',
      'app/pages/settings/subscription.vue',
      'app/pages/settings/usage.vue'
    ]
    for (const rel of leafPages) {
      const source = readFileSync(root(rel), 'utf8')
      expect(source, rel).toContain('ShellSectionHeader')
    }

    const team = readFileSync(root('app/pages/settings/team.vue'), 'utf8')
    expect(team).toContain('ShellFilterToolbarLite')
  })

  it('organização Nuxt UI: 4 páginas densas + accordion cadastro/config/escritório', () => {
    const registration = readFileSync(root('app/components/clients/ClientRegistration.vue'), 'utf8')
    expect(registration).toContain('ShellPanelAccordion')
    expect(registration).toContain('panel === \'all\'')

    const config = readFileSync(root('app/components/clients/ClientConfigPanel.vue'), 'utf8')
    expect(config).toContain('ShellPanelAccordion')
    expect(config).toContain('USwitch')

    const office = readFileSync(root('app/components/settings/OfficeSettingsPanel.vue'), 'utf8')
    expect(office).toContain('ShellPanelAccordion')
    expect(office).toContain('UStepper')

    const tabs = readFileSync(root('app/utils/client-detail-tabs.ts'), 'utf8')
    expect(tabs).toContain('\'contato\'')
    expect(tabs).toContain('\'departamento\'')
    expect(tabs).toContain('\'configuracao\'')
    expect(tabs).not.toContain('value: \'fiscal\'')
  })

  it('DocsWorkspace permanece min-w-0 e detalhe em modal', () => {
    const workspace = readFileSync(root('app/components/docs/Workspace.vue'), 'utf8')
    expect(workspace).toContain('min-w-0')
    expect(workspace).toContain('DocsDetailModal')
    expect(workspace).toContain('DocsCatalog')
  })

  it('pages autenticadas com chrome canônico incluem collapse da sidebar', () => {
    const pagesDir = root('app/pages')
    const files = walkVueFiles(pagesDir)
    const authenticatedCandidates = files.filter((full) => {
      const rel = relative(root('app'), full).replace(/\\/g, '/')
      if (rel.includes('/two-factor')) return false
      const base = rel.split('/').pop() || ''
      // Auth/public surfaces sem shell autenticado
      if ([
        'login.vue',
        'first-access.vue',
        'activate.vue',
        'onboarding.vue',
        'two-factor-challenge.vue'
      ].includes(base)) {
        return false
      }
      const source = readFileSync(full, 'utf8')
      // Só pages que montam navbar/painel próprio (não redirects puros)
      const hasChrome = /ShellPageNavbar|UDashboardNavbar|UDashboardPanel|ShellPagePanel|ShellSettingsShell/.test(source)
      if (!hasChrome) return false
      // Redirect-only meta
      if (/definePageMeta\(\s*\{[^}]*redirect:/s.test(source) && !hasChrome) return false
      return true
    })

    expect(authenticatedCandidates.length).toBeGreaterThan(10)

    const missing: string[] = []
    for (const full of authenticatedCandidates) {
      const source = readFileSync(full, 'utf8')
      const hasCollapse = source.includes('UDashboardSidebarCollapse')
        || source.includes('ShellPageNavbar') // ShellPageNavbar já embute collapse
        || source.includes('ShellSettingsShell') // SettingsShell → ShellPageNavbar
      if (!hasCollapse) {
        missing.push(relative(root('app'), full).replace(/\\/g, '/'))
      }
    }

    expect(missing, `Pages sem collapse: ${missing.join(', ')}`).toEqual([])
  })
})
