import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import SectionNavigation from '../../app/components/navigation/SectionNavigation.vue'
import type { NavLayerItem } from '../../app/utils/navigation-hierarchy'

const push = vi.fn()

vi.stubGlobal('useRoute', () => ({
  path: '/monitoring/simples-mei',
  fullPath: '/monitoring/simples-mei'
}))
vi.stubGlobal('useRouter', () => ({ push }))

const fixtures: NavLayerItem[] = [
  {
    id: 'overview',
    label: 'Visão geral',
    children: [
      { id: 'dashboard', label: 'Dashboard fiscal', to: '/monitoring', exact: true }
    ]
  },
  {
    id: 'obligations',
    label: 'Obrigações',
    children: [
      { id: 'simples', label: 'Simples / MEI', to: '/monitoring/simples-mei' },
      { id: 'dctf', label: 'DCTFWeb / MIT', to: '/monitoring/dctfweb' },
      { id: 'decl', label: 'Declarações', to: '/monitoring/declarations' }
    ]
  },
  {
    id: 'unit',
    label: 'Grupo unitário',
    children: [
      { id: 'only', label: 'Único', to: '/monitoring/only' }
    ]
  }
]

const longLabelFixtures: NavLayerItem[] = [
  { id: 'a', label: 'Rótulo extremamente longo para truncamento acessível da seção', to: '/a' },
  { id: 'b', label: 'Outro destino com nome extenso de navegação fiscal', to: '/b' }
]

const uiStubs = {
  UNavigationMenu: defineComponent({
    props: {
      items: { type: Array, default: () => [] },
      ariaLabel: { type: String, default: '' }
    },
    setup(props, { attrs }) {
      const flat = ((props.items as {
        label?: string
        active?: boolean
        to?: string
      }[][])[0] || [])
      return () => h('nav', {
        ...attrs,
        'aria-label': props.ariaLabel || attrs['aria-label'],
        'data-stub': 'nav-menu'
      }, flat.map(item => h('a', {
        'data-active': item.active ? 'true' : 'false',
        'data-to': item.to ?? '',
        'data-label': item.label ?? ''
      }, item.label)))
    }
  }),
  USelectMenu: defineComponent({
    props: {
      modelValue: { type: [String, Number], default: '' },
      items: { type: Array, default: () => [] },
      ariaLabel: { type: String, default: '' },
      ui: { type: Object, default: () => ({}) }
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit, slots }) {
      return () => h('button', {
        ...attrs,
        'type': 'button',
        'aria-label': props.ariaLabel || attrs['aria-label'],
        'data-stub': 'select-menu',
        'data-min-h': String((props.ui as { base?: string })?.base || ''),
        'onClick': () => {
          const second = (props.items as { id: string }[])[1]
          if (second) emit('update:modelValue', second.id)
        }
      }, [
        slots.default?.(),
        h('ul', (props.items as { id: string, label: string }[]).map(item =>
          h('li', { 'data-option': item.id }, item.label)
        ))
      ])
    }
  })
}

describe('SectionNavigation', () => {
  it('monta tabs desktop, seletor mobile e item ativo', async () => {
    const wrapper = await mountSuspended(SectionNavigation, {
      props: { items: fixtures, path: '/monitoring/simples-mei' },
      global: { stubs: uiStubs }
    })

    expect(wrapper.find('[data-testid="section-nav-tabs-desktop"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="section-nav-tabs-mobile"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="section-nav-subtabs-desktop"]').exists()).toBe(true)

    const active = wrapper.findAll('[data-stub="nav-menu"] a').filter(a => a.attributes('data-active') === 'true')
    expect(active.some(a => a.text().includes('Obrigações') || a.text().includes('Simples'))).toBe(true)

    const select = wrapper.find('[data-testid="section-nav-tabs-mobile"] [data-stub="select-menu"]')
    expect(select.attributes('data-min-h')).toContain('min-h-11')
    expect(select.attributes('aria-label')).toBeTruthy()
  })

  it('não renderiza subtabs para grupo unitário', async () => {
    const wrapper = await mountSuspended(SectionNavigation, {
      props: { items: fixtures, path: '/monitoring/only' },
      global: { stubs: uiStubs }
    })
    expect(wrapper.find('[data-testid="section-navigation-subtabs"]').exists()).toBe(false)
  })

  it('oferece fallback de seletor com labels longos', async () => {
    const wrapper = await mountSuspended(SectionNavigation, {
      props: {
        items: longLabelFixtures,
        path: '/a',
        ariaLabel: 'Navegação de teste'
      },
      global: { stubs: uiStubs }
    })
    const mobile = wrapper.find('[data-testid="section-nav-tabs-mobile"]')
    expect(mobile.text()).toContain('Rótulo extremamente longo')
    expect(mobile.find('[data-option="b"]').exists()).toBe(true)
    expect(mobile.find('[data-stub="select-menu"]').attributes('data-min-h')).toContain('min-h-11')
    expect(mobile.find('[data-stub="select-menu"]').attributes('aria-label')).toBe('Navegação de teste')
  })

  it('emite navigate quando router está desabilitado', async () => {
    push.mockClear()
    const wrapper = await mountSuspended(SectionNavigation, {
      props: {
        items: longLabelFixtures,
        path: '/a',
        navigateWithRouter: false
      },
      global: { stubs: uiStubs }
    })

    await wrapper.find('[data-testid="section-nav-tabs-mobile"] [data-stub="select-menu"]').trigger('click')

    expect(push).not.toHaveBeenCalled()
    expect(wrapper.emitted('navigate')?.[0]?.[0]).toMatchObject({ id: 'b', to: '/b' })
  })

  it('em /monitoring/simples-mei ativa Obrigações + Simples/MEI', async () => {
    const wrapper = await mountSuspended(SectionNavigation, {
      props: { items: fixtures, path: '/monitoring/simples-mei' },
      global: { stubs: uiStubs }
    })

    const tabs = wrapper.find('[data-testid="section-nav-tabs-desktop"]')
    const subtabs = wrapper.find('[data-testid="section-nav-subtabs-desktop"]')
    expect(tabs.find('[data-label="Obrigações"]').attributes('data-active')).toBe('true')
    expect(subtabs.find('[data-label="Simples / MEI"]').attributes('data-active')).toBe('true')
  })

  it('mantém links semânticos e aponta grupos sempre ao primeiro filho', async () => {
    const wrapper = await mountSuspended(SectionNavigation, {
      props: { items: fixtures, path: '/monitoring/simples-mei' },
      global: { stubs: uiStubs }
    })

    const tabs = wrapper.find('[data-testid="section-nav-tabs-desktop"]')
    // O item ativo continua sendo link para preservar teclado e ações do navegador.
    expect(tabs.find('[data-label="Obrigações"]').attributes('data-to')).toBe('/monitoring/simples-mei')
    // Outro grupo → sempre 1º filho do grupo
    expect(tabs.find('[data-label="Visão geral"]').attributes('data-to')).toBe('/monitoring')
  })

  it('distingue tabs que compartilham a rota por query string', async () => {
    const queryTabs: NavLayerItem[] = [
      { id: 'summary', label: 'Resumo', to: '/conta?section=resumo' },
      { id: 'security', label: 'Segurança', to: '/conta?section=seguranca' }
    ]
    const wrapper = await mountSuspended(SectionNavigation, {
      props: { items: queryTabs, path: '/conta?section=seguranca' },
      global: { stubs: uiStubs }
    })

    const tabs = wrapper.find('[data-testid="section-nav-tabs-desktop"]')
    expect(tabs.find('[data-label="Resumo"]').attributes('data-active')).toBe('false')
    expect(tabs.find('[data-label="Segurança"]').attributes('data-active')).toBe('true')
    expect(tabs.find('[data-label="Segurança"]').attributes('data-to')).toBe('/conta?section=seguranca')
  })
})
