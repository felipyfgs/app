import type { MeUser } from '~/types/api'
import { describe, expect, it } from 'vitest'
import { unwrapMeUser } from '~/utils/permissions'

describe('identidade da sessão', () => {
  const user = {
    id: 42,
    role: 'ADMIN'
  } as MeUser

  it('aceita tanto o usuário direto quanto o envelope da API', () => {
    expect(unwrapMeUser(user)).toBe(user)
    expect(unwrapMeUser({ data: user })).toBe(user)
  })

  it('trata respostas não estruturadas como sessão indisponível', () => {
    expect(unwrapMeUser('<br /> Fatal error')).toBeNull()
    expect(unwrapMeUser(500)).toBeNull()
    expect(unwrapMeUser({ data: '<br /> Fatal error' })).toBeNull()
    expect(unwrapMeUser(null)).toBeNull()
  })
})
