import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PnrPanel from '../../app/components/clients/ClientPnrRenunciationsPanel.vue'

const forClient = vi.fn()
const history = vi.fn()
const status = vi.fn()
const receipt = vi.fn()
const toastAdd = vi.fn()

vi.stubGlobal('useApi', () => ({ fiscal: { pnrRenunciations: { forClient, history, status, receipt } } }))
vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({ setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()]) }),
  UAlert: defineComponent({ props: { title: String, description: String }, setup: props => () => h('div', [props.title, props.description]) }),
  UFormField: defineComponent({ setup: (_props, { slots }) => () => h('label', slots.default?.()) }),
  UInput: defineComponent({ props: { modelValue: { type: [String, Number], default: '' } }, emits: ['update:modelValue'], setup: (props, { emit, attrs }) => () => h('input', { ...attrs, value: props.modelValue, onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value) }) }),
  UButton: defineComponent({ props: { label: String, disabled: Boolean, loading: Boolean }, setup: (props, { attrs }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, props.label) }),
  UBadge: defineComponent({ setup: (_props, { slots }) => () => h('span', slots.default?.()) }),
  UEmpty: defineComponent({ props: { title: String, description: String }, setup: props => () => h('div', [props.title, props.description]) }),
  USkeleton: true
}

describe('painel PNR de renúncias', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    forClient.mockResolvedValue({ data: { client_id: 7, renunciations: [] } })
    history.mockResolvedValue({ data: { success: true, count: 0 } })
    status.mockResolvedValue({ data: { success: true, renunciation_id: null } })
    receipt.mockResolvedValue({ data: { success: true, renunciation_id: 12 } })
  })

  it('mostra vazio e não consulta externamente durante o carregamento', async () => {
    const wrapper = await mountSuspended(PnrPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })
    await vi.waitFor(() => expect(forClient).toHaveBeenCalledWith(7))
    expect(wrapper.text()).toContain('Nenhuma renúncia encontrada')
    expect(history).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toMatch(/cnpj|cpf|token|senha/i)
  })

  it('exige ação explícita para histórico e encaminha os identificadores apenas às ações corretas', async () => {
    const wrapper = await mountSuspended(PnrPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })
    await vi.waitFor(() => expect(forClient).toHaveBeenCalled())
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('SOL-9')
    await inputs[1].setValue('12')
    const buttons = wrapper.findAll('button')
    await buttons[1].trigger('click')
    await vi.waitFor(() => expect(history).toHaveBeenCalledWith(7, {}))
    await buttons[2].trigger('click')
    await vi.waitFor(() => expect(status).toHaveBeenCalledWith(7, 'SOL-9'))
    await buttons[3].trigger('click')
    await vi.waitFor(() => expect(receipt).toHaveBeenCalledWith(7, 12))
  })
})
