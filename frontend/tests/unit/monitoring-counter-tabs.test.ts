import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('contadores do monitoramento', () => {
  const source = readFileSync(
    resolve(__dirname, '../../app/components/monitoring/KpiStrip.vue'),
    'utf8'
  )

  it('usa tabs compactas em vez de cards', () => {
    expect(source).toContain('<UTabs')
    expect(source).toContain(':content="false"')
    expect(source).toContain('variant="pill"')
    expect(source).not.toContain('<UPageCard')
    expect(source).not.toContain('<ShellKpiStrip')
    // Não poluir as tabs cápsula com “Atualizando…” em todo refresh
    expect(source).not.toMatch(/Atualizando/)
  })

  it('mantém os contadores e o filtro por situação', () => {
    for (const key of ['total', 'up_to_date', 'processing', 'pending', 'attention', 'error']) {
      expect(source).toContain(`value: '${key}'`)
    }

    expect(source).toContain('@update:model-value="onSelect"')
    expect(source).toContain('fiscalKpiSituationFilter(k)')
  })
})
