/**
 * Router HTTP de fixtures do hub de monitoramento fiscal para Playwright.
 * Dados determinísticos vêm de `fiscal-fixtures.ts` (contrato + builders).
 * Sanitizadas: sem PFX, PEM, IDs de cofre, Consumer Secret, tokens reais ou XML fiscal.
 */
import type { Route } from '@playwright/test'
import type { FiscalPortfolioModuleKey } from '../../../app/types/fiscal-modules'
import {
  FISCAL_FIXTURE_NOW,
  FISCAL_MAILBOX_MESSAGE_ID,
  buildDeclarations,
  buildDeclarationsCatalog,
  buildDeclarationsSummary,
  buildFiscalCategories,
  buildFiscalFindings,
  buildFiscalPendingItems,
  buildFiscalRuns,
  buildFiscalSnapshots,
  buildFgtsCompetences,
  buildFgtsCoverage,
  buildFgtsEvents,
  buildGuideDetail,
  buildGuides,
  buildInstallmentModalities,
  buildInstallmentOrders,
  buildInstallmentParcels,
  buildMailboxMessageDetail,
  buildMailboxMessages,
  buildSimplesMeiCatalog,
  buildSitfisView,
  fiscalModuleClientsResponse,
  fiscalModuleOverviewResponse,
  isPortfolioModulePathSegment,
  type FiscalListScenario
} from './fiscal-fixtures'

export { FISCAL_MAILBOX_MESSAGE_ID as MAILBOX_MESSAGE_ID }

const DEMO_MARK = 'DEMONSTRAÇÃO — SEM VALIDADE FISCAL'

async function fulfill(route: Route, body: unknown, status = 200) {
  await route.fulfill({
    status,
    contentType: 'application/json; charset=utf-8',
    body: JSON.stringify(body)
  })
}

function pageMeta(total: number, perPage = 15) {
  return {
    current_page: 1,
    last_page: Math.max(1, Math.ceil(total / perPage) || 1),
    per_page: perPage,
    total
  }
}

async function maybeSlow(scenario: FiscalListScenario) {
  if (scenario === 'slow') {
    await new Promise(resolve => setTimeout(resolve, 1500))
  }
}

/**
 * Intercepta rotas /api/v1/fiscal/* necessárias às páginas de monitoramento.
 * @returns true se a requisição foi atendida.
 */
export async function tryFulfillMonitoringApi(
  route: Route,
  pathname: string,
  method: string,
  listScenario: FiscalListScenario = 'ready',
  activeOfficeId = 1
): Promise<boolean> {
  if (!pathname.includes('/api/v1/fiscal/')) {
    return false
  }

  // Office sentinela (id≠1): mesmos cenários com nomes/ids do tenant B (isolamento).
  const tenant = { officeId: activeOfficeId }
  const scenario: FiscalListScenario = listScenario

  const moduleMatch = pathname.match(/\/api\/v1\/fiscal\/modules\/([^/]+)\/(overview|clients)$/)
  if (moduleMatch) {
    const segment = decodeURIComponent(moduleMatch[1]!)
    const action = moduleMatch[2]
    if (!isPortfolioModulePathSegment(segment)) {
      await fulfill(route, { message: 'Módulo desconhecido na fixture.' }, 404)
      return true
    }
    const module = segment as FiscalPortfolioModuleKey
    await maybeSlow(scenario)
    if (scenario === 'error') {
      const msg = action === 'overview'
        ? fiscalModuleOverviewResponse(module, 'error', tenant)
        : fiscalModuleClientsResponse(module, 'error', tenant)
      await fulfill(route, msg, 503)
      return true
    }
    if (action === 'overview') {
      await fulfill(route, fiscalModuleOverviewResponse(module, scenario, tenant))
      return true
    }
    await fulfill(route, fiscalModuleClientsResponse(module, scenario, tenant))
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/pending-items') && method === 'GET') {
    await maybeSlow(scenario)
    if (scenario === 'error') {
      await fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return true
    }
    const data = scenario === 'empty' ? [] : buildFiscalPendingItems(tenant)
    await fulfill(route, { data, meta: pageMeta(data.length), total: data.length })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/findings') && method === 'GET') {
    await maybeSlow(scenario)
    if (scenario === 'error') {
      await fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return true
    }
    const data = scenario === 'empty' ? [] : buildFiscalFindings(tenant)
    await fulfill(route, { data, meta: pageMeta(data.length), total: data.length })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/runs') && method === 'GET') {
    await maybeSlow(scenario)
    if (scenario === 'error') {
      await fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return true
    }
    const data = scenario === 'empty' ? [] : buildFiscalRuns(tenant)
    await fulfill(route, { data, meta: pageMeta(data.length), total: data.length })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/runs') && method === 'POST') {
    const run = buildFiscalRuns(tenant)[0]!
    await fulfill(route, {
      data: { ...run, id: 299, status: 'PENDING', correlation_id: 'DEMO_RUN_ENQUEUED' }
    }, 201)
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/categories') && method === 'GET') {
    await fulfill(route, { data: buildFiscalCategories() })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/category-links') && method === 'GET') {
    await fulfill(route, { data: [] })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/category-links/batch') && method === 'POST') {
    await fulfill(route, { data: { created: 1, errors: [] } })
    return true
  }

  const registrationRows = [{
    id: 701,
    client_id: 1,
    link_key: 'VINCULO-DEMO-001',
    status: 'ACTIVE',
    evidence_version: 'e'.repeat(32),
    source_provenance: 'SERPRO_REAL',
    is_simulated: false,
    refreshed_at: FISCAL_FIXTURE_NOW
  }]
  if (pathname.endsWith('/api/v1/fiscal/registrations') && method === 'GET') {
    await maybeSlow(scenario)
    if (scenario === 'error') {
      await fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return true
    }
    await fulfill(route, { data: scenario === 'empty' ? [] : registrationRows, meta: pageMeta(registrationRows.length, 25) })
    return true
  }
  if (/\/api\/v1\/fiscal\/clients\/\d+\/registrations$/.test(pathname) && method === 'GET') {
    await fulfill(route, { data: { client_id: 1, links: scenario === 'empty' ? [] : registrationRows } })
    return true
  }
  if (/\/api\/v1\/fiscal\/clients\/\d+\/registrations\/refresh$/.test(pathname) && method === 'POST') {
    await fulfill(route, { data: { queued: true, client_id: 1 } }, 202)
    return true
  }

  const taxProcessRows = [{
    id: 801,
    client_id: 1,
    process_number: 'PROC-DEMO-001',
    status: 'OPEN',
    evidence_version: 'f'.repeat(32),
    source_provenance: 'SERPRO_REAL',
    is_simulated: false,
    refreshed_at: FISCAL_FIXTURE_NOW
  }]
  if (pathname.endsWith('/api/v1/fiscal/tax-processes') && method === 'GET') {
    await maybeSlow(scenario)
    if (scenario === 'error') {
      await fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return true
    }
    await fulfill(route, { data: scenario === 'empty' ? [] : taxProcessRows, meta: pageMeta(taxProcessRows.length, 25) })
    return true
  }
  if (/\/api\/v1\/fiscal\/clients\/\d+\/tax-processes$/.test(pathname) && method === 'GET') {
    await fulfill(route, { data: { client_id: 1, processes: scenario === 'empty' ? [] : taxProcessRows } })
    return true
  }
  if (/\/api\/v1\/fiscal\/clients\/\d+\/tax-processes\/refresh$/.test(pathname) && method === 'POST') {
    await fulfill(route, { data: { queued: true, client_id: 1 } }, 202)
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/mailbox/messages') && method === 'GET') {
    await maybeSlow(scenario)
    if (scenario === 'error') {
      await fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return true
    }
    const data = scenario === 'empty' ? [] : buildMailboxMessages()
    await fulfill(route, { data, meta: pageMeta(data.length), total: data.length })
    return true
  }

  const mailboxGet = pathname.match(/\/api\/v1\/fiscal\/mailbox\/messages\/(\d+)$/)
  if (mailboxGet && method === 'GET') {
    const id = Number(mailboxGet[1])
    const msg = buildMailboxMessageDetail(id)
    if (!msg || (id !== msg.id && !buildMailboxMessages().some(m => m.id === id))) {
      await fulfill(route, { message: 'Mensagem não encontrada.' }, 404)
      return true
    }
    await fulfill(route, {
      data: msg,
      meta: {
        official_read_unchanged: true,
        official_read_indicator: msg.official_read_indicator
      }
    })
    return true
  }

  const mailboxTriage = pathname.match(/\/api\/v1\/fiscal\/mailbox\/messages\/(\d+)\/triage$/)
  if (mailboxTriage && (method === 'PATCH' || method === 'POST' || method === 'PUT')) {
    const id = Number(mailboxTriage[1])
    const msg = buildMailboxMessageDetail(id)
    await fulfill(route, {
      data: { ...msg, triage_status: 'IN_REVIEW' },
      meta: {
        official_read_unchanged: true,
        official_read_indicator: msg.official_read_indicator
      }
    })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/mailbox/state') && method === 'GET') {
    await fulfill(route, {
      data: {
        dte: { status: 'ACTIVE', source: 'DEMO', observed_at: FISCAL_FIXTURE_NOW }
      }
    })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/mailbox/alerts') && method === 'GET') {
    await fulfill(route, { data: [] })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/declarations/summary') && method === 'GET') {
    await fulfill(route, { data: buildDeclarationsSummary() })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/declarations/catalog') && method === 'GET') {
    await fulfill(route, { data: buildDeclarationsCatalog() })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/declarations') && method === 'GET') {
    const data = scenario === 'empty' ? [] : buildDeclarations()
    await fulfill(route, { data, meta: pageMeta(data.length), total: data.length })
    return true
  }

  const declGet = pathname.match(/\/api\/v1\/fiscal\/declarations\/(\d+)$/)
  if (declGet && method === 'GET') {
    const id = Number(declGet[1])
    const row = buildDeclarations().find(d => d.id === id) || buildDeclarations()[0]!
    await fulfill(route, {
      data: {
        ...row,
        evidences: [],
        due_rule_snapshot: { disclaimer: DEMO_MARK }
      }
    })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/guides') && method === 'GET') {
    await maybeSlow(scenario)
    if (scenario === 'error') {
      await fulfill(route, { message: 'Falha sintética sanitizada.' }, 503)
      return true
    }
    const data = scenario === 'empty' ? [] : buildGuides()
    await fulfill(route, { data, meta: pageMeta(data.length), total: data.length })
    return true
  }

  const guideGet = pathname.match(/\/api\/v1\/fiscal\/guides\/(\d+)$/)
  if (guideGet && method === 'GET') {
    await fulfill(route, {
      data: { ...buildGuideDetail(Number(guideGet[1])), disclaimer: DEMO_MARK }
    })
    return true
  }

  if (pathname.match(/\/api\/v1\/fiscal\/guides\/\d+\/download-token$/) && method === 'POST') {
    await fulfill(route, {
      data: { token: 'demo-dl-tok', expires_at: FISCAL_FIXTURE_NOW }
    })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/installments/modalities') && method === 'GET') {
    await fulfill(route, { data: buildInstallmentModalities() })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/installments/orders') && method === 'GET') {
    const data = scenario === 'empty' ? [] : buildInstallmentOrders()
    await fulfill(route, { data, meta: pageMeta(data.length) })
    return true
  }

  const orderGet = pathname.match(/\/api\/v1\/fiscal\/installments\/orders\/(\d+)$/)
  if (orderGet && method === 'GET') {
    const id = Number(orderGet[1])
    const order = buildInstallmentOrders().find(o => o.id === id) || buildInstallmentOrders()[0]!
    await fulfill(route, { data: { ...order, disclaimer: DEMO_MARK } })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/installments/parcels') && method === 'GET') {
    const data = buildInstallmentParcels()
    await fulfill(route, { data, meta: pageMeta(data.length) })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/installments/guides') && method === 'GET') {
    await fulfill(route, { data: buildGuides(), meta: pageMeta(buildGuides().length) })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/sitfis') && method === 'GET') {
    await fulfill(route, { data: buildSitfisView() })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/sitfis/refresh') && method === 'POST') {
    await fulfill(route, {
      data: { queued: true, correlation_id: 'DEMO_SITFIS_REFRESH' }
    }, 202)
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/fgts/coverage') && method === 'GET') {
    await fulfill(route, { data: buildFgtsCoverage() })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/fgts/competences') && method === 'GET') {
    const data = scenario === 'empty' ? [] : buildFgtsCompetences()
    await fulfill(route, { data, meta: pageMeta(data.length) })
    return true
  }

  const fgtsComp = pathname.match(/\/api\/v1\/fiscal\/fgts\/competences\/(\d+)$/)
  if (fgtsComp && method === 'GET') {
    const id = Number(fgtsComp[1])
    const row = buildFgtsCompetences().find(c => c.id === id) || buildFgtsCompetences()[0]!
    await fulfill(route, {
      data: {
        ...row,
        divergences: [{
          code: 'TOTALIZER_GAP',
          title: 'Divergência sintética de totalização',
          detail: DEMO_MARK,
          severity: 'MEDIUM',
          situation: 'ATTENTION'
        }]
      }
    })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/fgts/events') && method === 'GET') {
    const data = buildFgtsEvents()
    await fulfill(route, { data, meta: pageMeta(data.length) })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/fgts/sync') && method === 'POST') {
    await fulfill(route, { data: { queued: true, correlation_id: 'DEMO_FGTS_SYNC' } }, 202)
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/snapshots') && method === 'GET') {
    const data = scenario === 'empty' ? [] : buildFiscalSnapshots()
    await fulfill(route, { data, meta: pageMeta(data.length) })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/simples-mei/catalog') && method === 'GET') {
    await fulfill(route, { data: buildSimplesMeiCatalog() })
    return true
  }

  if (pathname.endsWith('/api/v1/fiscal/mutations/preflight') && method === 'POST') {
    await fulfill(route, {
      data: {
        eligible: false,
        denial_code: 'DEMO_MODE',
        denial_message: 'Mutação bloqueada em modo demonstração.',
        confirmation_required: false
      }
    })
    return true
  }

  // Downloads — apenas texto sintético
  if (
    pathname.includes('/api/v1/fiscal/mailbox/messages/')
    && (pathname.endsWith('/body') || pathname.includes('/attachments/'))
  ) {
    await route.fulfill({
      status: 200,
      contentType: 'text/plain; charset=utf-8',
      body: `${DEMO_MARK}\nConteúdo sintético de mailbox.`
    })
    return true
  }

  if (pathname.includes('/api/v1/fiscal/guides/downloads/')) {
    await route.fulfill({
      status: 200,
      contentType: 'text/plain; charset=utf-8',
      body: `${DEMO_MARK}\nGuia sintética.`
    })
    return true
  }

  if (pathname.includes('/api/v1/fiscal/evidence/') && pathname.endsWith('/download')) {
    await route.fulfill({
      status: 200,
      contentType: 'text/plain; charset=utf-8',
      body: `${DEMO_MARK}\nEvidência sintética.`
    })
    return true
  }

  // Fallback honesto para rotas fiscais não mapeadas
  if (pathname.includes('/api/v1/fiscal/')) {
    if (method === 'GET') {
      await fulfill(route, { data: [], meta: pageMeta(0) })
      return true
    }
    await fulfill(route, {
      message: 'Ação fiscal sintética não prevista ou bloqueada em demo.',
      code: 'DEMO_MODE'
    }, 422)
    return true
  }

  return false
}
