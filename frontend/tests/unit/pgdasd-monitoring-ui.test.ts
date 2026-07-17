import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { PgdasdCommunicationPreference, SimplesMeiClientRow } from '../../app/types/fiscal-modules'
import {
  formatPgdasdPeriod,
  pgdasdCanRequestAutomatic,
  pgdasdDeclarationMeta,
  pgdasdHistoryPeriods,
  pgdasdRbt12Tooltip,
  pgdasdSummary,
  pgdasdTrackingMeta
} from '../../app/utils/pgdasd'

beforeAll(() => vi.stubGlobal('resolveComponent', (name: string) => name))

function row(detail: SimplesMeiClientRow['detail']): SimplesMeiClientRow {
  return {
    module_key: 'simples_mei',
    client_id: 7,
    legal_name: 'ACME COMÉRCIO LTDA',
    name: 'ACME',
    cnpj_masked: '12.***.***/****-90',
    situation: 'UNKNOWN',
    coverage: 'FULL',
    detail
  }
}

describe('renderer PGDAS-D', () => {
  it('materializa as nove colunas da referência visual; seleção fica a cargo do shell', () => {
    const source = readFileSync(
      resolve(__dirname, '../../app/utils/pgdasd-table.ts'),
      'utf8'
    )
    const ids = [...source.matchAll(/id: '([^']+)'/g)].map(match => match[1])
    expect(ids).toEqual([
      'situation',
      'last_declaration',
      'rbt12',
      'actions',
      'send',
      'client',
      'tracking',
      'consulted',
      'history'
    ])
    expect(ids).not.toEqual(
      expect.arrayContaining(['automatic', 'details', 'cnpj', 'coverage', 'guide', 'payment'])
    )
    expect(source).toContain("header: 'Situação'")
    expect(source).toContain("header: 'Últ. Declaração'")
    expect(source).toContain("'Sublimite (RBT12)'")
    expect(source).toContain("header: 'Ações'")
    expect(source).toContain("'Enviar'")
    expect(source).toContain("sortHeader('Cliente'")
    expect(source).toContain("header: 'Rastreio de envio'")
    expect(source).toContain("sortHeader('Última Busca'")
    expect(source).toContain("header: 'Histórico de Busca'")
    expect(source).toContain('cnpjMasked: row.original.cnpj_masked')
    expect(source).toContain('BulkAutomaticSwitch')
    expect(source).toContain('declaration_state_reason || summary?.declaration_reason')
  })

  it('mapeia estado por semântica, ícone, texto e cor sem promover desconhecido', () => {
    expect(pgdasdDeclarationMeta('CURRENT')).toMatchObject({
      color: 'success',
      label: 'Em dia'
    })
    expect(pgdasdDeclarationMeta('DUE_WITHIN_DEADLINE')).toMatchObject({
      color: 'warning',
      label: 'Pendências'
    })
    expect(pgdasdDeclarationMeta('OVERDUE_NOT_FOUND')).toMatchObject({
      color: 'error',
      label: 'Atrasado'
    })
    expect(pgdasdDeclarationMeta('valor-inventado')).toMatchObject({
      color: 'neutral',
      label: 'Não verificado'
    })
  })

  it('aceita detail.pgdasd nulo e o shape aditivo do deploy sem fabricar dado', () => {
    expect(pgdasdSummary(row({ submodule: 'PGDASD', pgdasd: null }))).toBeNull()

    const legacy = pgdasdSummary(row({
      submodule: 'PGDASD',
      period_key: '06/2026',
      declaration_state: 'UNVERIFIED',
      last_productive_consulted_at: null,
      rbt12: { status: 'NO_DAS' }
    }))
    expect(legacy).toMatchObject({
      expected_period_key: '06/2026',
      declaration_state: 'UNVERIFIED',
      rbt12: { status: 'NO_DAS' }
    })
    expect(legacy?.last_valid_query_at).toBeNull()
  })

  it('RBT12 só formata projeção PARSED e explica indisponibilidade sem estimar', () => {
    expect(pgdasdRbt12Tooltip({ status: 'PARSED', total_cents: 208_092_00 }))
      .toContain('R$ 208.092,00')
    expect(pgdasdRbt12Tooltip({ status: 'UNKNOWN', total_cents: 208_092_00 }))
      .toMatch(/indisponível/i)
    expect(pgdasdRbt12Tooltip({ status: 'AMBIGUOUS' }))
      .toMatch(/não estima/i)
    const detailed = pgdasdRbt12Tooltip({
      status: 'PARSED',
      total_cents: 208_092_00,
      composition: {
        internal_market_cents: 200_000_00,
        external_market_cents: 8_092_00
      },
      origin: {
        das_number: '123456789',
        declaration_number: '987654321'
      }
    })
    expect(detailed).toContain('Mercado interno:')
    expect(detailed).toContain('Mercado externo:')
    expect(detailed).toContain('Origem: extrato do DAS nº 123456789 e declaração nº 987654321.')
  })

  it('exibe o PA como MM/AAAA para YYYY-MM, YYYYMM e valor já formatado', () => {
    expect(formatPgdasdPeriod('2026-06')).toBe('06/2026')
    expect(formatPgdasdPeriod('202606')).toBe('06/2026')
    expect(formatPgdasdPeriod('06/2026')).toBe('06/2026')
    expect(formatPgdasdPeriod('2026-13')).toBe('—')
  })
})

describe('comunicação TEMPLATE_ONLY', () => {
  const eligible: PgdasdCommunicationPreference = {
    automatic_requested: false,
    automatic_effective: false,
    email_enabled: true,
    whatsapp_enabled: false,
    lock_version: 2,
    execution_mode: 'TEMPLATE_ONLY',
    eligible_channels: ['EMAIL'],
    tracking_status: 'NO_HISTORY'
  }

  it('só permite solicitar automático quando canal habilitado é elegível', () => {
    expect(pgdasdCanRequestAutomatic(eligible)).toBe(true)
    expect(pgdasdCanRequestAutomatic({
      ...eligible,
      eligible_channels: [],
      email_enabled: true
    })).toBe(false)
    expect(pgdasdCanRequestAutomatic(null)).toBe(false)
  })

  it('mapeia todos os estados de rastreio e cai em NO_HISTORY sem inventar entrega', () => {
    expect(pgdasdTrackingMeta('READ')).toMatchObject({ color: 'success', label: 'Lido' })
    expect(pgdasdTrackingMeta('FAILED')).toMatchObject({ color: 'error' })
    expect(pgdasdTrackingMeta(undefined)).toMatchObject({ label: 'Sem histórico de envio' })
  })
})

describe('modais e integração da página', () => {
  const app = resolve(__dirname, '../../app')

  it('normaliza histórico por periods, history ou array e tolera ausência', () => {
    const period = { period_key: '202606' }
    expect(pgdasdHistoryPeriods([period])).toEqual([period])
    expect(pgdasdHistoryPeriods({ periods: [period] })).toEqual([period])
    expect(pgdasdHistoryPeriods({ history: [period] })).toEqual([period])
    expect(pgdasdHistoryPeriods(null)).toEqual([])
  })

  it('não usa GET de preferências, JSON bruto ou endpoint de envio', () => {
    const composable = readFileSync(resolve(app, 'composables/usePgdasdMonitoring.ts'), 'utf8')
    const communication = readFileSync(
      resolve(app, 'components/monitoring/PgdasdCommunicationModals.vue'),
      'utf8'
    )
    expect(composable).not.toContain('fetchPreferences')
    expect(communication).not.toContain('JSON.stringify')
    expect(communication).toContain('can_send')
    expect(communication).toContain('Enviar agora')
    expect(communication).toContain('disabled')
  })

  it('especializa seleção/scroll e colunas PGDAS-D e PGMEI com bulk no cabeçalho Enviar', () => {
    const page = readFileSync(resolve(app, 'pages/monitoring/simples-mei/index.vue'), 'utf8')
    expect(page).toContain('selection-enabled')
    expect(page).toContain('custom-bulk-actions')
    expect(page).toContain('horizontal-scroll')
    expect(page).toContain('selection-change')
    expect(page).toContain('buildPgdasdColumns')
    expect(page).toContain('buildPgmeiColumns')
    expect(page).toContain('selectedClientIds')
    // Sem faixa de KPI em tabs (Total / Em dia / Pendências / …).
    expect(page).toContain(':show-kpis="false"')
    expect(page).not.toContain(':counters=')
  })
})
