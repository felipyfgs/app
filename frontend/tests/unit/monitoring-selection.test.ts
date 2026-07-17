import { describe, expect, it } from 'vitest'
import { monitoringFilterSignature, normalizeMonitoringFilters } from '../../app/utils/monitoring-filters'
import { monitoringBulkActionState } from '../../app/utils/monitoring-actions'
import {
  monitoringSelectionScope,
  pruneMonitoringSelection,
  selectedMonitoringRows
} from '../../app/utils/monitoring-selection'

type Guide = { id: number, client_id: number }
const guideId = (guide: Guide) => `guide:${guide.id}`

describe('monitoring selection', () => {
  it('mantém no refresh somente IDs que ainda existem', () => {
    const rows: Guide[] = [{ id: 10, client_id: 7 }, { id: 12, client_id: 8 }]
    const selection = { 'guide:10': true, 'guide:11': true }
    expect(pruneMonitoringSelection(rows, selection, guideId)).toEqual({ 'guide:10': true })
  })

  it('usa o ID da guia sem colidir e deduplica clientes para bulk', () => {
    const rows: Guide[] = [{ id: 10, client_id: 7 }, { id: 11, client_id: 7 }]
    expect(rows.map(guideId)).toEqual(['guide:10', 'guide:11'])
    const selected = selectedMonitoringRows(
      rows,
      { 'guide:10': true, 'guide:11': true },
      guideId
    )
    expect([...new Set(selected.map(row => row.client_id))]).toEqual([7])
  })

  it.each([
    ['Office', { officeEpoch: 2 }],
    ['rota', { route: '/monitoring/guides' }],
    ['página', { page: 2 }],
    ['filtro', { filters: monitoringFilterSignature(normalizeMonitoringFilters({ status: 'OPEN' })) }],
    ['ordenação', { sorting: [{ id: 'client', desc: true }] }],
    ['submódulo', { submodule: 'PGDASD' }]
  ])('altera o escopo ao trocar %s', (_label, changed) => {
    const base = {
      officeEpoch: 1,
      route: '/monitoring/registrations',
      page: 1,
      filters: monitoringFilterSignature(normalizeMonitoringFilters()),
      sorting: [{ id: 'client', desc: false }],
      submodule: ''
    }
    expect(monitoringSelectionScope({ ...base, ...changed })).not.toBe(
      monitoringSelectionScope(base)
    )
  })

  it('exibe ações somente para módulo suportado, capacidade e seleção', () => {
    expect(monitoringBulkActionState({
      moduleKey: 'guides',
      selectedCount: 2,
      canAssociate: true,
      canEnqueue: false,
      canExport: true
    }).visible).toBe(true)
    expect(monitoringBulkActionState({
      moduleKey: 'registrations',
      selectedCount: 2,
      canAssociate: true,
      canEnqueue: true,
      canExport: true
    }).visible).toBe(false)
    expect(monitoringBulkActionState({
      moduleKey: 'guides',
      selectedCount: 0,
      canAssociate: true,
      canEnqueue: true,
      canExport: true
    }).visible).toBe(false)
  })
})
