import type { TaskStatus } from '~/types/work'

/** Ações de transição/claim disponíveis no select inline. */
export type WorkTaskInlineAction = 'start' | 'complete' | 'resume' | 'block' | 'claim'

export interface WorkTaskStatusOption {
  label: string
  value: WorkTaskInlineAction
}

/**
 * Opções de status/transição a partir do estado atual da tarefa.
 * Terminal (CONCLUIDA/DISPENSADA) → sem opções (reopen só via detalhe/ADMIN).
 */
export function workTaskStatusOptions(
  status: TaskStatus,
  opts?: { canClaim?: boolean }
): WorkTaskStatusOption[] {
  const options: WorkTaskStatusOption[] = []

  if (status === 'A_FAZER') {
    options.push(
      { label: 'Iniciar', value: 'start' },
      { label: 'Concluir', value: 'complete' },
      { label: 'Impedir', value: 'block' }
    )
    if (opts?.canClaim) {
      options.push({ label: 'Assumir', value: 'claim' })
    }
    return options
  }

  if (status === 'EM_PROGRESSO') {
    return [
      { label: 'Concluir', value: 'complete' },
      { label: 'Impedir', value: 'block' }
    ]
  }

  if (status === 'IMPEDIDA') {
    return [
      { label: 'Retomar', value: 'resume' },
      { label: 'Concluir', value: 'complete' }
    ]
  }

  return []
}
