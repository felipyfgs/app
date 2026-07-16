/**
 * Fluxos públicos de ativação — sem Playwright.
 * Cobre fragmento #token=, paths públicos e ausência de envio de e-mail.
 */
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  consumeActivationTokenFromLocation,
  extractActivationTokenFromHash,
  stripActivationHashFromLocation
} from '../../app/utils/activation'
import { isAuthPublicPath } from '../../app/utils/auth-public'

const APP = join(process.cwd(), 'app')
const longToken = `tok_${'a'.repeat(40)}`

describe('activation public utils', () => {
  it('extrai token do fragmento', () => {
    expect(extractActivationTokenFromHash(`#token=${longToken}`)).toBe(longToken)
    expect(extractActivationTokenFromHash(`token=${longToken}`)).toBe(longToken)
    expect(extractActivationTokenFromHash('#token=curto')).toBeNull()
    expect(extractActivationTokenFromHash('')).toBeNull()
  })

  it('remove o fragmento e devolve o token uma vez', () => {
    const location = {
      hash: `#token=${longToken}`,
      pathname: '/activate',
      search: ''
    }
    let replaced = ''
    const history = {
      replaceState: (_s: unknown, _t: string, url: string) => {
        replaced = url
        location.hash = ''
      }
    }
    const token = consumeActivationTokenFromLocation(location, history)
    expect(token).toBe(longToken)
    expect(replaced).toBe('/activate')
    stripActivationHashFromLocation({ hash: '', pathname: '/x', search: '' }, history)
  })

  it('libera /activate e /first-access sem sessão', () => {
    expect(isAuthPublicPath('/activate')).toBe(true)
    expect(isAuthPublicPath('/first-access')).toBe(true)
    expect(isAuthPublicPath('/login')).toBe(true)
    expect(isAuthPublicPath('/settings/team')).toBe(false)
    expect(isAuthPublicPath('/admin/offices')).toBe(false)
  })
})

describe('activation surface files', () => {
  it('middleware e páginas públicas existem e não prometem e-mail', () => {
    const mw = readFileSync(join(APP, 'middleware/auth.global.ts'), 'utf8')
    expect(mw).toMatch(/activate|isAuthPublicPath|AUTH_PUBLIC/)

    for (const rel of ['pages/activate.vue', 'pages/first-access.vue']) {
      const text = readFileSync(join(APP, rel), 'utf8')
      expect(text).toMatch(/layout:\s*['"]auth['"]/)
      expect(text.toLowerCase()).not.toMatch(/enviar e-mail|send email|nodemailer|mailable/)
    }
  })

  it('wizard e equipe não usam envio externo', () => {
    for (const rel of [
      'pages/admin/offices/new.vue',
      'pages/settings/team.vue',
      'components/activation/OneTimeSecret.vue'
    ]) {
      const text = readFileSync(join(APP, rel), 'utf8')
      expect(text.toLowerCase()).not.toMatch(/enviar e-mail|send email|sms|whatsapp/)
    }
  })
})
