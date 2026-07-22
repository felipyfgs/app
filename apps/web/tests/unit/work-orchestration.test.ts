import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { createWorkApi } from '../../app/composables/api/createWorkApi'
import type { ApiClient, ApiUrl } from '../../app/composables/api/types'
import type { ProcessAudienceRules } from '../../app/types/work'
import {
  buildGenerationSelection,
  cloneProcessAudienceRules,
  generationItemClientLabel,
  generationItemClientMeta,
  nextExpandedProcessId
} from '../../app/utils/work-orchestration'

const source = (...parts: string[]) => readFileSync(resolve(process.cwd(), ...parts), 'utf8')

function apiHarness() {
  const clientMock = vi.fn(async () => ({ data: [] }))
  const client = clientMock as unknown as ApiClient
  const apiUrl = vi.fn((path: string) => path) as ApiUrl
  return { api: createWorkApi(client, apiUrl), clientMock }
}

describe('orquestração dos modelos de trabalho', () => {
  it('normaliza regras e exceções antes do preview', () => {
    const rules = cloneProcessAudienceRules({
      tax_regimes: ['MEI', 'MEI', 'LUCRO_REAL'],
      category_ids: [8, 8, -1, 0],
      category_match: 'ALL',
      excluded_category_ids: [13, 13, Number.NaN]
    })

    expect(rules).toEqual({
      tax_regimes: ['MEI', 'LUCRO_REAL'],
      category_ids: [8],
      category_match: 'ALL',
      excluded_category_ids: [13]
    })

    expect(buildGenerationSelection(rules, [5, 5, 7], [7, 9, 0])).toEqual({
      rules,
      include_client_ids: [5, 7],
      exclude_client_ids: [7, 9]
    })
  })

  it('mapeia catálogo, instalação e preview estruturado sem office_id', async () => {
    const { api, clientMock } = apiHarness()
    const rules: ProcessAudienceRules = {
      tax_regimes: ['SIMPLES_NACIONAL'],
      category_ids: [4],
      category_match: 'ANY',
      excluded_category_ids: [9]
    }
    const selection = buildGenerationSelection(rules, [17], [18])

    await api.work.templates.catalog()
    await api.work.templates.installCatalog('PGDAS_MENSAL')
    await api.work.templates.preview(31, { competence: '2026-07', selection })

    expect(clientMock).toHaveBeenNthCalledWith(1, '/api/v1/work/template-catalog')
    expect(clientMock).toHaveBeenNthCalledWith(2, '/api/v1/work/template-catalog/PGDAS_MENSAL/install', {
      method: 'POST',
      body: {}
    })
    expect(clientMock).toHaveBeenNthCalledWith(3, '/api/v1/work/templates/31/preview', {
      method: 'POST',
      body: { competence: '2026-07', selection }
    })
    expect(JSON.stringify(clientMock.mock.calls)).not.toContain('office_id')
  })

  it('expõe identidade explicativa no item da prévia', () => {
    const item = {
      id: 1,
      client_id: 17,
      status: 'PREVIEWED',
      is_blocked: false,
      preview_payload: {
        selection: {
          client_name: 'Empresa Exemplo Ltda.',
          cnpj_masked: '12.345.678/0001-90',
          tax_regime: 'SIMPLES_NACIONAL'
        }
      }
    }

    expect(generationItemClientLabel(item)).toBe('Empresa Exemplo Ltda.')
    expect(generationItemClientMeta(item)).toBe('12.345.678/0001-90 · Simples Nacional')
  })
})

describe('listagem tabular de processos', () => {
  it('mantém helper de expansão unitário estável', () => {
    expect(nextExpandedProcessId(null, 10)).toBe(10)
    expect(nextExpandedProcessId(10, 20)).toBe(20)
    expect(nextExpandedProcessId(20, 20)).toBeNull()
  })

  it('lista processos com ShellDataTable expansível e links canônicos', () => {
    const page = source('app/pages/work/processes/index.vue')

    expect(page).toContain('ShellDataTable')
    expect(page).toContain('work-processes-table')
    expect(page).toContain('v-model:expanded')
    expect(page).toContain('#expanded')
    expect(page).toContain('work-process-expand')
    expect(page).toContain('/work/tasks/${task.id}')
    expect(page).toContain('/work/processes/${process.id}')
    expect(page).toContain('openProcess')
    expect(page).toContain('WorkBulkActionsModal')
    expect(page).toContain('can-update-processes')
    expect(page).toContain('WorkTaskStatusSelect')
    expect(page).toContain('manual-sorting')
    expect(page).toContain('sortHeader')
    expect(page).toContain('enableSorting: false')
    expect(page).toContain('ShellListFilterToolbar')
    expect(page).toContain('work-processes-bulk-actions')
    expect(page).toContain('openBulkActions')
    expect(page).toContain('#actions')
    expect(page).toContain('work-processes-toolbar')
    expect(page).not.toContain('@select="onProcessTableSelect"')
    expect(page).not.toContain('WorkProcessAccordionList')
    expect(page).not.toContain('overflow-x-auto')
  })

  it('expõe bulk de processos e tarefas no cliente API', async () => {
    const { api, clientMock } = apiHarness()
    await api.work.processes.bulk({
      items: [{ id: 1, lock_version: 1 }],
      changes: { action: 'assign', assignee_membership_id: 9 }
    })
    await api.work.tasks.bulk({
      items: [{ id: 2, lock_version: 3 }],
      changes: { action: 'start' }
    })
    expect(clientMock).toHaveBeenCalledWith('/api/v1/work/processes/bulk', {
      method: 'POST',
      body: {
        items: [{ id: 1, lock_version: 1 }],
        changes: { action: 'assign', assignee_membership_id: 9 }
      }
    })
    expect(clientMock).toHaveBeenCalledWith('/api/v1/work/tasks/bulk', {
      method: 'POST',
      body: {
        items: [{ id: 2, lock_version: 3 }],
        changes: { action: 'start' }
      }
    })
  })
})

describe('integração entre modelos, tarefas e monitoramento', () => {
  it('oferece biblioteca, edição e seleção avançada sem campo bruto de IDs', () => {
    const templates = source('app/pages/work/templates/index.vue')

    for (const token of [
      'Biblioteca',
      'Meus modelos',
      'installCatalog',
      'audienceRules',
      'FiscalClientPicker',
      'Pré-visualizar empresas'
    ]) {
      expect(templates).toContain(token)
    }
    expect(templates).not.toContain('IDs de clientes')
  })

  it('carrega trabalho do cliente com falha independente e link filtrado', () => {
    const page = source('app/pages/monitoring/clients/[clientId].vue')
    const block = source('app/components/monitoring/ClientOperationalWork.vue')

    expect(page).toContain('operationalWorkState')
    expect(page).toContain('void loadOperationalWork(force)')
    expect(page).toContain('active_only: true')
    expect(page).toContain('MonitoringClientOperationalWork')
    expect(block).toContain('Trabalho operacional')
    expect(block).toContain('/work/processes?client_id=${props.clientId}')
    expect(block).toContain('progress_percent')
  })

  it('consolida a visão transversal sob o nome Tarefas', () => {
    const navigation = source('app/utils/work-navigation.ts')
    const workspace = source('app/components/work/WorkQueueWorkspace.vue')

    expect(navigation).toContain('label: \'Tarefas\'')
    expect(workspace).toContain('UDashboardNavbar title="Tarefas"')
    expect(navigation).not.toContain('Minha fila')
  })

  it('oferece toggle Fila|Lista sincronizado com view na query', () => {
    const workspace = source('app/components/work/WorkQueueWorkspace.vue')
    const filters = source('app/composables/useWorkQueueFilters.ts')

    expect(workspace).toContain('work-queue-view-toggle')
    expect(workspace).toContain('setQueueView(\'lista\')')
    expect(workspace).toContain('ShellDataTable')
    expect(workspace).toContain('work-queue-table')
    expect(workspace).toContain('WorkBulkActionsModal')
    expect(workspace).toContain('WorkTaskStatusSelect')
    expect(workspace).toContain('manual-sorting')
    expect(workspace).toContain('sortHeader')
    expect(workspace).toContain('work-queue-bulk-actions')
    expect(workspace).toContain('openBulkActions')
    expect(workspace).toContain('#actions')
    expect(filters).toContain('view: WorkQueueView')
    expect(filters).toContain('view: f.view === \'lista\' ? \'lista\' : undefined')
    expect(filters).toContain('sort: f.sort || undefined')
  })

  it('Fila desktop: detalhe colapsável com auto-seleção da primeira tarefa', () => {
    const workspace = source('app/components/work/WorkQueueWorkspace.vue')
    expect(workspace).toContain('detailOpen')
    expect(workspace).toContain('toggleDetail')
    expect(workspace).toContain('work-queue-detail-toggle')
    expect(workspace).toContain('detailPaneVisible')
    expect(workspace).toContain('suppressAutoSelect')
    expect(workspace).toContain('await select(items.value[0]')
    expect(workspace).not.toContain('work-queue-neutral')
  })
})
