import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CommunicationModals from '../../app/components/monitoring/PgdasdCommunicationModals.vue'
import HistoryView from '../../app/components/monitoring/PgdasdHistoryView.vue'

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

// O modal é compartilhado com DCTFWeb, mas estes testes exercitam apenas PGDAS-D.
// O mock evita inicializar outra API e comprova que a leitura não cruza domínios.
vi.mock('../../app/composables/useDctfwebMonitoring', () => ({
  useDctfwebMonitoring: () => ({
    fetchPreview,
    fetchTracking,
    updatePreferences,
    evidenceDownloadUrl: (clientId: number, id: number) =>
      `/dctfweb/clients/${clientId}/evidence/${id}`
  })
}))

vi.stubGlobal('useToast', () => ({ add: vi.fn() }))

const stubs = {
  UModal: defineComponent({
    props: { open: Boolean },
    emits: ['update:open'],
    setup: (props, { slots }) => () => props.open
      ? h('div', { 'data-stub': 'modal' }, [slots.content?.(), slots.body?.(), slots.footer?.()])
      : null
  }),
  UButton: defineComponent({
    inheritAttrs: false,
    props: {
      label: { type: String, default: '' },
      disabled: { type: Boolean, default: false },
      to: { type: String, default: '' }
    },
    setup: (props, { attrs, slots }) => () => props.to
      ? h('a', { ...attrs, href: props.to }, slots.default?.() || props.label)
      : h('button', {
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
    setup: (props, { slots }) => () => h('span', props.label || slots.default?.())
  }),
  UPageCard: defineComponent({
    props: {
      title: { type: String, default: '' },
      description: { type: String, default: '' }
    },
    setup: (props, { slots, attrs }) => () => h('section', attrs, [
      props.title,
      props.description,
      slots.default?.()
    ])
  }),
  UCard: defineComponent({
    setup: (_props, { slots }) => () => h('section', [slots.header?.(), slots.default?.(), slots.footer?.()])
  }),
  UCheckbox: defineComponent({
    props: { modelValue: Boolean, disabled: Boolean, label: { type: String, default: '' } },
    emits: ['update:modelValue'],
    setup: (props, { attrs, emit }) => () => h('label', [
      h('input', {
        ...attrs,
        type: 'checkbox',
        checked: props.modelValue,
        disabled: props.disabled || undefined,
        onChange: (event: Event) =>
          emit('update:modelValue', (event.target as HTMLInputElement).checked)
      }),
      props.label
    ])
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

describe('histórico e modais PGDAS-D sem efeito colateral de leitura', () => {
  it('abrir histórico executa apenas o GET local', async () => {
    const wrapper = await mountSuspended(HistoryView, {
      props: { clientId: 7 },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(collectDocuments).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="pgdasd-history-view"]').text())
      .toContain('Nenhum histórico local')
  })

  it('resume o histórico PGDAS-D sem repetir identificação do cliente', async () => {
    fetchHistory.mockResolvedValueOnce({
      client: { id: 7, legal_name: 'ACME LTDA', cnpj_masked: '12.***.***/****-90' },
      expected_period_key: '2026-06',
      declaration_state: 'CURRENT',
      declaration_state_reason: 'EXPECTED_PA_FOUND',
      last_valid_query_at: '2026-07-18T14:12:00Z',
      periods: []
    })
    const wrapper = await mountSuspended(HistoryView, {
      props: { clientId: 7 },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Em dia'))
    const card = wrapper.get('[data-testid="pgdasd-compact-summary-card"]')
    expect(card.text()).toContain('Histórico PGDAS-D')
    expect(card.text()).toContain('Declarações, DAS e documentos armazenados localmente.')
    expect(card.text()).toContain('PA esperado 06/2026')
    expect(card.text()).toContain('Última consulta')
    expect(card.text()).not.toContain('ACME LTDA')
    expect(card.text()).not.toContain('12.***.***/****-90')
    expect(card.text()).not.toContain('EXPECTED_PA_FOUND')
  })

  it('lista os cinco documentos locais como downloads pelo download_path da API', async () => {
    fetchHistory.mockResolvedValueOnce({
      client: { id: 7, legal_name: 'ACME LTDA' },
      declaration_state: 'CURRENT',
      periods: [{
        period_key: '2026-06',
        artifacts: [],
        documents: [
          { id: 1, kind: 'DECLARACAO', filename: 'declaracao.pdf', download_path: '/api/pgdasd/1' },
          { id: 2, kind: 'RECIBO', filename: 'recibo.pdf', download_path: '/api/pgdasd/2' },
          { id: 3, kind: 'NOTIFICACAO_MAED', filename: 'notificacao.pdf', download_path: '/api/pgdasd/3' },
          { id: 4, kind: 'DARF_MAED', filename: 'darf.pdf', download_path: '/api/pgdasd/4' },
          { id: 5, kind: 'EXTRATO', filename: 'extrato.pdf', download_path: '/api/pgdasd/5' }
        ]
      }]
    })
    const wrapper = await mountSuspended(HistoryView, {
      props: { clientId: 7 },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Documentos'))
    const links = wrapper.findAll('[data-testid="pgdasd-history-table"] a')
    expect(links.map(link => link.attributes('aria-label'))).toEqual([
      'Baixar Declaração',
      'Baixar Recibo',
      'Baixar MAED',
      'Baixar DAS da MAED',
      'Baixar Extrato'
    ])
    expect(links.map(link => link.attributes('href'))).toEqual([
      '/api/pgdasd/1',
      '/api/pgdasd/2',
      '/api/pgdasd/3',
      '/api/pgdasd/4',
      '/api/pgdasd/5'
    ])
    const mobileLinks = wrapper.findAll('[data-testid="pgdasd-history-mobile"] a')
    expect(mobileLinks.map(link => link.attributes('aria-label'))).toEqual([
      'Baixar Declaração',
      'Baixar Recibo',
      'Baixar MAED',
      'Baixar DAS da MAED',
      'Baixar Extrato'
    ])
    expect(collectDocuments).not.toHaveBeenCalled()
  })

  it('não mostra ação de download quando o histórico não tem artefatos', async () => {
    const wrapper = await mountSuspended(HistoryView, {
      props: { clientId: 7 },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(wrapper.findAll('a')).toHaveLength(0)
  })

  it('consolida todos os PAs em uma única tabela de declaração e DAS', async () => {
    fetchHistory.mockResolvedValueOnce({
      client: { id: 7, legal_name: 'ACME LTDA' },
      periods: [{
        period_key: '2026-06',
        declarations: [{ declaration_number: 'DECL-06', normalized_operation_type: 'RECTIFIER', transmitted_at: '2026-07-07T11:32:14Z', malha: false }],
        das: [{ das_number: 'DAS-06', issued_at: '2026-07-07T11:39:14Z', payment_located: false }]
      }]
    })
    const wrapper = await mountSuspended(HistoryView, {
      props: { clientId: 7 },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('PA 06/2026'))
    expect(wrapper.get('[data-testid="pgdasd-history-table"]').text()).toContain('Nº declaração')
    expect(wrapper.get('[data-testid="pgdasd-history-table"]').text()).toContain('Nº DAS')
    expect(wrapper.get('[data-testid="pgdasd-history-table"]').text()).toContain('Documentos')
    expect(wrapper.findAll('[data-testid="pgdasd-history-table"] table')).toHaveLength(1)
    expect(wrapper.find('[data-testid="pgdasd-history-mobile"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="pgdasd-mobile-period-2026-06"]').text()).toContain('DECL-06')
    expect(wrapper.get('[data-testid="pgdasd-mobile-period-2026-06"]').text()).toContain('DAS-06')
    expect(wrapper.text()).toContain('Retificadora')
    expect(wrapper.text()).not.toContain('RECTIFIER')
    expect(wrapper.text()).toContain('Não')
    expect(collectDocuments).not.toHaveBeenCalled()
  })

  it('oferece a primeira coleta pelo PA esperado, mas só chama a API após confirmação', async () => {
    fetchHistory.mockResolvedValueOnce({
      client: { id: 7, legal_name: 'ACME LTDA' },
      expected_period_key: '2026-06',
      declaration_state: 'UNVERIFIED',
      periods: []
    })
    const wrapper = await mountSuspended(HistoryView, {
      props: { clientId: 7, canCollectDocuments: true },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Buscar declaração e recibo'))
    await wrapper.findAll('button').find(button => button.text().includes('Buscar declaração e recibo'))?.trigger('click')
    expect(collectDocuments).not.toHaveBeenCalled()

    await vi.waitFor(() => expect(wrapper.text()).toContain('Consulta manual e potencialmente faturável'))
    expect(wrapper.text()).toContain('ACME LTDA · PA 06/2026')
    const acknowledgement = wrapper.find('input[type="checkbox"]')
    await acknowledgement.setValue(true)
    await wrapper.findAll('button').find(button => button.text() === 'Solicitar documentos')?.trigger('click')
    await vi.waitFor(() => expect(collectDocuments).toHaveBeenCalledWith(7, {
      period_key: '2026-06',
      declaration_number: null,
      confirmed: true
    }))
  })

  it('exige confirmação antes de coletar documentos de uma declaração observada', async () => {
    fetchHistory.mockResolvedValueOnce({
      client: { id: 7, legal_name: 'ACME LTDA' },
      periods: [{
        period_key: '2026-05',
        declarations: [{ declaration_number: '12345', transmitted_at: '2026-06-01T12:00:00Z' }]
      }]
    })
    const wrapper = await mountSuspended(HistoryView, {
      props: { clientId: 7, canCollectDocuments: true },
      global: { stubs }
    })

    const collectSelector = 'button[aria-label="Buscar documentos de 05/2026"]'
    await vi.waitFor(() => expect(wrapper.findAll(collectSelector).length).toBeGreaterThan(0))
    await wrapper.findAll(collectSelector)[0]!.trigger('click')
    expect(collectDocuments).not.toHaveBeenCalled()
    await wrapper.find('input[type="checkbox"]').setValue(true)
    await wrapper.findAll('button').find(button => button.text() === 'Solicitar documentos')?.trigger('click')
    await vi.waitFor(() => expect(collectDocuments).toHaveBeenCalledWith(7, {
      period_key: '2026-05',
      declaration_number: '12345',
      confirmed: true
    }))
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
