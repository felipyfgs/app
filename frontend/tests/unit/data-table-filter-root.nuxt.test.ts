import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h, ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import DataTableFilterRoot from '../../app/components/data-table-filter/Root.vue'
import type { DataTableFilterDefinition, DataTableFilterModel } from '../../app/types/data-table-filter'

const definitions: DataTableFilterDefinition[] = [
  {
    key: 'status',
    kind: 'option',
    label: 'Status',
    items: [
      { label: 'Ativo', value: 'ACTIVE' },
      { label: 'Inativo', value: 'INACTIVE' }
    ],
    emptyValue: 'all'
  },
  { key: 'competence', kind: 'month', label: 'Competência' }
]

const uiStubs = {
  UButton: defineComponent({
    inheritAttrs: false,
    props: {
      label: { type: String, default: '' },
      disabled: { type: Boolean, default: false }
    },
    setup(props, { attrs, slots }) {
      return () => h('button', {
        ...attrs,
        type: 'button',
        disabled: props.disabled || undefined
      }, slots.default?.() || props.label)
    }
  }),
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
  USelect: defineComponent({
    inheritAttrs: false,
    props: { modelValue: { type: String, default: '' } },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
      return () => h('select', {
        ...attrs,
        value: props.modelValue,
        onChange: (event: Event) => emit('update:modelValue', (event.target as HTMLSelectElement).value)
      })
    }
  }),
  UFormField: defineComponent({
    setup: (_props, { slots }) => () => h('div', slots.default?.())
  }),
  UPopover: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup(props, { emit, slots }) {
      return () => h('div', { 'data-stub': 'popover' }, [
        h('div', { onClick: () => emit('update:open', !props.open) }, slots.default?.()),
        props.open ? h('div', slots.content?.()) : null
      ])
    }
  }),
  UDrawer: defineComponent({
    props: { open: Boolean },
    setup(props, { slots }) {
      return () => h('div', { 'data-stub': 'drawer' }, [
        props.open ? h('div', slots.content?.()) : null
      ])
    }
  }),
  UCommandPalette: defineComponent({
    props: { groups: { type: Array, default: () => [] } },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
      const groups = props.groups as Array<{ items?: Array<{ key?: string, label?: string, onSelect?: () => void }> }>
      return () => h('div', { ...attrs },
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
    setup: (_props, { slots }) => () => h('div', slots.default?.())
  }),
  FiscalClientPicker: defineComponent({
    setup: () => () => h('div')
  })
}

vi.mock('@vueuse/core', async () => {
  const actual = await vi.importActual<typeof import('@vueuse/core')>('@vueuse/core')
  const isMobile = ref(false)
  return {
    ...actual,
    useBreakpoints: () => ({
      smaller: () => isMobile
    }),
    __isMobile: isMobile
  }
})

function mountRoot(initial: DataTableFilterModel[] = []) {
  const models = ref<DataTableFilterModel[]>([...initial])
  const onUpdate = vi.fn((next: DataTableFilterModel[]) => {
    models.value = next
  })
  const onClear = vi.fn()
  const Host = defineComponent({
    setup: () => () => h(DataTableFilterRoot, {
      definitions,
      'modelValue': models.value,
      'onUpdate:modelValue': onUpdate,
      onClear
    })
  })
  return { Host, models, onUpdate, onClear }
}

describe('DataTableFilterRoot', () => {
  it('confirma opção com uma única emissão e remove com outra', async () => {
    const { Host, onUpdate } = mountRoot()
    const wrapper = await mountSuspended(Host, { global: { stubs: uiStubs } })

    await wrapper.get('[data-testid="data-table-filter-add"]').trigger('click')
    await wrapper.get('[data-key="status"]').trigger('click')
    await wrapper.vm.$nextTick()

    const select = wrapper.findComponent(uiStubs.USelect)
    expect(select.exists()).toBe(true)
    select.vm.$emit('update:modelValue', 'ACTIVE')
    await wrapper.vm.$nextTick()
    expect(onUpdate).not.toHaveBeenCalled()

    const confirm = wrapper.get('[data-testid="data-table-filter-confirm"]')
    expect(confirm.attributes('disabled')).toBeUndefined()
    await confirm.trigger('click')
    await wrapper.vm.$nextTick()
    expect(onUpdate).toHaveBeenCalledTimes(1)
    expect(onUpdate.mock.calls[0]?.[0][0]).toMatchObject({
      key: 'status',
      value: 'ACTIVE'
    })
  })

  it('cancela rascunho sem emitir', async () => {
    const { Host, onUpdate } = mountRoot()
    const wrapper = await mountSuspended(Host, { global: { stubs: uiStubs } })

    await wrapper.get('[data-testid="data-table-filter-add"]').trigger('click')
    await wrapper.get('[data-key="competence"]').trigger('click')
    await wrapper.vm.$nextTick()
    await wrapper.get('[data-testid="data-table-filter-month"]').setValue('2026-07')
    await wrapper.get('[data-testid="data-table-filter-cancel"]').trigger('click')
    expect(onUpdate).not.toHaveBeenCalled()
  })

  it('desabilita confirmar com competência inválida', async () => {
    const { Host, onUpdate } = mountRoot()
    const wrapper = await mountSuspended(Host, { global: { stubs: uiStubs } })

    await wrapper.get('[data-testid="data-table-filter-add"]').trigger('click')
    await wrapper.get('[data-key="competence"]').trigger('click')
    await wrapper.vm.$nextTick()
    await wrapper.get('[data-testid="data-table-filter-month"]').setValue('2026-99')
    const confirm = wrapper.get('[data-testid="data-table-filter-confirm"]')
    expect(confirm.attributes('disabled')).toBeDefined()
    await confirm.trigger('click')
    expect(onUpdate).not.toHaveBeenCalled()
  })

  it('chip possui nomes acessíveis de editar e remover', async () => {
    const { Host } = mountRoot([
      { key: 'status', operator: 'eq', value: 'ACTIVE', label: 'Ativo' }
    ])
    const wrapper = await mountSuspended(Host, { global: { stubs: uiStubs } })
    expect(wrapper.get('[data-testid="data-table-filter-chip-edit"]').attributes('aria-label'))
      .toMatch(/Editar filtro Status/i)
    expect(wrapper.get('[data-testid="data-table-filter-chip-remove"]').attributes('aria-label'))
      .toMatch(/Remover filtro Status/i)
  })
})
