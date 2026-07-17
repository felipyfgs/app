import { describe, expect, it } from 'vitest'
import {
  normalizeTenantPath,
  resolveInboxItemLink,
  SERPRO_INBOX_ROUTES
} from '../../app/utils/inbox-links'

describe('inbox deep-links SERPRO', () => {
  it('normaliza paths legados para rotas existentes', () => {
    expect(normalizeTenantPath('/settings/integracao-serpro')).toBe('/conta/escritorio')
    expect(normalizeTenantPath('/settings/consumo')).toBe('/conta/consumo')
    expect(normalizeTenantPath('/clients/42/procuracoes')).toBe('/clients/42')
    expect(normalizeTenantPath('/settings/proxies')).toBe('/conta/escritorio')
    expect(normalizeTenantPath('/fiscal/runs/99')).toBe('/monitoring')
  })

  it('resolve alertas SERPRO para settings onboarding', () => {
    expect(resolveInboxItemLink({
      type: 'serpro_termo_missing',
      links: { serpro_authorization: '/settings/integracao-serpro' },
      client_id: null,
      reasons: []
    })).toBe(SERPRO_INBOX_ROUTES.authorization)

    expect(resolveInboxItemLink({
      type: 'serpro_token_expiring',
      links: {},
      client_id: null,
      reasons: ['REFRESH_PROCURADOR_TOKEN']
    })).toBe('/conta/escritorio')
  })

  it('resolve procurações para detalhe do cliente (sem importação manual)', () => {
    expect(resolveInboxItemLink({
      type: 'proxy_power_expired',
      links: { proxy: '/clients/7/procuracoes' },
      client_id: 7,
      reasons: []
    })).toBe('/clients/7')

    expect(resolveInboxItemLink({
      type: 'proxy_power_missing',
      links: {},
      client_id: 3,
      reasons: []
    })).toBe('/clients/3')
  })

  it('resolve consumo e consulta bloqueada de forma tenant-safe', () => {
    expect(resolveInboxItemLink({
      type: 'usage_high',
      links: { usage: '/settings/consumo' },
      client_id: null,
      reasons: []
    })).toBe('/conta/consumo')

    expect(resolveInboxItemLink({
      type: 'query_blocked',
      links: { run: '/fiscal/runs/1' },
      client_id: 9,
      reasons: []
    })).toBe('/monitoring')
  })

  it('nunca devolve rota inexistente — fallback /health', () => {
    expect(resolveInboxItemLink({
      type: 'unknown_type',
      links: { client: 'https://evil.example/x' },
      client_id: null,
      reasons: []
    })).toBe('/health')
  })
})
