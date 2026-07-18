import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PaymentCountPanel from '../../app/components/clients/ClientPagtowebPaymentCountPanel.vue'

const fetchHistory = vi.fn()
const requestConsult = vi.fn()
const toastAdd = vi.fn()

vi.mock('../../app/composables/usePagtowebPaymentCountMonitoring', () => ({
  usePagtowebPaymentCountMonitoring: () => ({ fetchHistory, requestConsult })
}))
vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('formatDateTime', (value?: string | null) => value || '—')
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({ setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()]) }),
  UAlert: defineComponent({ props: { title: String, description: String }, setup: props => () => h('div', [props.title, props.description]) }),
  UFormField: defineComponent({ setup: (_props, { slots }) => () => h('label', slots.default?.()) }),
  UInput: defineComponent({ props: { modelValue: { type: String, default: '' } }, emits: ['update:modelValue'], setup: (props, { emit, attrs }) => () => h('input', { ...attrs, value: props.modelValue, onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value) }) }),
  UButton: defineComponent({ props: { label: { type: String, default: '' }, disabled: Boolean, loading: Boolean }, setup: (props, { attrs }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, props.label) }),
  UEmpty: defineComponent({ props: { title: { type: String, default: '' } }, setup: props => () => h('div', props.title) }),
  USkeleton: true
}

describe('painel de contagem PAGTOWEB', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchHistory.mockResolvedValue({ client_id: 7, current: null, history: [] })
    requestConsult.mockResolvedValue({ data: { id: 1 } })
  })

  it('exibe apenas o agregado e enfileira filtro oficial confirmado', async () => {
    fetchHistory.mockResolvedValueOnce({ client_id: 7, current: { payment_count: 3, filter_summary: {}, observed_at: '2026-07-18T12:00:00Z', source_provenance: 'SIMULATED' }, history: [] })
    const wrapper = await mountSuspended(PaymentCountPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })
    await vi.waitFor(() => expect(wrapper.text()).toContain('3'))
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|token|senha|documento [0-9]/i)
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('2026-01-01')
    await inputs[1].setValue('2026-01-31')
    await wrapper.findAll('button')[1].trigger('click')
    await vi.waitFor(() => expect(requestConsult).toHaveBeenCalledWith(7, { intervalo_data_arrecadacao: { data_inicial: '2026-01-01', data_final: '2026-01-31' } }))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Contagem de pagamentos enfileirada' }))
  })

  it('mantém a confirmação desabilitada sem filtro', async () => {
    const wrapper = await mountSuspended(PaymentCountPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })
    await vi.waitFor(() => expect(wrapper.text()).toContain('Sem contagem registrada'))
    expect((wrapper.findAll('button')[1].element as HTMLButtonElement).disabled).toBe(true)
  })
})
