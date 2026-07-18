import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CcmeiRegistrationStatusPanel from '../../app/components/clients/ClientCcmeiRegistrationStatusPanel.vue'

const fetchHistory = vi.fn()
const requestConsult = vi.fn()
const toastAdd = vi.fn()

vi.mock('../../app/composables/useCcmeiRegistrationStatusMonitoring', () => ({
  useCcmeiRegistrationStatusMonitoring: () => ({ fetchHistory, requestConsult })
}))

vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('formatDateTime', (value?: string | null) => value || '—')
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({ setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()]) }),
  UAlert: defineComponent({ setup: (_props, { slots }) => () => h('div', { role: 'alert' }, [slots.description?.(), slots.actions?.()]) }),
  UButton: defineComponent({
    props: { label: { type: String, default: '' }, disabled: Boolean, loading: Boolean },
    setup: (props, { attrs, slots }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, slots.default?.() || props.label)
  }),
  UBadge: defineComponent({ props: { label: { type: String, default: '' } }, setup: props => () => h('span', props.label) }),
  UEmpty: defineComponent({ props: { title: { type: String, default: '' } }, setup: props => () => h('div', props.title) }),
  USkeleton: true
}

describe('painel de situação cadastral CCMEI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchHistory.mockResolvedValue({ client_id: 7, current: null, history: [] })
    requestConsult.mockResolvedValue({ data: { id: 1 } })
  })

  it('mostra resumo sanitizado e enfileira a consulta sem identificadores fiscais', async () => {
    fetchHistory
      .mockResolvedValueOnce({
        client_id: 7,
        current: { status: 'ATIVA', enquadrado_mei: true, situation: 'UP_TO_DATE', count: 1, observed_at: '2026-07-18T12:00:00Z', source_provenance: 'SIMULATED' },
        history: []
      })
      .mockResolvedValueOnce({ client_id: 7, current: null, history: [] })
    const wrapper = await mountSuspended(CcmeiRegistrationStatusPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Enquadrado no MEI'))
    expect(wrapper.text()).toContain('ATIVA')
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|qrcode/i)
    await wrapper.get('button').trigger('click')
    await vi.waitFor(() => expect(requestConsult).toHaveBeenCalledWith(7))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Consulta cadastral CCMEI enfileirada' }))
  })

  it('mostra estado vazio sem dados fiscais', async () => {
    const wrapper = await mountSuspended(CcmeiRegistrationStatusPanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Sem consulta cadastral registrada'))
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|qrcode/i)
  })

  it('mostra erro e permite nova tentativa', async () => {
    fetchHistory.mockRejectedValueOnce(new Error('network'))
    fetchHistory.mockResolvedValueOnce({ client_id: 7, current: null, history: [] })
    const wrapper = await mountSuspended(CcmeiRegistrationStatusPanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Falha de rede'))
    await wrapper.get('button').trigger('click')
    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(2))
  })
})
