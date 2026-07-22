import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import type { OperationalProcess } from '../../app/types/work'
import {
  cascadeProcessTaskSelection,
  cascadeSelectAllProcessesOnPage,
  sortedProcessTasks
} from '../../app/utils/work-process-selection'

function processFixture(id: number, taskIds: number[]): OperationalProcess {
  return {
    id,
    title: `Processo ${id}`,
    competence: '2026-07',
    origin: 'MANUAL',
    status: 'EM_PROGRESSO',
    subject_to_fine: false,
    client_id: 1,
    lock_version: 1,
    tasks: taskIds.map((taskId, index) => ({
      id: taskId,
      title: `Tarefa ${taskId}`,
      status: 'A_FAZER',
      is_critical: false,
      is_required: true,
      requires_evidence: false,
      lock_version: 1,
      sort_order: index + 1
    }))
  }
}

describe('work-process-selection cascade', () => {
  it('marca processo e todas as tarefas embutidas', () => {
    const processes = [processFixture(10, [1, 2]), processFixture(20, [3])]
    const next = cascadeProcessTaskSelection({
      processes,
      processSelection: {},
      taskSelection: {},
      changedProcessIds: [10],
      selected: true
    })
    expect(next.processSelection).toEqual({ 10: true })
    expect(next.taskSelection).toEqual({ 1: true, 2: true })
  })

  it('desmarca processo e limpa só as tarefas dele', () => {
    const processes = [processFixture(10, [1, 2]), processFixture(20, [3])]
    const next = cascadeProcessTaskSelection({
      processes,
      processSelection: { 10: true, 20: true },
      taskSelection: { 1: true, 2: true, 3: true },
      changedProcessIds: [10],
      selected: false
    })
    expect(next.processSelection).toEqual({ 20: true })
    expect(next.taskSelection).toEqual({ 3: true })
  })

  it('header seleciona todos os processos e tarefas da página', () => {
    const processes = [processFixture(10, [1, 2]), processFixture(20, [3])]
    expect(cascadeSelectAllProcessesOnPage({ processes, selected: true })).toEqual({
      processSelection: { 10: true, 20: true },
      taskSelection: { 1: true, 2: true, 3: true }
    })
    expect(cascadeSelectAllProcessesOnPage({ processes, selected: false })).toEqual({
      processSelection: {},
      taskSelection: {}
    })
  })

  it('ordena tarefas por sort_order', () => {
    const process = processFixture(1, [9, 8])
    process.tasks![0]!.sort_order = 2
    process.tasks![1]!.sort_order = 1
    expect(sortedProcessTasks(process).map(t => t.id)).toEqual([8, 9])
  })

  it('página de processos usa cascata no checkbox do header e da linha', () => {
    const page = readFileSync(resolve(process.cwd(), 'app/pages/work/processes/index.vue'), 'utf8')
    expect(page).toContain('setAllPageSelected')
    expect(page).toContain('setProcessSelected')
    expect(page).toContain('cascadeSelectAllProcessesOnPage')
    expect(page).toContain('cascadeProcessTaskSelection')
    expect(page).toContain('pageSelectionState')
    expect(page).toContain('selectionSnapshot[processId]')
    expect(page).toContain(':get-row-id="(row) => String(row.id)"')
  })
})
