import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  procuracaoActionHint,
  procuracaoChipLabel,
  procuracaoLabel,
  procuracaoTone,
  procuracaoValidityLabel
} from '../../app/utils/procuracao'

describe('procuração na lista de clientes', () => {
  it('traduz os cinco estados operacionais sem presumir autorização', () => {
    expect(procuracaoLabel('authorized')).toBe('Ativa')
    expect(procuracaoLabel('expiring')).toBe('A vencer')
    expect(procuracaoLabel('expired')).toBe('Vencida')
    expect(procuracaoLabel('missing')).toBe('Sem procuração')
    expect(procuracaoLabel('unverified')).toBe('Não verificada')
    expect(procuracaoTone('expiring')).toBe('warning')
    expect(procuracaoActionHint('expiring')).toContain('Renove')
    expect(procuracaoChipLabel('authorized', '2026-08-30T00:00:00Z')).toContain('Válida até')
    expect(procuracaoChipLabel('expiring', '2026-08-30T00:00:00Z')).toContain('A vencer')
    expect(procuracaoChipLabel('expired', '2026-07-01T00:00:00Z')).toContain('Vencida')
  })

  it('mostra a validade oficial sem criar sincronização', () => {
    expect(procuracaoValidityLabel('authorized', '2026-08-30T00:00:00Z')).toContain('Vence')
    expect(procuracaoValidityLabel('expired', '2026-07-01T00:00:00Z')).toContain('Venceu')
    expect(procuracaoValidityLabel('missing', null)).toBeNull()
  })

  it('coluna repassa somente a projeção recebida pela lista', () => {
    const source = readFileSync(resolve(__dirname, '../../app/pages/clients/index.vue'), 'utf8')
    const badge = readFileSync(resolve(__dirname, '../../app/components/clients/ClientProcuracaoBadge.vue'), 'utf8')
    expect(source).toContain(':valid-to="row.original.procuracao_valid_to"')
    expect(source).toContain('compact')
    expect(source).not.toMatch(/syncProxyPowers|office_id/)
    expect(badge).toContain('procuracaoValidityLabel')
  })
})
