import { describe, expect, it } from 'vitest'
import {
  formatCompetence,
  formatDueDate,
  highestRiskColor,
  processStatusLabel,
  queueBucketLabel,
  taskStatusIcon,
  taskStatusLabel,
  workRiskLabel
} from '../../app/utils/work-labels'
import {
  addDays,
  formatYmd,
  monthGrid,
  navigateDate,
  parseYmd,
  rangeForView,
  weekDates
} from '../../app/composables/useWorkCalendarRange'
import {
  parseWorkQueueQuery,
  serializeWorkQueueQuery
} from '../../app/composables/useWorkQueueFilters'

describe('work-labels', () => {
  it('mapeia status, riscos e buckets com texto e ícone', () => {
    expect(taskStatusLabel('A_FAZER')).toBe('A fazer')
    expect(taskStatusIcon('CONCLUIDA')).toContain('check')
    expect(processStatusLabel('IMPEDIDO')).toBe('Impedido')
    expect(workRiskLabel('EM_MULTA')).toBe('Em multa')
    expect(highestRiskColor(['ATRASADA', 'EM_MULTA'])).toBe('error')
    expect(queueBucketLabel('VENCE_HOJE')).toBe('Vence hoje')
  })

  it('formata competência e prazo sem converter CNPJ', () => {
    expect(formatCompetence('2026-06')).toBe('06/2026')
    expect(formatDueDate('2026-06-15')).toBe('15/06/2026')
    expect(formatDueDate(null)).toBe('Sem prazo')
  })
})

describe('useWorkCalendarRange utils', () => {
  it('calcula semana, grade e navegação sem inventar horários', () => {
    const week = weekDates('2026-06-15')
    expect(week).toHaveLength(7)
    expect(week[0]).toBe('2026-06-15') // segunda
    expect(week[6]).toBe('2026-06-21')

    const grid = monthGrid(2026, 6)
    expect(grid).toHaveLength(42)
    expect(rangeForView('month', '2026-06-15').from).toBe(grid[0]!.date)

    expect(navigateDate('day', '2026-06-15', 1)).toBe('2026-06-16')
    expect(addDays('2026-06-30', 1)).toBe('2026-07-01')
    expect(formatYmd(2026, 7, 1)).toBe('2026-07-01')
    expect(parseYmd('2026-07-01')).toEqual({ y: 2026, m: 7, d: 1 })
  })
})

describe('useWorkQueueFilters utils', () => {
  it('normaliza query e omite vazios', () => {
    const parsed = parseWorkQueueQuery({
      tab: 'atrasadas',
      task: '12',
      q: ' das ',
      page: '2',
      department_id: '3'
    })
    expect(parsed.tab).toBe('atrasadas')
    expect(parsed.task).toBe(12)
    expect(parsed.department_id).toBe(3)
    expect(parsed.page).toBe(2)

    const serialized = serializeWorkQueueQuery({
      tab: 'open',
      task: null,
      q: '',
      department_id: null,
      assignee_membership_id: null,
      client_id: null,
      scope: 'default',
      page: 1,
      per_page: 25
    })
    expect(serialized.tab).toBeUndefined()
    expect(serialized.page).toBeUndefined()
    expect(serialized.q).toBeUndefined()
  })
})
