import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const panel = readFileSync(resolve(process.cwd(), 'app/components/clients/ClientPnrRenunciationsPanel.vue'), 'utf8')
const clientDetail = readFileSync(resolve(process.cwd(), 'app/pages/monitoring/clients/[clientId].vue'), 'utf8')

describe('painel de renúncias PNR', () => {
  it('fica no detalhe do cliente com estados e ações explícitas de leitura', () => {
    expect(clientDetail).toContain('\'renunciations\'')
    expect(clientDetail).toContain('<ClientPnrRenunciationsPanel')
    expect(panel).toContain('USkeleton')
    expect(panel).toContain('Nenhuma renúncia encontrada')
    expect(panel).toContain('Histórico indisponível')
    expect(panel).toContain('Demonstração SERPRO (Trial)')
    expect(panel).toContain('SERPRO real — ainda sem canário de produção')
  })

  it('não oferece a mutação solicitar renúncia nem envia office_id', () => {
    expect(panel).not.toContain('solicitar_renuncia')
    expect(panel).not.toContain('office_id')
    expect(panel).toContain('Solicitar renúncia não está disponível nesta tela.')
  })
})
