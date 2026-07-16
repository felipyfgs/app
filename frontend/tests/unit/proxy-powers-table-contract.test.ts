import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * OpenSpec: importação manual de procurações removida do tenant.
 * /settings/proxies → redirect para /settings; status oficial na lista de clientes.
 */
describe('procurações — superfície tenant sem importação manual', () => {
  const proxies = readFileSync(resolve(__dirname, '../../app/pages/settings/proxies.vue'), 'utf8')
  const middleware = readFileSync(resolve(__dirname, '../../app/middleware/auth.global.ts'), 'utf8')
  const clients = readFileSync(resolve(__dirname, '../../app/pages/clients/index.vue'), 'utf8')

  it('rota legada /settings/proxies redireciona para settings unificado', () => {
    expect(proxies).toMatch(/navigateTo\(['"]\/settings['"]/)
    expect(proxies).not.toContain('appliedClientId')
    expect(proxies).not.toMatch(/importar|override|TaxProxyPower/i)
    expect(middleware).toMatch(/settings\/proxies/)
    expect(middleware).toContain('navigateTo(\'/settings\'')
  })

  it('lista de clientes expõe coluna Procuração (estado oficial)', () => {
    expect(clients).toContain('procuracao')
    expect(clients).toContain('ClientsClientProcuracaoBadge')
    expect(clients).not.toMatch(/importar procuraç|override de poder/i)
  })
})
