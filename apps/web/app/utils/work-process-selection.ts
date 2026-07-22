import type { OperationalProcess, OperationalProcessTask } from '~/types/work'

/** Tarefas ordenadas de um processo (mesma regra da lista). */
export function sortedProcessTasks(process: OperationalProcess): OperationalProcessTask[] {
  return [...(process.tasks || [])].sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
}

/**
 * Aplica seleção em cascata: marcar/desmarcar processos também marca/desmarca
 * todas as tarefas embutidas desses processos.
 */
export function cascadeProcessTaskSelection(input: {
  processes: OperationalProcess[]
  processSelection: Record<string, boolean>
  taskSelection: Record<string, boolean>
  /** IDs de processo cujo estado acabou de mudar (para sincronizar só o necessário). */
  changedProcessIds: number[]
  selected: boolean
}): { processSelection: Record<string, boolean>, taskSelection: Record<string, boolean> } {
  const processSelection = { ...input.processSelection }
  const taskSelection = { ...input.taskSelection }
  const changed = new Set(input.changedProcessIds.map(String))

  for (const process of input.processes) {
    const key = String(process.id)
    if (!changed.has(key)) continue

    if (input.selected) {
      processSelection[key] = true
      for (const task of sortedProcessTasks(process)) {
        taskSelection[String(task.id)] = true
      }
    } else {
      Reflect.deleteProperty(processSelection, key)
      for (const task of sortedProcessTasks(process)) {
        Reflect.deleteProperty(taskSelection, String(task.id))
      }
    }
  }

  return { processSelection, taskSelection }
}

/** Seleciona ou limpa todos os processos da página e todas as tarefas deles. */
export function cascadeSelectAllProcessesOnPage(input: {
  processes: OperationalProcess[]
  selected: boolean
}): { processSelection: Record<string, boolean>, taskSelection: Record<string, boolean> } {
  if (!input.selected) {
    return { processSelection: {}, taskSelection: {} }
  }

  const processSelection: Record<string, boolean> = {}
  const taskSelection: Record<string, boolean> = {}
  for (const process of input.processes) {
    processSelection[String(process.id)] = true
    for (const task of sortedProcessTasks(process)) {
      taskSelection[String(task.id)] = true
    }
  }
  return { processSelection, taskSelection }
}
