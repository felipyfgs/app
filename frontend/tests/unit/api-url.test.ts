import { describe, expect, it } from 'vitest'
import { resolveApiUrl } from '../../app/utils/api-url'

describe('resolveApiUrl', () => {
  it('mantém path relativo quando apiBase está vazio (same-origin)', () => {
    expect(resolveApiUrl('/api/v1/fiscal/simples-mei/pgdasd/artifacts/1/download'))
      .toBe('/api/v1/fiscal/simples-mei/pgdasd/artifacts/1/download')
  })

  it('prefixa com proxy Sanctum em make dev', () => {
    expect(resolveApiUrl(
      '/api/v1/fiscal/simples-mei/pgdasd/artifacts/1/download',
      '/api/sanctum'
    )).toBe('/api/sanctum/api/v1/fiscal/simples-mei/pgdasd/artifacts/1/download')
  })

  it('não duplica o prefixo do proxy', () => {
    expect(resolveApiUrl(
      '/api/sanctum/api/v1/fiscal/evidence/1/download',
      '/api/sanctum'
    )).toBe('/api/sanctum/api/v1/fiscal/evidence/1/download')
  })

  it('preserva URLs absolutas', () => {
    expect(resolveApiUrl('https://app.example/api/v1/x', '/api/sanctum'))
      .toBe('https://app.example/api/v1/x')
  })
})
