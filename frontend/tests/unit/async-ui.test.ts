import { describe, expect, it } from 'vitest'
import {
  ASYNC_UI_COPY,
  asyncMessageForStatus,
  initialAsyncUi,
  markFailure,
  markLoading,
  markSuccess
} from '../../app/utils/async-ui'
import { chooseOverlay } from '../../app/utils/overlay-ui'

describe('async-ui', () => {
  it('preserva lastGood em falha de refresh', () => {
    let snap = initialAsyncUi<string[]>(null)
    snap = markLoading(snap)
    snap = markSuccess(snap, ['a', 'b'])
    expect(snap.state).toBe('success')
    expect(snap.data).toEqual(['a', 'b'])

    snap = markLoading(snap)
    snap = markFailure(snap, 'rede')
    expect(snap.stale).toBe(true)
    expect(snap.data).toEqual(['a', 'b'])
    expect(snap.errorMessage).toBe('rede')
    expect(snap.state).toBe('success')
  })

  it('falha inicial sem dados vira error', () => {
    let snap = initialAsyncUi<string[]>(null)
    snap = markLoading(snap)
    snap = markFailure(snap, 'boom')
    expect(snap.state).toBe('error')
    expect(snap.data).toBeNull()
    expect(snap.stale).toBe(false)
  })

  it('lista vazia vira empty', () => {
    let snap = initialAsyncUi<string[]>(null)
    snap = markSuccess(snap, [])
    expect(snap.state).toBe('empty')
  })

  it('mapeia 403/409/422', () => {
    expect(asyncMessageForStatus(403)).toBe(ASYNC_UI_COPY.forbidden)
    expect(asyncMessageForStatus(409)).toBe(ASYNC_UI_COPY.conflict)
    expect(asyncMessageForStatus(422)).toBe(ASYNC_UI_COPY.validation)
  })
})

describe('overlay-ui', () => {
  it('escolhe modal para confirmação e drawer para detalhe mobile', () => {
    expect(chooseOverlay({ task: 'confirm' }).kind).toBe('modal')
    expect(chooseOverlay({ task: 'detail', viewport: 'mobile' }).kind).toBe('drawer')
    expect(chooseOverlay({ task: 'detail', viewport: 'desktop' }).kind).toBe('slideover')
    expect(chooseOverlay({ task: 'long-flow' }).kind).toBe('route')
    expect(chooseOverlay({ task: 'hint' }).kind).toBe('tooltip')
  })
})
