import { describe, expect, it } from 'vitest'
import {
  COMPACT_BUTTON_LABEL_UI,
  DATA_TABLE_FILTERS_ROW,
  LIST_FILTER_ACTIONS_ROW,
  LIST_FILTER_SEARCH_INPUT,
  LIST_FILTER_TOOLBAR_STACK,
  SCROLLABLE_TABS_UI,
  TOUCH_SCROLL_X
} from '../../app/utils/list-filter-layout'

describe('list-filter-layout tokens', () => {
  it('toolbar empilha no mobile e fica em linha no sm+', () => {
    expect(LIST_FILTER_TOOLBAR_STACK).toContain('flex-col')
    expect(LIST_FILTER_TOOLBAR_STACK).toContain('sm:flex-row')
    expect(LIST_FILTER_SEARCH_INPUT).toContain('w-full')
    expect(LIST_FILTER_SEARCH_INPUT).toContain('sm:max-w-sm')
    expect(LIST_FILTER_SEARCH_INPUT).toContain('sm:flex-1')
    expect(LIST_FILTER_SEARCH_INPUT).not.toContain('basis-full')
  })

  it('ações e chips usam scroll touch no mobile', () => {
    expect(TOUCH_SCROLL_X).toContain('touch-pan-x')
    expect(TOUCH_SCROLL_X).toContain('overflow-x-auto')
    expect(LIST_FILTER_ACTIONS_ROW).toContain(TOUCH_SCROLL_X)
    expect(LIST_FILTER_ACTIONS_ROW).toContain('sm:overflow-visible')
    expect(DATA_TABLE_FILTERS_ROW).toContain('touch-pan-x')
  })

  it('tabs scrolláveis e labels compactos', () => {
    expect(SCROLLABLE_TABS_UI.list).toContain('flex-nowrap')
    expect(SCROLLABLE_TABS_UI.trigger).toContain('shrink-0')
    expect(COMPACT_BUTTON_LABEL_UI.label).toBe('hidden sm:inline')
  })
})
