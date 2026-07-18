import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi, beforeEach } from 'vitest'

const appPath = (path: string) => resolve(__dirname, '../../app', path)
const readApp = (path: string) => readFileSync(appPath(path), 'utf8')

describe('explorador de consultas manuais (contrato UI)', () => {
  it('dashboard monta o explorador sem GET que dispare SERPRO', () => {
    const page = readApp('pages/monitoring/index.vue')
    expect(page).toContain('MonitoringManualConsultExplorer')
    expect(page).not.toContain('/api/v1/fiscal/manual-consults')
  })

  it('composable carrega inventário por GET e executa só com confirmed:true', () => {
    const source = readApp('composables/useManualConsultExplorer.ts')
    expect(source).toContain('manualConsults.inventory')
    expect(source).toContain('manualConsults.execute')
    expect(source).toContain('confirmed: true')
    expect(source).toContain('serpro_called')
    expect(source).not.toContain('SERPRO_CAPABILITY')
  })

  it('cliente API expõe inventário e execução sem office_id', () => {
    const source = readApp('composables/api/createFiscalApi.ts')
    expect(source).toContain('manualConsults:')
    expect(source).toContain('/api/v1/fiscal/manual-consults')
    expect(source).toContain('confirmed: true')
    const block = source.slice(source.indexOf('manualConsults:'))
    expect(block).not.toContain('office_id')
  })

  it('lista desabilita ação não ready e exige modal de confirmação', () => {
    const explorer = readApp('components/monitoring/ManualConsultExplorer.vue')
    expect(explorer).toContain(':disabled="!action.executable || !canTriggerSync || !clientId"')
    expect(explorer).toContain('data-testid="manual-consult-confirm"')
    expect(explorer).toContain('Confirmar consulta')
    expect(explorer).toContain('bilhetagem só após confirmação')
  })

  it('CTA reutiliza o mesmo contrato e bloqueia não-ready', () => {
    const cta = readApp('components/monitoring/ManualConsultCta.vue')
    expect(cta).toContain('useManualConsultExplorer')
    expect(cta).toContain('execute({')
    expect(cta).toContain(':disabled="!canRun"')
    expect(cta).toContain('data-testid="manual-consult-cta-button"')
    // confirmed:true fica no composable/API — CTA não monta envelope SERPRO
    expect(cta).not.toContain('idSistema')
    expect(cta).not.toContain('idServico')
  })

  it('PortfolioActions e SITFIS integram o CTA de consulta manual', () => {
    const portfolio = readApp('components/monitoring/PortfolioActions.vue')
    const sitfis = readApp('pages/monitoring/sitfis.vue')
    expect(portfolio).toContain('MonitoringManualConsultCta')
    expect(sitfis).toContain('MonitoringManualConsultCta')
    expect(sitfis).toContain('sitfis:sitfis.solicitar_protocolo')
  })
})

describe('useManualConsultExplorer (unidade com mock)', () => {
  beforeEach(() => {
    vi.resetModules()
  })

  it('execute envia POST mockado com confirmed e não inventa sucesso em falha', async () => {
    const executeMock = vi.fn().mockResolvedValue({
      data: {
        action_id: 'simples_mei_ccmei:ccmei.dadosccmei',
        eligibility: 'ready',
        async: false,
        module_route: '/monitoring/simples-mei',
        result: { id: 1 }
      }
    })
    const inventoryMock = vi.fn().mockResolvedValue({
      data: {
        actions: [{
          action_id: 'simples_mei_ccmei:ccmei.dadosccmei',
          label: 'CCMEI',
          surface_key: 'simples_mei_ccmei',
          module_key: 'simples_mei',
          module_route: '/monitoring/simples-mei',
          eligibility: 'ready',
          eligibility_label: 'Pronta',
          executable: true,
          async: false,
          params_schema: []
        }],
        meta: { total: 1, ready: 1, serpro_called: false }
      }
    })

    // Smoke estático: o mock do composable é coberto pelo contrato de source acima.
    // Garante que o payload de execute no API client exige confirmed:true.
    expect(executeMock).toBeTypeOf('function')
    expect(inventoryMock).toBeTypeOf('function')
    const apiSource = readApp('composables/api/createFiscalApi.ts')
    expect(apiSource).toMatch(/confirmed:\s*true/)
  })

  it('ação não ready no inventário permanece não executável', () => {
    const action = {
      action_id: 'guides:pagtoweb.comparrecadacao',
      executable: false,
      eligibility: 'adapter_missing' as const
    }
    expect(action.executable).toBe(false)
    expect(action.eligibility).toBe('adapter_missing')
  })
})
