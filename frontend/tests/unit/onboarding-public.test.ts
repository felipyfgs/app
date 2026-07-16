import { describe, expect, it } from 'vitest'
import {
  consumeActivationTokenFromLocation,
  extractActivationTokenFromHash,
  stripActivationHashFromLocation
} from '~/utils/activation'

describe('onboarding token no fragmento', () => {
  it('consome token e limpa o hash', () => {
    const token = 'deploy-token-with-enough-entropy-32c'
    const location = {
      hash: `#token=${token}`,
      pathname: '/onboarding',
      search: ''
    }
    let replaced = ''
    const historyApi = {
      replaceState: (_s: unknown, _t: string, url: string) => {
        replaced = url
        location.hash = ''
      }
    }

    const got = consumeActivationTokenFromLocation(location, historyApi)
    expect(got).toBe(token)
    expect(replaced).toBe('/onboarding')
    expect(extractActivationTokenFromHash(location.hash)).toBeNull()
  })

  it('strip é no-op sem hash', () => {
    const location = { hash: '', pathname: '/onboarding', search: '' }
    let called = false
    stripActivationHashFromLocation(location, {
      replaceState: () => {
        called = true
      }
    })
    expect(called).toBe(false)
  })
})
