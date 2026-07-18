import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CcmeiPanel from '../../app/components/clients/ClientCcmeiPanel.vue'

const fetchHistory = vi.fn()
const requestConsult = vi.fn()
const toastAdd = vi.fn()

vi.mock('../../app/composables/useCcmeiMonitoring', () => ({
  useCcmeiMonitoring: () => ({ fetchHistory, requestConsult })
}))

vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('formatDateTime', (value?: string | null) => value || '—')
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({
    setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()])
  }),
  UAlert: defineComponent({
    props: { title: { type: String, default: '' }, description: { type: String, default: '' } },
    setup: (props, { slots }) => () => h('div', { role: 'alert' }, [props.title, props.description, slots.actions?.()])
  }),
  UButton: defineComponent({
    props: { label: { type: String, default: '' }, disabled: Boolean, loading: Boolean },
    setup: (props, { attrs, slots }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, slots.default?.() || props.label)
  }),
  UBadge: defineComponent({
    props: { label: { type: String, default: '' } },
    setup: props => () => h('span', props.label)
  }),
  UEmpty: defineComponent({
    props: { title: { type: String, default: '' }, description: { type: String, default: '' } },
    setup: props => () => h('div', [props.title, props.description])
  }),
  UIcon: true,
  USkeleton: true
}

describe('painel CCMEI do detalhe de cliente', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchHistory.mockResolvedValue({ client_id: 7, current: null, history: [] })
    requestConsult.mockResolvedValue({ data: { id: 1 } })
  })

  it('mostra estado vazio sem identificadores fiscais', async () => {
    const wrapper = await mountSuspended(CcmeiPanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(wrapper.text()).toContain('Sem consulta CCMEI registrada')
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|qrcode/i)
  })

  it('mostra histórico sanitizado de sucesso', async () => {
    fetchHistory.mockResolvedValueOnce({
      client_id: 7,
      current: {
        status: 'ATIVA',
        situation: 'UP_TO_DATE',
        last_valid_query_at: '2026-07-18T12:00:00Z',
        source_provenance: 'SIMULATED'
      },
      history: [{
        id: 10,
        status: 'ATIVA',
        situation: 'UP_TO_DATE',
        observed_at: '2026-07-18T12:00:00Z',
        source_provenance: 'SIMULATED'
      }]
    })

    const wrapper = await mountSuspended(CcmeiPanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Histórico de consultas'))
    expect(wrapper.text()).toContain('ATIVA')
    expect(wrapper.text()).toContain('Simulada')
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|qrcode/i)
  })

  it('mostra erro e permite nova tentativa', async () => {
    fetchHistory.mockRejectedValueOnce(new Error('network'))
    fetchHistory.mockResolvedValueOnce({ client_id: 7, current: null, history: [] })
    const wrapper = await mountSuspended(CcmeiPanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Histórico indisponível'))
    await wrapper.get('button').trigger('click')
    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(2))
  })

  it('enfileira consulta e recarrega o histórico local', async () => {
    fetchHistory
      .mockResolvedValueOnce({ client_id: 7, current: null, history: [] })
      .mockResolvedValueOnce({
        client_id: 7,
        current: { status: 'ATIVA', situation: 'UP_TO_DATE' },
        history: []
      })
    const wrapper = await mountSuspended(CcmeiPanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(1))
    await wrapper.get('button').trigger('click')
    await vi.waitFor(() => expect(requestConsult).toHaveBeenCalledWith(7))
    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(2))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Consulta CCMEI enfileirada' }))
  })
})
