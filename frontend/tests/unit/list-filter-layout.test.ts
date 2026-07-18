import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
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

const readApp = (path: string) => readFileSync(resolve(__dirname, '../../app', path), 'utf8')

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
    expect(LIST_FILTER_ACTIONS_ROW).toContain('sm:ml-auto')
    expect(LIST_FILTER_ACTIONS_ROW.split(/\s+/)).not.toContain('ml-auto')
    expect(LIST_FILTER_ACTIONS_ROW).toContain('sm:justify-end')
    expect(LIST_FILTER_ACTIONS_ROW).toContain('sm:overflow-visible')
    expect(DATA_TABLE_FILTERS_ROW).toContain('touch-pan-x')
  })

  it('tabs scrolláveis e labels compactos', () => {
    expect(SCROLLABLE_TABS_UI.list).toContain('flex-nowrap')
    expect(SCROLLABLE_TABS_UI.trigger).toContain('shrink-0')
    expect(COMPACT_BUTTON_LABEL_UI.label).toBe('hidden sm:inline')
  })

  it('listas filtráveis usam o shell ou os tokens canônicos', () => {
    const sharedShellSurfaces = [
      'components/monitoring/ModuleToolbar.vue',
      'pages/clients/index.vue',
      'pages/work/templates/index.vue'
    ]
    for (const path of sharedShellSurfaces) {
      expect(readApp(path), path).toContain('ShellListFilterToolbar')
    }

    const tokenSurfaces = [
      'components/docs/ByClient.vue',
      'pages/admin/offices/index.vue',
      'pages/admin/serpro/catalog.vue',
      'pages/admin/serpro/contracts.vue',
      'pages/closing.vue',
      'pages/docs/imports/[id].vue',
      'pages/health.vue',
      'pages/work/processes/index.vue'
    ]
    for (const path of tokenSurfaces) {
      const source = readApp(path)
      expect(source, path).toContain('LIST_FILTER_TOOLBAR_STACK')
      expect(source, path).toContain('LIST_FILTER_ACTIONS_ROW')
    }
  })
})
