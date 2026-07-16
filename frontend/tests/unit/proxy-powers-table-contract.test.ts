import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('contrato incremental de procurações', () => {
  const source = readFileSync(resolve(__dirname, '../../app/pages/settings/proxies.vue'), 'utf8')

  it('mantém o filtro digitado separado do filtro aplicado ao feed', () => {
    expect(source).toContain('const clientId = ref(\'\')')
    expect(source).toContain('const appliedClientId = ref<number | null>(null)')
    expect(source).toContain('client_id: appliedClientId.value ?? undefined')
    expect(source).toContain('@click="applyClientFilter"')
    expect(source).not.toContain('client_id: clientId.value ? Number(clientId.value)')
    expect(source).not.toContain('watch(clientId')
  })

  it('sincroniza somente o cliente do filtro aplicado', () => {
    expect(source).toContain('draftClientId.value === appliedClientId.value')
    expect(source).toContain('if (!canSyncAppliedClient.value || appliedClientId.value === null)')
    expect(source).toContain('{ client_id: appliedClientId.value }')
    expect(source).toContain(':disabled="!canSyncAppliedClient"')
  })

  it('limpa campo e filtro aplicado quando a sessão muda', () => {
    expect(source).toMatch(/watch\(sessionEpoch[\s\S]*clientId\.value = ''[\s\S]*appliedClientId\.value = null/)
  })
})
