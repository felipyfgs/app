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
  advanced: [
    { key: 'competence', kind: 'month', label: 'Competência' },
    {
      key: 'status',
      kind: 'select',
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
      type: { type: String, default: 'button' }
    },
    setup(props, { attrs, slots }) {
      return () => h('button', { ...attrs, type: props.type }, slots.default?.() || props.label)
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
  UCollapsible: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup(props, { emit, slots }) {
      return () => h('div', [
        h('div', { onClick: () => emit('update:open', !props.open) }, slots.default?.({ open: props.open })),
        props.open ? h('div', slots.content?.()) : null
      ])
    }
  }),
  FiscalClientPicker: defineComponent({
    setup: () => () => h('div')
  })
}

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
  it('abre com um clique e fecha com outro pelo gatilho nativo', async () => {
    const { Host } = controlledToolbar()
    const wrapper = await mountToolbar(Host)
    const trigger = wrapper.get('[data-testid="advanced-filters-toggle"]')

    expect(trigger.attributes('aria-expanded')).toBe('false')
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('true')
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('false')
  })

  it('não consulta filtros avançados antes de Aplicar e preserva busca/situação rápidas abertas', async () => {
    const { Host, quick, apply } = controlledToolbar()
    const wrapper = await mountToolbar(Host)
    vi.useFakeTimers()
    await wrapper.get('[data-testid="advanced-filters-toggle"]').trigger('click')

    await wrapper.get('[data-testid="fiscal-filter-q"]').setValue('ACME')
    wrapper.findComponent(uiStubs.USelect).vm.$emit('update:modelValue', 'PENDING')
    await wrapper.vm.$nextTick()
    await wrapper.get('[data-testid="fiscal-filter-competence"]').setValue('2026-07')
    expect(apply).not.toHaveBeenCalled()

    await wrapper.get('[data-testid="fiscal-advanced-filters"]').trigger('submit')
    expect(apply).toHaveBeenCalledTimes(1)
    expect(apply.mock.calls[0]?.[0]).toMatchObject({
      q: 'ACME',
      situation: 'PENDING',
      competence: '2026-07'
    })
    await vi.runAllTimersAsync()
    expect(quick).toHaveBeenCalledTimes(1)
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

  it('inclui filtro específico no contador e reset emite uma única transação', async () => {
    const { Host, reset, quick } = controlledToolbar({ status: 'ACTIVE' })
    const wrapper = await mountToolbar(Host)
    vi.useFakeTimers()
    expect(wrapper.text()).toContain('Filtros (1)')
    await wrapper.get('[data-testid="advanced-filters-toggle"]').trigger('click')
    await wrapper.get('[data-testid="fiscal-filter-q"]').setValue('pendente')
    await wrapper.get('[data-testid="fiscal-filters-reset"]').trigger('click')
    await vi.runAllTimersAsync()

    expect(reset).toHaveBeenCalledTimes(1)
    expect(reset.mock.calls[0]?.[0]).toEqual(resetMonitoringFilters())
    expect(quick).not.toHaveBeenCalled()
  })
})
