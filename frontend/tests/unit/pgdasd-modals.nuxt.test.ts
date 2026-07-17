import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CommunicationModals from '../../app/components/monitoring/PgdasdCommunicationModals.vue'
import HistoryModal from '../../app/components/monitoring/PgdasdHistoryModal.vue'

const fetchHistory = vi.fn()
const collectDocuments = vi.fn()
const fetchPreview = vi.fn()
const fetchTracking = vi.fn()
const updatePreferences = vi.fn()

vi.mock('../../app/composables/usePgdasdMonitoring', () => ({
  usePgdasdMonitoring: () => ({
    fetchHistory,
    collectDocuments,
    fetchPreview,
    fetchTracking,
    updatePreferences,
    artifactDownloadUrl: (id: number) => `/artifacts/${id}`
  })
}))

vi.mock('../../app/composables/usePgmeiMonitoring', () => ({
  usePgmeiMonitoring: () => ({
    fetchPreview,
    fetchTracking,
    updatePreferences
  })
}))

vi.stubGlobal('useToast', () => ({ add: vi.fn() }))

const stubs = {
  UModal: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup: (props, { slots }) => () => props.open
      ? h('div', { 'data-stub': 'modal' }, [slots.body?.(), slots.footer?.()])
      : null
  }),
  UButton: defineComponent({
    inheritAttrs: false,
    props: {
      label: { type: String, default: '' },
      disabled: { type: Boolean, default: false }
    },
    setup: (props, { attrs, slots }) => () => h('button', {
      ...attrs,
      disabled: props.disabled || undefined
    }, slots.default?.() || props.label)
  }),
  UAlert: defineComponent({
    props: { title: { type: String, default: '' }, description: { type: String, default: '' } },
    setup: (props, { slots }) => () => h('div', { role: 'alert' }, [
      props.title,
      props.description,
      slots.actions?.()
    ])
  }),
  UBadge: defineComponent({
    props: { label: { type: String, default: '' } },
    setup: props => () => h('span', props.label)
  }),
  UCard: defineComponent({
    setup: (_props, { slots }) => () => h('section', [slots.header?.(), slots.default?.()])
  }),
  UFormField: defineComponent({
    setup: (_props, { slots }) => () => h('label', slots.default?.())
  }),
  UTooltip: defineComponent({
    props: { text: { type: String, default: '' } },
    setup: (_props, { slots }) => () => slots.default?.()
  }),
  USwitch: defineComponent({
    props: { modelValue: Boolean, disabled: Boolean },
    emits: ['update:modelValue'],
    setup: (props, { attrs, emit }) => () => h('input', {
      ...attrs,
      type: 'checkbox',
      checked: props.modelValue,
      disabled: props.disabled || undefined,
      onChange: (event: Event) =>
        emit('update:modelValue', (event.target as HTMLInputElement).checked)
    })
  }),
  USeparator: true,
  USkeleton: true,
  UIcon: true,
  MonitoringPgdasdRbt12Value: true
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchHistory.mockResolvedValue({
    client: { id: 7, legal_name: 'ACME LTDA', cnpj_masked: '12.***.***/****-90' },
    declaration_state: 'CURRENT',
    periods: []
  })
  fetchPreview.mockResolvedValue({
    client: { id: 7, legal_name: 'ACME LTDA' },
    period_key: '202606',
    execution_mode: 'TEMPLATE_ONLY',
    can_send: false,
    channels: [],
    documents: [],
    warnings: []
  })
  fetchTracking.mockResolvedValue({ client_id: 7, status: 'NO_HISTORY', channels: [] })
  updatePreferences.mockResolvedValue({
    client_id: 7,
    automatic_requested: false,
    automatic_effective: false,
    email_enabled: false,
    whatsapp_enabled: false,
    lock_version: 1,
    execution_mode: 'TEMPLATE_ONLY',
    eligible_channels: [],
    tracking_status: 'NOT_CONFIGURED'
  })
})

describe('modais PGDAS-D sem efeito colateral de leitura', () => {
  it('abrir histórico executa apenas o GET local', async () => {
    const wrapper = await mountSuspended(HistoryModal, {
      props: { open: true, clientId: 7, clientName: 'ACME LTDA' },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(collectDocuments).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="pgdasd-history-modal"]').text())
      .toContain('Nenhum histórico local')
  })

  it('abrir prévia mantém o botão final desabilitado e não salva preferência', async () => {
    const wrapper = await mountSuspended(CommunicationModals, {
      props: {
        previewOpen: true,
        trackingOpen: false,
        prefsOpen: false,
        clientId: 7,
        clientName: 'ACME LTDA',
        canManage: true
      },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchPreview).toHaveBeenCalledWith(7))
    expect(updatePreferences).not.toHaveBeenCalled()
    const send = wrapper.findAll('button').find(button => button.text().includes('Enviar agora'))
    expect(send?.attributes('disabled')).toBeDefined()
  })

  it('abrir rastreio não cria evento nem marca leitura', async () => {
    await mountSuspended(CommunicationModals, {
      props: {
        previewOpen: false,
        trackingOpen: true,
        prefsOpen: false,
        clientId: 7,
        clientName: 'ACME LTDA'
      },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchTracking).toHaveBeenCalledWith(7))
    expect(updatePreferences).not.toHaveBeenCalled()
    expect(collectDocuments).not.toHaveBeenCalled()
  })

  it('preserva lock_version zero ao criar a primeira preferência', async () => {
    const wrapper = await mountSuspended(CommunicationModals, {
      props: {
        previewOpen: false,
        trackingOpen: false,
        prefsOpen: true,
        clientId: 7,
        clientName: 'ACME LTDA',
        canManage: true
      },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchPreview).toHaveBeenCalledWith(7))
    const save = wrapper.findAll('button').find(button => button.text().includes('Salvar preferências'))
    await save?.trigger('click')

    await vi.waitFor(() => expect(updatePreferences).toHaveBeenCalledWith(7, {
      automatic_requested: false,
      email_enabled: false,
      whatsapp_enabled: false,
      lock_version: 0
    }))
  })
})
