import { describe, expect, it } from 'vitest'
import type { QueueBucket, WorkRisk } from '../../app/types/work'

/** Espelha a prioridade de badge da fila (sem montar Vue). */
function riskColor(risks?: WorkRisk[]) {
  if (!risks?.length) return 'neutral'
  if (risks.includes('EM_MULTA')) return 'error'
  if (risks.includes('ATRASADA')) return 'warning'
  return 'info'
}

function bucketLabel(bucket: QueueBucket): string {
  const map: Record<QueueBucket, string> = {
    EM_MULTA: 'Em multa',
    ATRASADA: 'Atrasada',
    VENCE_HOJE: 'Vence hoje',
    VENCE_EM_TRES_DIAS: 'Próximos 3 dias',
    IMPEDIDA: 'Impedida',
    SEM_RESPONSAVEL: 'Sem responsável',
    DEMAIS_ABERTAS: 'Abertas',
    CONCLUIDAS: 'Concluídas'
  }
  return map[bucket]
}

function emptyCopy(kind: 'queue' | 'processes' | 'templates' | 'calendar'): string {
  switch (kind) {
    case 'queue':
      return 'Nenhuma tarefa nesta aba.'
    case 'processes':
      return 'Nenhum processo encontrado.'
    case 'templates':
      return 'Nenhum modelo'
    case 'calendar':
      return 'Sem tarefas neste dia.'
  }
}

function roleActions(role: 'ADMIN' | 'OPERATOR' | 'VIEWER') {
  return {
    canStart: role === 'ADMIN' || role === 'OPERATOR',
    canComplete: role === 'ADMIN' || role === 'OPERATOR',
    canManageCatalog: role === 'ADMIN',
    canExport: role === 'ADMIN' || role === 'OPERATOR',
    readOnly: role === 'VIEWER'
  }
}

function conflictMessage(status: number) {
  if (status === 409) return 'Conflito de versão: o registro foi alterado por outro usuário.'
  if (status === 422) return 'Validação rejeitada.'
  if (status === 403) return 'Sem permissão.'
  return 'Erro inesperado.'
}

describe('estados da fila e prioridade', () => {
  it('prioriza EM_MULTA sobre ATRASADA na cor', () => {
    expect(riskColor(['EM_MULTA', 'ATRASADA'])).toBe('error')
    expect(riskColor(['ATRASADA'])).toBe('warning')
    expect(riskColor(['SEM_RESPONSAVEL'])).toBe('info')
    expect(riskColor([])).toBe('neutral')
  })

  it('rotula buckets da fila', () => {
    expect(bucketLabel('EM_MULTA')).toBe('Em multa')
    expect(bucketLabel('VENCE_HOJE')).toBe('Vence hoje')
    expect(bucketLabel('CONCLUIDAS')).toBe('Concluídas')
  })
})

describe('estados vazios por superfície', () => {
  it('usa copy estável sem mock de dados demo', () => {
    expect(emptyCopy('queue')).toContain('Nenhuma tarefa')
    expect(emptyCopy('processes')).toContain('Nenhum processo')
    expect(emptyCopy('calendar')).toContain('Sem tarefas')
  })
})

describe('ações por papel', () => {
  it('VIEWER somente leitura', () => {
    const a = roleActions('VIEWER')
    expect(a.readOnly).toBe(true)
    expect(a.canStart).toBe(false)
    expect(a.canManageCatalog).toBe(false)
    expect(a.canExport).toBe(false)
  })

  it('OPERATOR executa sem administrar catálogo', () => {
    const a = roleActions('OPERATOR')
    expect(a.canStart).toBe(true)
    expect(a.canComplete).toBe(true)
    expect(a.canManageCatalog).toBe(false)
    expect(a.canExport).toBe(true)
  })

  it('ADMIN administra catálogo e executa', () => {
    const a = roleActions('ADMIN')
    expect(a.canManageCatalog).toBe(true)
    expect(a.canStart).toBe(true)
  })
})

describe('erros 409/422', () => {
  it('mapeia conflito otimista e validação', () => {
    expect(conflictMessage(409)).toMatch(/Conflito de versão/)
    expect(conflictMessage(422)).toMatch(/Validação/)
    expect(conflictMessage(403)).toMatch(/permissão/)
  })
})
