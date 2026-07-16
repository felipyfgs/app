import { describe, expect, it, vi } from 'vitest'

vi.mock('#components', () => ({
  UButton: 'button'
}))

const { sortHeader } = await import('../../app/utils/table-sort')

describe('sortHeader', () => {
  it.each([
    {
      direction: false as const,
      ariaLabel: 'Razão social: coluna ordenável, sem ordenação. Ative para ordenar em ordem crescente.',
      icon: 'i-lucide-arrow-up-down'
    },
    {
      direction: 'asc' as const,
      ariaLabel: 'Razão social: coluna ordenável, ordenada em ordem crescente. Ative para ordenar em ordem decrescente.',
      icon: 'i-lucide-arrow-up-narrow-wide'
    },
    {
      direction: 'desc' as const,
      ariaLabel: 'Razão social: coluna ordenável, ordenada em ordem decrescente. Ative para ordenar em ordem crescente.',
      icon: 'i-lucide-arrow-down-wide-narrow'
    }
  ])('expõe estado $direction e próxima direção no nome acessível', ({ direction, ariaLabel, icon }) => {
    const column = {
      getIsSorted: () => direction,
      toggleSorting: vi.fn()
    }

    const header = sortHeader('Razão social', column)

    expect(header.props?.['aria-label']).toBe(ariaLabel)
    expect(header.props?.icon).toBe(icon)
    expect(header.props?.label).toBe('Razão social')
  })

  it('mantém a alternância crescente/decrescente no acionamento do botão', () => {
    let direction: false | 'asc' | 'desc' = 'asc'
    const toggleSorting = vi.fn()
    const header = sortHeader('Razão social', {
      getIsSorted: () => direction,
      toggleSorting
    })

    header.props?.onClick()
    expect(toggleSorting).toHaveBeenLastCalledWith(true)

    direction = 'desc'
    header.props?.onClick()
    expect(toggleSorting).toHaveBeenLastCalledWith(false)
  })
})
