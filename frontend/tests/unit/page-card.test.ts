import { describe, expect, it } from 'vitest'
import { PAGE_CARD_PANEL, PAGE_CARD_SECTION_BODY, PAGE_CARD_SECTION_HEADER, pageCardRootClass } from '../../app/utils/page-card'

describe('page-card taxonomy', () => {
  it('mapeia papéis canônicos', () => {
    expect(pageCardRootClass('section-header')).toMatch(/mb-4/)
    expect(pageCardRootClass('section-body')).toMatch(/overflow-hidden/)
    expect(pageCardRootClass('panel')).toMatch(/min-w-0/)
    expect(PAGE_CARD_SECTION_HEADER.variant).toBe('naked')
    expect(PAGE_CARD_SECTION_BODY.variant).toBe('subtle')
    expect(PAGE_CARD_PANEL.variant).toBe('subtle')
  })
})
