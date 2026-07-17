import { describe, expect, it } from 'vitest'
import {
  buildKillSwitchOffBody,
  defaultChangeWindow,
  expectedOwnerConfirmationPhrase,
  isDualRolePolicy,
  isOwnerConfirmationPolicy,
  validateOwnerConfirmationInput
} from '../../app/utils/serpro-owner-confirmation'

describe('serpro-owner-confirmation', () => {
  it('gera frase CONFIRMO-{ACTION}', () => {
    expect(expectedOwnerConfirmationPhrase('KILL_SWITCH_OFF')).toBe('CONFIRMO-KILL_SWITCH_OFF')
    expect(expectedOwnerConfirmationPhrase('credential_cutover')).toBe('CONFIRMO-CREDENTIAL_CUTOVER')
  })

  it('classifica políticas', () => {
    expect(isOwnerConfirmationPolicy('OWNER_CONFIRMATION')).toBe(true)
    expect(isDualRolePolicy('DUAL_ROLE')).toBe(true)
    expect(isOwnerConfirmationPolicy('DUAL_ROLE')).toBe(false)
  })

  it('valida motivo, frase e senha', () => {
    const expected = 'CONFIRMO-KILL_SWITCH_OFF'
    expect(validateOwnerConfirmationInput({
      reason: '',
      confirmationPhrase: expected,
      expectedPhrase: expected,
      password: 'x'
    }).ok).toBe(false)

    expect(validateOwnerConfirmationInput({
      reason: 'ok',
      confirmationPhrase: 'ERRADA',
      expectedPhrase: expected,
      password: 'x'
    }).ok).toBe(false)

    expect(validateOwnerConfirmationInput({
      reason: 'ok',
      confirmationPhrase: expected,
      expectedPhrase: expected,
      password: ''
    }).ok).toBe(false)

    expect(validateOwnerConfirmationInput({
      reason: 'reabrir após drill',
      confirmationPhrase: expected,
      expectedPhrase: expected,
      password: 'secret'
    })).toEqual({ ok: true })
  })

  it('monta body de kill-off com janela e sem segredo de vault', () => {
    const body = buildKillSwitchOffBody({
      reason: ' drill ',
      confirmationPhrase: 'CONFIRMO-KILL_SWITCH_OFF',
      window: {
        change_window_start: '2026-07-17T00:00:00.000Z',
        change_window_end: '2026-07-17T01:00:00.000Z'
      }
    })
    expect(body.active).toBe(false)
    expect(body.reason).toBe('drill')
    expect(body.confirmation_phrase).toBe('CONFIRMO-KILL_SWITCH_OFF')
    expect(body.change_window_start).toBe('2026-07-17T00:00:00.000Z')
    expect(JSON.stringify(body)).not.toMatch(/pfx|vault|consumer_secret|token/i)
  })

  it('defaultChangeWindow é vigente em torno de agora', () => {
    const now = new Date('2026-07-17T12:00:00.000Z')
    const w = defaultChangeWindow(now)
    expect(new Date(w.change_window_start).getTime()).toBeLessThan(now.getTime())
    expect(new Date(w.change_window_end).getTime()).toBeGreaterThan(now.getTime())
  })

  it('canário permanece política dual (não OWNER)', () => {
    // Regressão documental: BILLABLE_CANARY não usa frase singleton como autorização completa.
    expect(isDualRolePolicy('DUAL_ROLE')).toBe(true)
    expect(isOwnerConfirmationPolicy('DUAL_ROLE')).toBe(false)
    expect(expectedOwnerConfirmationPhrase('BILLABLE_CANARY')).toBe('CONFIRMO-BILLABLE_CANARY')
  })
})
