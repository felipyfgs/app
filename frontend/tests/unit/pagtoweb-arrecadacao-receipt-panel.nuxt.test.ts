import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PagtowebArrecadacaoReceiptPanel from '../../app/components/clients/ClientPagtowebArrecadacaoReceiptPanel.vue'

const fetchHistory = vi.fn()
const requestReceipt = vi.fn()
const downloadPath = vi.fn()
const toastAdd = vi.fn()

vi.mock('../../app/composables/usePagtowebArrecadacaoReceipt', () => ({
  usePagtowebArrecadacaoReceipt: () => ({ fetchHistory, requestReceipt, downloadPath })
}))

vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('formatDateTime', (value?: string | null) => value || '—')
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({ setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()]) }),
  UModal: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup: (props, { slots }) => () => props.open ? h('section', { 'data-stub': 'modal' }, [slots.body?.(), slots.footer?.()]) : null
  }),
  UAlert: defineComponent({ props: { title: String }, setup: (props, { slots }) => () => h('div', { role: 'alert' }, [props.title, slots.actions?.()]) }),
  UButton: defineComponent({ props: { label: String, disabled: Boolean, loading: Boolean }, setup: (props, { attrs, slots }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, slots.default?.() || props.label) }),
  UFormField: defineComponent({ setup: (_props, { slots }) => () => h('label', slots.default?.()) }),
  UInput: defineComponent({
    props: { modelValue: String, disabled: Boolean },
    emits: ['update:modelValue'],
    setup: (props, { emit }) => () => h('input', { value: props.modelValue, disabled: props.disabled, onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value) })
  }),
  UEmpty: defineComponent({ props: { title: String, description: String }, setup: props => () => h('div', [props.title, props.description]) }),
  USkeleton: true
}

describe('painel de comprovante de arrecadação PAGTOWEB', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchHistory.mockResolvedValue({ client_id: 7, items: [] })
    requestReceipt.mockResolvedValue({ id: 9, success: true })
    downloadPath.mockReturnValue('/download/9')
  })

  it('carrega somente o histórico local e não consulta na montagem', async () => {
    const wrapper = await mountSuspended(PagtowebArrecadacaoReceiptPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(requestReceipt).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Nenhum comprovante disponível')
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|senha|token|base64/i)
  })

  it('mascara o número na confirmação e só solicita após confirmação explícita', async () => {
    const wrapper = await mountSuspended(PagtowebArrecadacaoReceiptPanel, { props: { clientId: 7, canConsult: true }, global: { stubs } })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(1))
    await wrapper.find('input').setValue('12345678901234567')
    await wrapper.findAll('button').find(button => button.text() === 'Solicitar comprovante')?.trigger('click')
    expect(requestReceipt).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('•••••••••••••4567')
    expect(wrapper.text()).not.toContain('12345678901234567')

    await wrapper.findAll('button').find(button => button.text() === 'Confirmar solicitação')?.trigger('click')
    await vi.waitFor(() => expect(requestReceipt).toHaveBeenCalledWith(7, '12345678901234567'))
    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(2))
  })

  it('não disponibiliza solicitação para perfil sem permissão', async () => {
    const wrapper = await mountSuspended(PagtowebArrecadacaoReceiptPanel, { props: { clientId: 7, canConsult: false }, global: { stubs } })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(wrapper.text()).toContain('Solicitação indisponível para seu perfil')
    expect(wrapper.text()).not.toContain('Solicitar comprovante')
  })
})
