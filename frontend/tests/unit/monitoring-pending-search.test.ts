import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const appPath = (path: string) => resolve(__dirname, '../../app', path)
const readApp = (path: string) => readFileSync(appPath(path), 'utf8')

describe('busca manual de pendências no monitoramento', () => {
  it('expõe a ação primária no navbar do arquétipo de lista', () => {
    const source = readApp('components/monitoring/ModuleTable.vue')

    expect(source).toContain('#right')
    expect(source).toContain('<MonitoringPendingSearchButton')
    expect(source).toContain(':current-page-client-ids="currentPageClientIds"')
    expect(source).toContain(':selected-client-ids="selectedClientIds"')
    expect(source).toContain('@submitted="emit(\'refresh\')"')
  })

  it('confirma a chamada faturável e consolida o lote sem toasts por cliente', () => {
    const source = readApp('components/monitoring/PendingSearchButton.vue')

    expect(source).toContain('isDctfweb.value ? \'Consulta manual\' : \'Buscar pendências\'')
    expect(source).toContain('\'Confirmar consulta manual DCTFWeb\'')
    expect(source).toContain('pode consumir a franquia da integração')
    expect(source).toContain('.slice(0, 100)')
    expect(source).toContain('silent: true')
    expect(source).toContain('i-lucide-scan-search')
    expect(source).toContain('data-testid="monitoring-pending-search-confirm"')
    expect(source).toContain('scheduleRefreshes()')
  })

  it('respeita os contratos especializados de PGMEI e MIT', () => {
    const source = readApp('components/monitoring/PendingSearchButton.vue')

    expect(source).toContain('requestPgmeiConsult(targets.value, currentYear.value)')
    expect(source).toContain('system_code: isMit.value ? \'INTEGRA_MIT\' : undefined')
    expect(source).toContain('service_code: isMit.value ? \'MIT\' : undefined')
    expect(source).toContain('operation_code: isMit.value ? \'CONSULTAR_APURACAO\' : undefined')
  })

  it('usa o endpoint dedicado da DCTFWeb na consulta manual', () => {
    const source = readApp('composables/useMonitoringActions.ts')

    expect(source).toContain('if (key === \'dctfweb\')')
    expect(source).toContain('api.fiscal.dctfweb.consult')
    expect(source).toContain('operation_code: input.operation_code || \'CONSULTAR_RECIBO\'')
  })
})
