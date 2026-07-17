import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h, ref } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import ModuleToolbar from '../../app/components/monitoring/ModuleToolbar.vue'
import type {
  MonitoringFilterConfig,
  MonitoringFilterValue
} from '../../app/types/fiscal-modules'
import { resetMonitoringFilters } from '../../app/utils/monitoring-filters'

const config: MonitoringFilterConfig = {
  fields: [
    { key: 'situation', kind: 'option', label: 'Situação' },
    { key: 'competence', kind: 'month', label: 'Competência' },
    {
      key: 'status',
      kind: 'option',
      label: 'Status',
      items: [{ label: 'Todos', value: 'all' }, { label: 'Ativo', value: 'ACTIVE' }]
    }
  ]
}

const uiStubs = {
  UInput: defineComponent({
    inheritAttrs: false,
    props: { modelValue: { type: [String, Number], default: '' } },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
      return () => h('input', {
        ...attrs,
        value: props.modelValue,
        onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value)
      })
    }
  }),
  UButton: defineComponent({
    inheritAttrs: false,
    props: {
      label: { type: String, default: '' },
      type: { type: String, default: 'button' },
      disabled: { type: Boolean, default: false }
    },
    setup(props, { attrs, slots }) {
      return () => h('button', {
        ...attrs,
        type: props.type,
        disabled: props.disabled || undefined
      }, slots.default?.() || props.label)
    }
  }),
  USelect: defineComponent({
    inheritAttrs: false,
    props: { modelValue: { type: String, default: 'all' } },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
      return () => h('select', {
        ...attrs,
        value: props.modelValue,
        onChange: (event: Event) => emit('update:modelValue', (event.target as HTMLSelectElement).value)
      })
    }
  }),
  UTooltip: defineComponent({
    props: { text: { type: String, default: '' } },
    setup: (_props, { slots }) => () => slots.default?.()
  }),
  UFormField: defineComponent({
    setup: (_props, { slots }) => () => h('div', slots.default?.())
  }),
  UPopover: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup(props, { emit, slots }) {
      return () => h('div', { 'data-stub': 'popover' }, [
        h('div', {
          onClick: () => emit('update:open', !props.open)
        }, slots.default?.()),
        props.open ? h('div', { 'data-testid': 'popover-content' }, slots.content?.()) : null
      ])
    }
  }),
  UDrawer: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup(props, { slots }) {
      return () => h('div', { 'data-stub': 'drawer' }, [
        props.open ? h('div', { 'data-testid': 'drawer-content' }, slots.content?.()) : null
      ])
    }
  }),
  UCommandPalette: defineComponent({
    props: { groups: { type: Array, default: () => [] } },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
      const groups = props.groups as Array<{ items?: Array<{ key?: string, label?: string, onSelect?: () => void }> }>
      return () => h('div', { ...attrs, 'data-testid': 'data-table-filter-selector' },
        (groups[0]?.items || []).map(item =>
          h('button', {
            'type': 'button',
            'data-key': item.key,
            'onClick': () => {
              item.onSelect?.()
              emit('update:modelValue', item)
            }
          }, item.label)
        )
      )
    }
  }),
  UFieldGroup: defineComponent({
    setup: (_props, { slots }) => () => h('div', { 'data-stub': 'field-group' }, slots.default?.())
  }),
  FiscalClientPicker: defineComponent({
    setup: () => () => h('div', { 'data-testid': 'fiscal-filter-client' })
  })
}

vi.mock('@vueuse/core', async () => {
  const actual = await vi.importActual<typeof import('@vueuse/core')>('@vueuse/core')
  return {
    ...actual,
    useBreakpoints: () => ({
      smaller: () => ref(false)
    })
  }
})

function mountToolbar(Host: ReturnType<typeof defineComponent>) {
  return mountSuspended(Host, { global: { stubs: uiStubs } })
}

function controlledToolbar(initial?: Partial<MonitoringFilterValue>) {
  const filters = ref<MonitoringFilterValue>({ ...resetMonitoringFilters(), ...initial })
  const quick = vi.fn((next: MonitoringFilterValue) => {
    filters.value = next
  })
  const apply = vi.fn((next: MonitoringFilterValue) => {
    filters.value = next
  })
  const reset = vi.fn((next: MonitoringFilterValue) => {
    filters.value = next
  })
  const Host = defineComponent({
    setup: () => () => h(ModuleToolbar, {
      filters: filters.value,
      filterConfig: config,
      showTotal: false,
      onQuickFilterChange: quick,
      onApplyFilters: apply,
      onResetFilters: reset
    })
  })
  return { Host, filters, quick, apply, reset }
}

afterEach(() => {
  vi.useRealTimers()
})

describe('MonitoringModuleToolbar', () => {
  it('exibe chips e botão Adicionar filtro', async () => {
    const { Host } = controlledToolbar({ status: 'ACTIVE' })
    const wrapper = await mountToolbar(Host)

    expect(wrapper.get('[data-testid="fiscal-structured-filters"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="data-table-filter-add"]').text()).toContain('Adicionar filtro')
    expect(wrapper.get('[data-testid="data-table-filter-chip"]').text()).toContain('Status')
  })

  it('não consulta filtros estruturados antes de confirmar', async () => {
    const { Host, apply } = controlledToolbar()
    const wrapper = await mountToolbar(Host)

    await wrapper.get('[data-testid="data-table-filter-add"]').trigger('click')
    const competenceBtn = wrapper.find('[data-key="competence"]')
    if (competenceBtn.exists()) {
      await competenceBtn.trigger('click')
      await wrapper.vm.$nextTick()
      expect(apply).not.toHaveBeenCalled()
      const month = wrapper.find('[data-testid="data-table-filter-month"]')
      if (month.exists()) {
        await month.setValue('2026-07')
        expect(apply).not.toHaveBeenCalled()
        await wrapper.get('[data-testid="data-table-filter-confirm"]').trigger('click')
        expect(apply).toHaveBeenCalledTimes(1)
        expect(apply.mock.calls[0]?.[0]).toMatchObject({ competence: '2026-07' })
      }
    }
  })

  it('debounceia a busca em 320 ms e Enter aplica imediatamente', async () => {
    const first = controlledToolbar()
    const wrapper = await mountToolbar(first.Host)
    vi.useFakeTimers()
    const input = wrapper.get('[data-testid="fiscal-filter-q"]')
    await input.setValue('Empresa')
    await vi.advanceTimersByTimeAsync(319)
    expect(first.quick).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    expect(first.quick).toHaveBeenCalledTimes(1)

    await input.setValue('Empresa Nova')
    await input.trigger('keyup.enter')
    expect(first.quick).toHaveBeenCalledTimes(2)
  })

  it('Limpar tudo emite uma única transação reset', async () => {
    const { Host, reset, quick } = controlledToolbar({ status: 'ACTIVE', q: 'x' })
    const wrapper = await mountToolbar(Host)
    vi.useFakeTimers()
    await wrapper.get('[data-testid="data-table-filter-clear"]').trigger('click')
    await vi.runAllTimersAsync()

    expect(reset).toHaveBeenCalledTimes(1)
    expect(reset.mock.calls[0]?.[0]).toEqual(resetMonitoringFilters())
    expect(quick).not.toHaveBeenCalled()
  })
})
