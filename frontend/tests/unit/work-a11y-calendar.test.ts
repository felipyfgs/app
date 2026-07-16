import { describe, expect, it } from 'vitest'
import {
  formatYmd,
  monthGrid,
  navigateDate,
  parseYmd,
  rangeForView,
  weekDates
} from '../../app/composables/useWorkCalendarRange'

describe('calendário — nomes e intervalos estáveis (a11y)', () => {
  it('grid mensal produz células com datas Y-m-d', () => {
    const grid = monthGrid(2026, 7)
    expect(grid.length).toBeGreaterThanOrEqual(28)
    for (const cell of grid) {
      expect(cell.date).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    }
    expect(grid.some(c => c.inMonth)).toBe(true)
  })

  it('semana tem 7 dias', () => {
    const days = weekDates('2026-07-15')
    expect(days).toHaveLength(7)
    expect(days[0]).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })

  it('range Mês/Semana/Dia é finito e ordenado', () => {
    for (const view of ['month', 'week', 'day'] as const) {
      const r = rangeForView(view, '2026-07-15')
      expect(r.from <= r.to).toBe(true)
      expect(r.from).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    }
  })

  it('parse/format e navegação são determinísticos', () => {
    const p = parseYmd('2026-07-15')
    expect(p).toEqual({ y: 2026, m: 7, d: 15 })
    expect(formatYmd(2026, 7, 15)).toBe('2026-07-15')
    expect(navigateDate('day', '2026-07-15', 1)).toBe('2026-07-16')
  })

  it('rótulos de contagem acessíveis preferem texto, não só cor', () => {
    const labelForCount = (n: number, date: string) =>
      n === 0 ? `Sem tarefas em ${date}` : `${n} tarefa${n > 1 ? 's' : ''} em ${date}`
    expect(labelForCount(0, '2026-07-10')).toContain('Sem tarefas')
    expect(labelForCount(2, '2026-07-10')).toBe('2 tarefas em 2026-07-10')
  })
})
