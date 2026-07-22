import { describe, expect, it } from 'vitest'
import { workTaskStatusOptions } from '../../app/utils/work-task-status-options'

describe('workTaskStatusOptions', () => {
  it('oferece transições a partir de A_FAZER com claim opcional', () => {
    expect(workTaskStatusOptions('A_FAZER').map(o => o.value)).toEqual([
      'start',
      'complete',
      'block'
    ])
    expect(workTaskStatusOptions('A_FAZER', { canClaim: true }).map(o => o.value)).toContain('claim')
  })

  it('restringe opções por status ativo e terminal', () => {
    expect(workTaskStatusOptions('EM_PROGRESSO').map(o => o.value)).toEqual([
      'complete',
      'block'
    ])
    expect(workTaskStatusOptions('IMPEDIDA').map(o => o.value)).toEqual([
      'resume',
      'complete'
    ])
    expect(workTaskStatusOptions('CONCLUIDA')).toEqual([])
    expect(workTaskStatusOptions('DISPENSADA')).toEqual([])
  })
})
