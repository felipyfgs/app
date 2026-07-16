import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('contrato do dashboard de clientes', () => {
  const page = readFileSync(resolve(__dirname, '../../app/pages/clients/dashboard.vue'), 'utf8')
  const dashboard = readFileSync(resolve(__dirname, '../../app/components/clients/ClientListDashboard.vue'), 'utf8')

  it('busca apenas a primeira página dos oito clientes mais recentes', () => {
    expect(page).toContain('per_page: 8')
    expect(page).toContain('sort: \'created_at\'')
    expect(page).toContain('direction: \'desc\'')
    expect(page).toContain('dashboard: true')
    expect(page).not.toMatch(/for\s*\(let\s+p\s*=\s*2/)
  })

  it('usa KPI e série globais do servidor, sem derivá-los da amostra', () => {
    expect(dashboard).toContain('props.stats.credential_ok')
    expect(dashboard).toContain('props.stats.client_growth_12m')
    expect(dashboard).not.toContain('props.clients.filter')
  })

  it('limpa o tenant anterior e descarta respostas em voo ao trocar de escritório', () => {
    expect(page).toContain('const { sessionEpoch } = useDashboard()')
    expect(page).toContain('const seq = ++loadSeq')
    expect(page).toContain('epoch !== sessionEpoch.value')
    expect(page).toContain('watch(sessionEpoch')
    expect(page).toContain('clients.value = []')
    expect(page).toContain('stats.value = emptyStats()')
  })
})
