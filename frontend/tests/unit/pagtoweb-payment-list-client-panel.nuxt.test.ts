import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PaymentListPanel from '../../app/components/clients/ClientPagtowebPaymentListPanel.vue'

const fetchHistory = vi.fn()
const requestConsult = vi.fn()
const toastAdd = vi.fn()

vi.mock('../../app/composables/usePagtowebPaymentListMonitoring', () => ({
  usePagtowebPaymentListMonitoring: () => ({ fetchHistory, requestConsult })
}))
vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('formatDateTime', (value?: string | null) => value || '—')
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({ setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()]) }),
  UAlert: defineComponent({ props: { title: String }, setup: (props, { slots }) => () => h('div', [props.title, slots.description?.(), slots.actions?.()]) }),
  UFormField: defineComponent({ setup: (_props, { slots }) => () => h('label', slots.default?.()) }),
  UInput: defineComponent({ props: { modelValue: { type: String, default: '' } }, emits: ['update:modelValue'], setup: (props, { emit, attrs }) => () => h('input', { ...attrs, value: props.modelValue, onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value) }) }),
  UButton: defineComponent({ props: { label: { type: String, default: '' }, disabled: Boolean, loading: Boolean }, setup: (props, { attrs }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, props.label) }),
  UEmpty: defineComponent({ props: { title: { type: String, default: '' } }, setup: props => () => h('div', props.title) }),
  UTable: defineComponent({ props: { data: { type: Array, default: () => [] } }, setup: props => () => h('div', props.data.map((item: unknown) => (item as { document_masked?: string }).document_masked)) }),
  USkeleton: true
}

describe('painel de documentos PAGTOWEB', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchHistory.mockResolvedValue({ client_id: 7, current: null, items: [], meta: { page: 1, per_page: 50, total: 0 } })
    requestConsult.mockResolvedValue({ data: { id: 1 } })
  })

  it('exibe período e documento mascarado sem vazar o identificador original', async () => {
    fetchHistory.mockResolvedValueOnce({ client_id: 7, current: { filter_summary: { intervalo_data_arrecadacao: { dataInicial: '2026-01-01', dataFinal: '2026-01-31' } }, returned_count: 1, observed_at: '2026-07-18T12:00:00Z', source_provenance: 'SIMULATED' }, items: [{ document_masked: '•••••••••••••4567', document_type: 'DARF' }], meta: { page: 1, per_page: 50, total: 1 } })
    const wrapper = await mountSuspended(PaymentListPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })
    await vi.waitFor(() => expect(wrapper.text()).toContain('2026-01-01 a 2026-01-31'))
    expect(wrapper.text()).toContain('•••••••••••••4567')
    expect(wrapper.text()).toContain('SIMULATED')
    expect(wrapper.text()).not.toContain('12345678901234567')
  })

  it('envia somente o período e filtros oficiais confirmados', async () => {
    const wrapper = await mountSuspended(PaymentListPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })
    await vi.waitFor(() => expect(wrapper.text()).toContain('Nenhum pagamento consultado'))
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('2026-01-01')
    await inputs[1].setValue('2026-01-31')
    await wrapper.findAll('button')[1].trigger('click')
    await vi.waitFor(() => expect(requestConsult).toHaveBeenCalledWith(7, { intervalo_data_arrecadacao: { data_inicial: '2026-01-01', data_final: '2026-01-31' } }))
  })
})
