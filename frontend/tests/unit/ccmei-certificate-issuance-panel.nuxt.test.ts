import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CcmeiCertificateIssuancePanel from '../../app/components/clients/ClientCcmeiCertificateIssuancePanel.vue'

const fetchHistory = vi.fn()
const requestIssue = vi.fn()
const downloadPath = vi.fn()
const toastAdd = vi.fn()

vi.mock('../../app/composables/useCcmeiCertificateIssuance', () => ({
  useCcmeiCertificateIssuance: () => ({ fetchHistory, requestIssue, downloadPath })
}))

vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('formatDateTime', (value?: string | null) => value || '—')
vi.stubGlobal('apiErrorMessage', () => 'Falha de rede')

const stubs = {
  UPageCard: defineComponent({ setup: (_props, { slots }) => () => h('section', [slots.default?.(), slots.footer?.()]) }),
  UModal: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup: (props, { slots }) => () => props.open
      ? h('section', { 'data-stub': 'modal' }, [slots.body?.(), slots.footer?.()])
      : null
  }),
  UAlert: defineComponent({ props: { title: String }, setup: (props, { slots }) => () => h('div', { role: 'alert' }, [props.title, slots.actions?.()]) }),
  UButton: defineComponent({
    props: { label: String, disabled: Boolean, loading: Boolean },
    setup: (props, { attrs, slots }) => () => h('button', { ...attrs, disabled: props.disabled || props.loading }, slots.default?.() || props.label)
  }),
  UEmpty: defineComponent({ props: { title: String, description: String }, setup: props => () => h('div', [props.title, props.description]) }),
  USkeleton: true
}

describe('painel de emissão do certificado CCMEI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchHistory.mockResolvedValue({ client_id: 7, certificates: [] })
    requestIssue.mockResolvedValue({ success: true, certificate: { id: 12 } })
    downloadPath.mockReturnValue('/download/12')
  })

  it('carrega somente o histórico local e mantém o estado vazio sanitizado', async () => {
    const wrapper = await mountSuspended(CcmeiCertificateIssuancePanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(requestIssue).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Nenhum certificado emitido')
    expect(wrapper.text()).not.toMatch(/cpf|cnpj|senha|token|base64/i)
  })

  it('abre a confirmação antes de emitir e só então recarrega o histórico', async () => {
    const wrapper = await mountSuspended(CcmeiCertificateIssuancePanel, {
      props: { clientId: 7, canConsult: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(1))
    await wrapper.findAll('button').find(button => button.text() === 'Emitir certificado')?.trigger('click')
    expect(requestIssue).not.toHaveBeenCalled()

    await vi.waitFor(() => expect(wrapper.text()).toContain('Emissão manual'))
    await wrapper.findAll('button').find(button => button.text() === 'Confirmar emissão')?.trigger('click')
    await vi.waitFor(() => expect(requestIssue).toHaveBeenCalledWith(7))
    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledTimes(2))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Emissão de certificado solicitada' }))
  })

  it('não oferece emissão ao perfil sem permissão', async () => {
    const wrapper = await mountSuspended(CcmeiCertificateIssuancePanel, {
      props: { clientId: 7, canConsult: false },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(wrapper.text()).toContain('Emissão indisponível para seu perfil')
    expect(wrapper.text()).not.toContain('Emitir certificado')
  })
})
