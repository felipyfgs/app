import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const pagePath = resolve(__dirname, '../../app/pages/monitoring/index.vue')
const page = readFileSync(pagePath, 'utf8')

describe('monitoring insights dashboard fidelity', () => {
  it('usa grid denso 8/4 e endpoint de insights', () => {
    expect(page).toContain('monitoringInsights')
    expect(page).toContain('monitoring-insights-grid')
    expect(page).toContain('lg:col-span-8')
    expect(page).toContain('lg:col-span-4')
  })

  it('mantém copy honesta sem labels falsos da referência', () => {
    expect(page.toLowerCase()).not.toContain('sublimite')
    expect(page).not.toContain('Excluído')
    expect(page).not.toContain('SPEDs')
    expect(page).toContain('Nenhum KPI inventado')
  })

  it('reposiciona consulta manual abaixo dos insights', () => {
    const gridIdx = page.indexOf('monitoring-insights-grid')
    const manualIdx = page.indexOf('monitoring-manual-consult-explorer')
    expect(gridIdx).toBeGreaterThan(-1)
    expect(manualIdx).toBeGreaterThan(gridIdx)
  })
})

describe('monitoring insights cards copy', () => {
  const cardsDir = resolve(__dirname, '../../app/components/monitoring/insights')

  it('RBT12 card declara que não é sublimite', () => {
    const src = readFileSync(resolve(cardsDir, 'Rbt12ChartCard.vue'), 'utf8')
    expect(src).toContain('RBT12')
    expect(src).toContain('Não é sublimite anual')
    expect(src).not.toMatch(/title>\s*Sublimites/i)
  })

  it('mailbox card não inventa bucket Excluído', () => {
    const src = readFileSync(resolve(cardsDir, 'MailboxBucketsCard.vue'), 'utf8')
    expect(src).toContain('Importante')
    expect(src).toContain('Em dia')
    expect(src).toContain('Outros')
    expect(src).toContain('sem “Excluído”')
  })

  it('DIRF aparece como UNSUPPORTED no progresso', () => {
    const src = readFileSync(resolve(cardsDir, 'ObligationsProgressCard.vue'), 'utf8')
    expect(src).toContain('UNSUPPORTED')
  })
})
