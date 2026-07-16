import { describe, expect, it, vi } from 'vitest'
import { usePagedTable, laravelPageBatch } from '../../app/composables/usePagedTable'

describe('usePagedTable', () => {
  it('carrega uma página por vez sem acumular', async () => {
    const load = vi.fn(async ({ page }: { page: number }) =>
      laravelPageBatch({
        data: [{ id: page }],
        meta: { current_page: page, last_page: 3, total: 3 }
      })
    )

    const table = usePagedTable<{ id: number }>({
      load,
      getKey: row => row.id
    })

    await table.resetAndLoad()
    expect(table.rows.value).toEqual([{ id: 1 }])
    expect(table.total.value).toBe(3)

    await table.setPage(2)
    expect(table.rows.value).toEqual([{ id: 2 }])
    expect(load).toHaveBeenCalledTimes(2)
  })
})
