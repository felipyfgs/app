#!/usr/bin/env node
/**
 * Gate estrutural de fidelidade ao Nuxt UI Dashboard Template (0f30c09).
 *
 * Uso:
 *   node frontend/scripts/template-fidelity-gate.mjs
 *   node frontend/scripts/template-fidelity-gate.mjs --json
 *
 * Falha (exit 1) se:
 * - inventário de pages/ divergir da parity-matrix
 * - página usa wrapper de chrome proibido pela change
 * - UTable com :ui inventado sem preset de table-ui
 * - página visual não expande UDashboardPanel diretamente ou pelo pai Nuxt
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const REPO = path.resolve(__dirname, '../..')
const PAGES = path.join(REPO, 'frontend/app/pages')
const APP = path.join(REPO, 'frontend/app')
const MATRIX = path.join(REPO, 'openspec/changes/ui-template-fidelity-total/parity-matrix.md')

const FORBIDDEN_CHROME = [
  ['ShellListShell', 'components/shell/ListShell.vue'],
  ['ShellStickyTableFilters', 'components/shell/StickyTableFilters.vue'],
  ['ShellInfiniteTableLoader', 'components/shell/InfiniteTableLoader.vue'],
  ['ShellTableFooter', 'components/shell/TableFooter.vue'],
  ['ShellKpiStrip', 'components/shell/KpiStrip.vue'],
  ['MonitoringModuleTable', 'components/monitoring/ModuleTable.vue'],
  ['MonitoringModuleToolbar', 'components/monitoring/ModuleToolbar.vue'],
  ['MonitoringKpiStrip', 'components/monitoring/KpiStrip.vue'],
  ['DocsWorkspace', 'components/docs/Workspace.vue']
]

const FORBIDDEN_TABLE_PRESETS = [
  'DASHBOARD_TABLE_UI',
  'DENSE_DASHBOARD_TABLE_UI',
  'COMPACT_DASHBOARD_TABLE_UI'
]

const asJson = process.argv.includes('--json')
const selfTest = process.argv.includes('--self-test')

function walkVue(dir) {
  const out = []
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) out.push(...walkVue(full))
    else if (entry.name.endsWith('.vue')) out.push(full)
  }
  return out
}

function relApp(file) {
  return path.relative(APP, file).split(path.sep).join('/')
}

function parseMatrixFiles(md) {
  const files = new Set()
  for (const line of md.split('\n')) {
    const m = line.match(/^\| `(pages\/[^`]+)` \|/)
    if (m) files.add(m[1])
  }
  return files
}

function parseMatrixBundles(md) {
  const bundles = new Map()
  for (const line of md.split('\n')) {
    const cells = line.split('|').map(cell => cell.trim())
    const file = cells[1]?.match(/^`(pages\/[^`]+)`$/)?.[1]
    const bundle = cells[3]?.match(/^`([^`]+)`$/)?.[1]
    if (file && bundle) bundles.set(file, bundle)
  }
  return bundles
}

function read(file) {
  return fs.readFileSync(file, 'utf8')
}

function isAuth(text) {
  return /layout:\s*['"]auth['"]/.test(text)
}

function isRedirect(text) {
  return text.includes('navigateTo') && !text.includes('UDashboard') && text.length < 600
}

function hasChrome(text) {
  return text.includes('UDashboardPanel')
}

function findForbiddenChrome(file, text) {
  const violations = []
  for (const [identifier, componentPath] of FORBIDDEN_CHROME) {
    if (text.includes(identifier) || text.includes(componentPath)) {
      violations.push(`${relApp(file)}: chrome proibido ${identifier} (${componentPath})`)
    }
  }
  return violations
}

function findNonCanonicalAlerts(file, text) {
  const violations = []
  const alertBlocks = text.match(/<UAlert\b[\s\S]*?(?:\/>|<\/UAlert>)/g) || []
  if (alertBlocks.some(block => /(?:^|\s):?description=/.test(block))) {
    violations.push(`${relApp(file)}: UAlert com description explicativa; use título curto e acionável`)
  }
  if (text.includes('FiscalDemoBanner')) {
    violations.push(`${relApp(file)}: banner demonstrativo persistente fora do template`)
  }
  return violations
}

/**
 * Sobe a árvore de rotas Nuxt: pages/foo.vue envolve pages/foo/**.
 */
function parentShellFiles(pageFile) {
  const parents = []
  let dir = path.dirname(pageFile)
  while (dir.startsWith(PAGES) && dir !== PAGES) {
    const base = path.basename(dir)
    const sibling = path.join(path.dirname(dir), `${base}.vue`)
    if (fs.existsSync(sibling)) parents.push(sibling)
    dir = path.dirname(dir)
  }
  // also root-level layouts don't apply here
  return parents
}

function pageHasChromeWithParents(pageFile, text) {
  if (hasChrome(text)) return { ok: true, via: 'self' }
  for (const parent of parentShellFiles(pageFile)) {
    const pText = read(parent)
    if (hasChrome(pText)) return { ok: true, via: relApp(parent) }
  }
  return { ok: false, via: null }
}

function findLooseTableUi(file, text) {
  const violations = []
  if (!text.includes('UTable')) return violations
  for (const preset of FORBIDDEN_TABLE_PRESETS) {
    if (text.includes(preset)) {
      violations.push(`${relApp(file)}: preset de apresentação proibido ${preset}`)
    }
  }
  return violations
}

function findListContractIssues(file, text) {
  const rel = relApp(file)
  const violations = []
  const requiredTableUi = [
    `base: 'table-fixed border-separate border-spacing-0'`,
    `thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none'`,
    `tbody: '[&>tr]:last:[&>td]:border-b-0'`,
    `th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r'`,
    `td: 'border-b border-default'`,
    `separator: 'h-0'`
  ]

  if (!text.includes('<UTable')) violations.push(`${rel}: LIST sem UTable`)
  for (const token of requiredTableUi) {
    if (!text.includes(token)) violations.push(`${rel}: LIST sem :ui literal de customers.vue (${token})`)
  }
  if (!/<template #body>[\s\S]*<(?:UInput|USelect|UDropdownMenu)/.test(text)) {
    violations.push(`${rel}: LIST sem utilitários no body`)
  }
  if (!text.includes('border-t border-default pt-4')) violations.push(`${rel}: LIST sem footer literal`)
  if (!text.includes('<UPagination')) violations.push(`${rel}: LIST sem UPagination`)
  if (!/:total=/.test(text) && !/\{\{[^}]*total/.test(text)) violations.push(`${rel}: LIST sem total no footer`)
  return violations
}

function runSelfTest() {
  const fixture = path.join(PAGES, 'fixture.vue')
  const validList = `
    <template #body>
      <UInput />
      <UTable :ui="{
        base: 'table-fixed border-separate border-spacing-0',
        thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
        tbody: '[&>tr]:last:[&>td]:border-b-0',
        th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
        td: 'border-b border-default',
        separator: 'h-0'
      }" />
      <div class="border-t border-default pt-4">{{ total }}<UPagination :total="total" /></div>
    </template>`
  const checks = {
    rejectsForbiddenChrome: findForbiddenChrome(fixture, '<DocsWorkspace />').length === 1,
    rejectsAlertDescription: findNonCanonicalAlerts(fixture, '<UAlert title="Info" description="Texto longo" />').length === 1,
    rejectsDemoBanner: findNonCanonicalAlerts(fixture, '<FiscalDemoBanner />').length === 1,
    acceptsLiteralList: findListContractIssues(fixture, validList).length === 0,
    rejectsPresentationPreset: findLooseTableUi(fixture, '<UTable :ui="DASHBOARD_TABLE_UI" />').length === 1
  }
  const issues = Object.entries(checks)
    .filter(([, passed]) => !passed)
    .map(([name]) => `Autoteste falhou: ${name}`)
  const result = { ok: issues.length === 0, issues, checks }
  console.log(JSON.stringify(result, null, 2))
  process.exit(result.ok ? 0 : 1)
}

function main() {
  const issues = []
  const warnings = []

  const pageFiles = walkVue(PAGES).map(f => relApp(f)).sort()
  const hasMatrix = fs.existsSync(MATRIX)
  const matrixMd = hasMatrix ? read(MATRIX) : ''
  const matrixFiles = parseMatrixFiles(matrixMd)
  const matrixBundles = parseMatrixBundles(matrixMd)

  // Documentação/OpenSpec reiniciada: matriz é opcional até a nova docs existir.
  if (!hasMatrix) {
    warnings.push(`Matriz ausente (docs reiniciadas): ${path.relative(REPO, MATRIX)}`)
  } else {
    for (const f of pageFiles) {
      if (!matrixFiles.has(f)) issues.push(`Página fora da matriz: ${f}`)
    }
    for (const f of matrixFiles) {
      if (!pageFiles.includes(f)) issues.push(`Entrada fantasma na matriz: ${f}`)
    }
  }

  // structural chrome for every non-auth non-redirect page
  for (const abs of walkVue(PAGES)) {
    const text = read(abs)
    const rel = relApp(abs)
    if (isAuth(text) || isRedirect(text)) continue
    issues.push(...findForbiddenChrome(abs, text))
    issues.push(...findNonCanonicalAlerts(abs, text))
    // child empty placeholders under parent (mailbox empty) still need parent chrome
    const chrome = pageHasChromeWithParents(abs, text)
    if (!chrome.ok) {
      // allow pure content children that only render forms/cards without panel if parent has shell
      // already checked parents — fail
      issues.push(`Sem UDashboardPanel/casca (nem no pai): ${rel}`)
    }
    issues.push(...findLooseTableUi(abs, text))
    if (hasMatrix && matrixBundles.get(rel) === 'LIST') {
      issues.push(...findListContractIssues(abs, text))
    }
  }

  // scan components that use UTable
  const componentsDir = path.join(APP, 'components')
  if (fs.existsSync(componentsDir)) {
    for (const abs of walkVue(componentsDir)) {
      const text = read(abs)
      issues.push(...findLooseTableUi(abs, text))
      issues.push(...findNonCanonicalAlerts(abs, text))
    }
  }

  const result = {
    ok: issues.length === 0,
    pages: pageFiles.length,
    matrixEntries: matrixFiles.size,
    issues,
    warnings
  }

  if (asJson) {
    console.log(JSON.stringify(result, null, 2))
  } else {
    console.log(`Template fidelity gate — pages=${result.pages} matrix=${result.matrixEntries}`)
    if (warnings.length) {
      console.log('\nWarnings:')
      for (const w of warnings) console.log(`  ⚠ ${w}`)
    }
    if (issues.length) {
      console.log('\nIssues:')
      for (const i of issues) console.log(`  ✗ ${i}`)
      console.log(`\nFAIL (${issues.length} issue(s))`)
    } else {
      console.log('\nPASS')
    }
  }

  process.exit(issues.length ? 1 : 0)
}

if (selfTest) runSelfTest()
else main()
