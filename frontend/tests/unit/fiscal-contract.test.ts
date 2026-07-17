/**
 * 9.2 — testes de contrato das fixtures fiscais (overview/carteira/listagens).
 * Falham se envelope incorreto, module_key cruzado, campo incompatível ou material sensível.
 */
import { describe, expect, it } from 'vitest'
import {
  FISCAL_PORTFOLIO_MODULE_KEYS,
  isSyntheticFiscalOrigin
} from '../../app/types/fiscal-modules'
import type {
  buildFiscalModuleClients } from './support/fiscal-fixtures'
import {
  assertClientRowEnvelope,
  assertClientsPageEnvelope,
  assertFiscalPayloadSanitized,
  assertListEnvelope,
  assertOverviewEnvelope,
  buildDeclarations,
  buildFgtsCompetences,
  buildFgtsCoverage,
  buildFiscalCategories,
  buildFiscalFindings,
  buildFiscalModuleClientRow,
  buildFiscalModuleOverview,
  buildFiscalPendingItems,
  buildFiscalRuns,
  buildFiscalSnapshots,
  buildGuideDetail,
  buildGuides,
  buildMailboxMessageDetail,
  buildMailboxMessages,
  buildSitfisView,
  fiscalModuleClientsResponse,
  fiscalModuleOverviewResponse,
  FISCAL_FIXTURE_CNPJ_MASKED,
  FISCAL_FIXTURE_CNPJ_RAW,
  FISCAL_FORBIDDEN_KEYS
} from './support/fiscal-fixtures'

describe('contrato fiscal — overview discriminado por module_key (9.2)', () => {
  for (const module of FISCAL_PORTFOLIO_MODULE_KEYS) {
    it(`overview ${module}: envelope data + DEMO + counters`, () => {
      const body = fiscalModuleOverviewResponse(module, 'ready')
      expect(body).toHaveProperty('data')
      assertOverviewEnvelope(module, body)

      const overview = (body as { data: ReturnType<typeof buildFiscalModuleOverview> }).data
      expect(overview.module_key).toBe(module)
      expect(overview.data_origin).toBe('DEMO')
      expect(overview.is_synthetic).toBe(true)
      expect(isSyntheticFiscalOrigin(overview.data_origin)).toBe(true)
      expect(overview.counters).toMatchObject({
        up_to_date: expect.any(Number),
        processing: expect.any(Number),
        pending: expect.any(Number),
        attention: expect.any(Number),
        error: expect.any(Number)
      })
    })

    it(`overview ${module}: empty zera total/contadores sem quebrar envelope`, () => {
      const body = fiscalModuleOverviewResponse(module, 'empty')
      assertOverviewEnvelope(module, body)
      const overview = (body as { data: ReturnType<typeof buildFiscalModuleOverview> }).data
      expect(overview.total_clients).toBe(0)
      expect(overview.counters.pending).toBe(0)
    })

    it(`overview ${module}: error não finge sucesso com data`, () => {
      const body = fiscalModuleOverviewResponse(module, 'error')
      expect(body).toEqual({ message: expect.stringMatching(/sintética|sanitizada/i) })
      expect(body).not.toHaveProperty('data')
    })
  }

  it('rejeita module_key de overview cruzado', () => {
    const wrong = {
      data: {
        ...buildFiscalModuleOverview('sitfis'),
        module_key: 'guides' as const
      }
    }
    expect(() => assertOverviewEnvelope('sitfis', wrong)).toThrow(/module_key incompatível/)
  })

  it('rejeita envelope sem data', () => {
    expect(() => assertOverviewEnvelope('mailbox', { items: [] })).toThrow(/data ausente|envelope/)
  })

  it('rejeita counters incompletos', () => {
    const bad = {
      data: {
        ...buildFiscalModuleOverview('fgts'),
        counters: { up_to_date: 1 }
      }
    }
    expect(() => assertOverviewEnvelope('fgts', bad)).toThrow(/counters\./)
  })
})

describe('contrato fiscal — client rows discriminados (9.2)', () => {
  for (const module of FISCAL_PORTFOLIO_MODULE_KEYS) {
    it(`clients ${module}: página com module_key e CNPJ mascarado`, () => {
      const body = fiscalModuleClientsResponse(module, 'ready')
      assertClientsPageEnvelope(module, body)

      const rows = (body as { data: ReturnType<typeof buildFiscalModuleClients> }).data
      expect(rows.length).toBeGreaterThan(0)
      for (const row of rows) {
        expect(row.module_key).toBe(module)
        expect(row.cnpj_masked).toContain('*')
        expect(row.cnpj_masked).not.toBe(FISCAL_FIXTURE_CNPJ_RAW)
        expect(row.detail).toBeTruthy()
        expect((row.detail as { module_key?: string }).module_key).toBe(module)
      }
    })

    it(`clients ${module}: empty é array vazio válido`, () => {
      const body = fiscalModuleClientsResponse(module, 'empty')
      assertClientsPageEnvelope(module, body)
      expect((body as { data: unknown[] }).data).toEqual([])
    })
  }

  it('falha se registro de outro module_key entrar na carteira', () => {
    const body = {
      data: [
        buildFiscalModuleClientRow('sitfis'),
        {
          ...buildFiscalModuleClientRow('guides'),
          module_key: 'guides' as const
        }
      ],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 }
    }
    expect(() => assertClientsPageEnvelope('sitfis', body)).toThrow(/module_key|outro module/)
  })

  it('falha se cnpj_masked não tiver máscara', () => {
    const row = {
      ...buildFiscalModuleClientRow('simples_mei'),
      cnpj_masked: FISCAL_FIXTURE_CNPJ_RAW
    }
    expect(() => assertClientRowEnvelope('simples_mei', row)).toThrow(/cnpj_masked/)
  })

  it('falha se detail.module_key divergir', () => {
    const row = {
      ...buildFiscalModuleClientRow('dctfweb'),
      detail: { module_key: 'fgts', closure_status: 'CLOSED' }
    }
    expect(() => assertClientRowEnvelope('dctfweb', row)).toThrow(/detail\.module_key/)
  })

  it('máscara de CNPJ fixture é determinística e mascarada', () => {
    expect(FISCAL_FIXTURE_CNPJ_MASKED).toContain('*')
    expect(FISCAL_FIXTURE_CNPJ_MASKED.startsWith('12AB')).toBe(true)
    expect(FISCAL_FIXTURE_CNPJ_MASKED.endsWith('8900')).toBe(true)
  })
})

describe('contrato fiscal — listagens e detalhes sanitizados (9.2)', () => {
  it('runs / snapshots / findings / pending usam envelope data[]', () => {
    assertListEnvelope('runs', { data: buildFiscalRuns(), meta: { total: 2 } })
    assertListEnvelope('snapshots', { data: buildFiscalSnapshots() })
    assertListEnvelope('findings', { data: buildFiscalFindings() })
    assertListEnvelope('pending', { data: buildFiscalPendingItems() })
  })

  it('mailbox list + detail sem corpo/XML embutido', () => {
    const list = buildMailboxMessages()
    assertListEnvelope('mailbox', { data: list })
    const detail = buildMailboxMessageDetail(list[0]!.id)
    assertFiscalPayloadSanitized(detail, 'mailbox detail')
    expect(detail).not.toHaveProperty('body_content')
    expect(detail).not.toHaveProperty('raw_xml')
    expect(detail.attachments?.[0]).not.toHaveProperty('vault_object_id')
  })

  it('guide detail inclui versões/metadados sem PEM/vault', () => {
    const detail = buildGuideDetail()
    assertFiscalPayloadSanitized(detail, 'guide detail')
    expect(detail.amount_cents).toEqual(expect.any(Number))
    expect(detail.current_version?.content_sha256).toMatch(/^[a-f0-9]+$/i)
  })

  it('declarations e guides list mantêm data_origin DEMO quando presente', () => {
    for (const g of buildGuides()) {
      expect(g.data_origin).toBe('DEMO')
      expect(g.is_synthetic).toBe(true)
    }
    for (const d of buildDeclarations()) {
      expect(d.module_key).toBe('declarations')
      expect(d.data_origin).toBe('DEMO')
    }
  })

  it('FGTS coverage declara parcial e proíbe scraping/portal', () => {
    const coverage = buildFgtsCoverage()
    expect(coverage.coverage).toBe('PARTIAL')
    expect(coverage.declares_fgts_digital_debt).toBe(false)
    expect(coverage.scraping_allowed).toBe(false)
    expect(coverage.portal_fallback).toBe(false)
    assertFiscalPayloadSanitized(coverage, 'fgts coverage')
  })

  it('FGTS competences marcam guia/pagamento UNSUPPORTED', () => {
    const rows = buildFgtsCompetences()
    expect(rows[0]!.guide_status).toBe('UNSUPPORTED')
    expect(rows[0]!.payment_status).toBe('UNSUPPORTED')
    assertListEnvelope('fgts competences', { data: rows })
  })

  it('sitfis view nunca afirma certidão negativa', () => {
    const view = buildSitfisView()
    expect(view.is_negative_certificate).toBe(false)
    expect(view.disclaimer).toMatch(/não equivale a certidão/i)
    assertFiscalPayloadSanitized(view, 'sitfis view')
  })

  it('categories cobrem todos os módulos de carteira', () => {
    const cats = buildFiscalCategories()
    expect(cats.length).toBe(FISCAL_PORTFOLIO_MODULE_KEYS.length)
    assertFiscalPayloadSanitized(cats, 'categories')
  })
})

describe('contrato fiscal — sanitização anti-segredo (9.2)', () => {
  it('builders ready não expõem chaves proibidas', () => {
    const payloads = [
      fiscalModuleOverviewResponse('sitfis'),
      fiscalModuleClientsResponse('mailbox'),
      { data: buildFiscalRuns() },
      { data: buildFiscalSnapshots() },
      { data: buildFiscalFindings() },
      { data: buildFiscalPendingItems() },
      { data: buildMailboxMessages() },
      buildMailboxMessageDetail(),
      { data: buildGuides() },
      buildGuideDetail(),
      { data: buildDeclarations() },
      buildFgtsCoverage(),
      { data: buildFgtsCompetences() },
      buildSitfisView(),
      { data: buildFiscalCategories() }
    ]
    for (const [i, payload] of payloads.entries()) {
      expect(() => assertFiscalPayloadSanitized(payload, `payload#${i}`)).not.toThrow()
    }
  })

  it('detecta campo vault_object_id injetado', () => {
    const poisoned = {
      data: {
        ...buildFiscalModuleOverview('guides'),
        vault_object_id: 'sec-123'
      }
    }
    expect(() => assertFiscalPayloadSanitized(poisoned)).toThrow(/vault_object_id/)
  })

  it('detecta PEM embutido', () => {
    const begin = ['-----BEGIN', 'PRIVATE', 'KEY-----'].join(' ')
    const poisoned = {
      data: {
        pem: `${begin}\nMIIE\n-----END PRIVATE KEY-----`
      }
    }
    // assertFiscalPayloadSanitized usa chaves proibidas + padrões de conteúdo
    expect(() => assertFiscalPayloadSanitized(poisoned)).toThrow(/pem|PRIVATE KEY|proibido/i)
  })

  it('lista de chaves proibidas cobre PFX/PEM/vault/tokens SERPRO', () => {
    for (const key of [
      'pfx',
      'private_key',
      'vault_object_id',
      'consumer_secret',
      'consumer_key',
      'termo_xml',
      'raw_xml'
    ]) {
      expect(FISCAL_FORBIDDEN_KEYS).toContain(key)
    }
  })
})
