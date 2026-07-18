import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SicalcRevenueSupportPanel from '../../app/components/clients/ClientSicalcRevenueSupportPanel.vue'

const fetchHistory = vi.fn()
const requestConsult = vi.fn()
const toastAdd = vi.fn()

vi.mock('../../app/composables/useSicalcRevenueSupportMonitoring', () => ({
  useSicalcRevenueSupportMonitoring: () => ({ fetchHistory, requestConsult })
}))

vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('formatDateTime', (value?: string | null) => value || '—')
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({ setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()]) }),
  UFormField: defineComponent({ setup: (_props, { slots }) => () => h('label', slots.default?.()) }),
  UInput: defineComponent({
    props: { modelValue: { type: String, default: '' } },
    emits: ['update:modelValue'],
    setup: (props, { emit, attrs }) => () => h('input', { ...attrs, value: props.modelValue, onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value) })
  }),
  UAlert: defineComponent({ setup: (_props, { slots }) => () => h('div', { role: 'alert' }, [slots.description?.(), slots.actions?.()]) }),
  UButton: defineComponent({
    props: { label: { type: String, default: '' }, disabled: Boolean, loading: Boolean },
    setup: (props, { attrs, slots }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, slots.default?.() || props.label)
  }),
  UBadge: defineComponent({ props: { label: { type: String, default: '' } }, setup: props => () => h('span', props.label) }),
  UEmpty: defineComponent({ props: { title: { type: String, default: '' } }, setup: props => () => h('div', props.title) }),
  USkeleton: true
}

describe('painel de apoio de receitas SICALC', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchHistory.mockResolvedValue({ client_id: 7, current: [], history: [] })
    requestConsult.mockResolvedValue({ data: { id: 1 } })
  })

  it('mostra apenas o metadado sanitizado e enfileira uma receita válida', async () => {
    fetchHistory
      .mockResolvedValueOnce({
        client_id: 7,
        current: [{
          revenue_code: '1082', description: 'IRRF - Trabalho assalariado', extension_count: 1,
          extensions: [{ obrigatorios: { dataPA: true }, opcionais: { observacao: true }, informacoes: { calculado: true } }],
          observed_at: '2026-07-18T12:00:00Z', source_provenance: 'SIMULATED'
        }],
        history: []
      })
      .mockResolvedValueOnce({ client_id: 7, current: [], history: [] })
    const wrapper = await mountSuspended(SicalcRevenueSupportPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })

    await vi.waitFor(() => expect(wrapper.text()).toContain('IRRF - Trabalho assalariado'))
    expect(wrapper.text()).toContain('dataPA')
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|token|senha/i)
    await wrapper.get('input').setValue('1082')
    await wrapper.findAll('button')[1].trigger('click')
    await vi.waitFor(() => expect(requestConsult).toHaveBeenCalledWith(7, '1082'))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Consulta de apoio SICALC enfileirada' }))
  })

  it('bloqueia consulta até receber código numérico e mostra estado vazio', async () => {
    const wrapper = await mountSuspended(SicalcRevenueSupportPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Sem apoio de receita registrado'))
    expect((wrapper.findAll('button')[1].element as HTMLButtonElement).disabled).toBe(true)
    await wrapper.get('input').setValue('abc')
    expect((wrapper.findAll('button')[1].element as HTMLButtonElement).disabled).toBe(true)
  })
})
