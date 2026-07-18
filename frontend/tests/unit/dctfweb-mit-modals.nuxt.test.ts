import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DctfwebHistoryModal from '../../app/components/monitoring/DctfwebHistoryModal.vue'
import MitListaApuracoesModal from '../../app/components/monitoring/MitListaApuracoesModal.vue'

const fetchHistory = vi.fn()
const evidenceDownloadUrl = vi.fn((clientId: number, evidenceId: number) =>
  `/api/v1/fiscal/dctfweb/clients/${clientId}/evidence/${evidenceId}/download`
)
const fetchLocalList = vi.fn()

vi.mock('../../app/composables/useDctfwebMonitoring', () => ({
  useDctfwebMonitoring: () => ({ fetchHistory, evidenceDownloadUrl })
}))

vi.mock('../../app/composables/useMitListaApuracoes', () => ({
  useMitListaApuracoes: () => ({ fetchLocalList })
}))

const stubs = {
  UModal: defineComponent({
    props: { open: Boolean },
    setup: (props, { slots }) => () => props.open
      ? h('div', { 'data-stub': 'modal' }, slots.content?.())
      : null
  }),
  UCard: defineComponent({
    setup: (_props, { slots }) => () => h('section', [slots.header?.(), slots.default?.()])
  }),
  UButton: defineComponent({
    inheritAttrs: false,
    props: { label: { type: String, default: '' }, to: { type: String, default: '' } },
    setup: (props, { attrs, slots }) => () => props.to
      ? h('a', { ...attrs, href: props.to }, slots.default?.() || props.label)
      : h('button', attrs, slots.default?.() || props.label)
  }),
  UAlert: defineComponent({
    props: { title: { type: String, default: '' } },
    setup: props => () => h('div', { role: 'alert' }, props.title)
  }),
  UBadge: defineComponent({
    props: { label: { type: String, default: '' } },
    setup: props => () => h('span', props.label)
  }),
  UIcon: true
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchHistory.mockResolvedValue({
    client: { id: 7, legal_name: 'ACME LTDA' },
    declaration_state: 'CURRENT',
    periods: []
  })
  fetchLocalList.mockResolvedValue({
    data: [],
    provenance: { source: 'LOCAL_PROJECTION', serpro_called: false }
  })
})

describe('histórico DCTFWeb local', () => {
  it('concatena documentos e artefatos, deduplica e prioriza download_path para PDF/XML', async () => {
    fetchHistory.mockResolvedValueOnce({
      declaration_state: 'CURRENT',
      periods: [{
        period_key: '2026-06',
        documents: [{
          id: 10,
          kind: 'RECIBO',
          filename: 'dctfweb-recibo-10.pdf',
          content_type: 'application/pdf',
          download_path: '/api/local/recibo-10'
        }],
        artifacts: [
          {
            id: 10,
            kind: 'RECIBO',
            filename: 'dctfweb-recibo-10.pdf',
            content_type: 'application/pdf',
            download_path: '/api/local/recibo-10'
          },
          {
            id: 11,
            kind: 'XML',
            filename: 'dctfweb-xml-11.xml',
            content_type: 'application/xml',
            download_path: '/api/local/xml-11'
          }
        ]
      }]
    })

    const wrapper = await mountSuspended(DctfwebHistoryModal, {
      props: { open: true, clientId: 7, clientName: 'ACME LTDA' },
      global: { stubs }
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('dctfweb-recibo-10.pdf'))
    expect(wrapper.text()).toContain('dctfweb-xml-11.xml')
    expect(wrapper.findAll('[data-testid="dctfweb-download-evidence"]')).toHaveLength(2)
    expect(wrapper.findAll('a').map(link => link.attributes('href'))).toEqual([
      '/api/local/recibo-10',
      '/api/local/xml-11'
    ])
    expect(evidenceDownloadUrl).not.toHaveBeenCalled()
  })

  it('mostra estado vazio local sem oferecer download', async () => {
    const wrapper = await mountSuspended(DctfwebHistoryModal, {
      props: { open: true, clientId: 7 },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchHistory).toHaveBeenCalledWith(7))
    expect(wrapper.text()).toContain('Nenhum histórico local para este cliente.')
    expect(wrapper.findAll('a')).toHaveLength(0)
  })
})

describe('lista MIT 317 local', () => {
  it('lista projeções persistidas por leitura local, sem iniciar consulta SERPRO', async () => {
    fetchLocalList.mockResolvedValueOnce({
      data: [
        {
          id: 1,
          client_id: 7,
          period_key: '2026-06',
          situation: 'UP_TO_DATE',
          lista_apuracoes_317: {
            data_encerramento: '2026-07-10',
            evento_especial: false,
            valor_total_apurado: 1250.5
          }
        },
        {
          id: 2,
          client_id: 7,
          period_key: '2026-05',
          situation: 'PENDING',
          lista_apuracoes_317: { evento_especial: true, valor_total_apurado: 0 }
        }
      ],
      provenance: { source: 'LOCAL_PROJECTION', serpro_called: false }
    })

    const wrapper = await mountSuspended(MitListaApuracoesModal, {
      props: { open: true, clientId: 7, clientName: 'ACME LTDA' },
      global: { stubs }
    })

    await vi.waitFor(() => expect(fetchLocalList).toHaveBeenCalledWith(7))
    expect(wrapper.findAll('[data-testid="mit-lista-apuracoes-item"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('06/2026')
    expect(wrapper.text()).toContain('R$ 1.250,50')
    expect(wrapper.text()).toContain('serpro_called: false')
  })

  it('não lê nada enquanto o modal MIT estiver fechado e apresenta vazio local ao abrir', async () => {
    const wrapper = await mountSuspended(MitListaApuracoesModal, {
      props: { open: false, clientId: 7 },
      global: { stubs }
    })

    expect(fetchLocalList).not.toHaveBeenCalled()
    await wrapper.setProps({ open: true })
    await vi.waitFor(() => expect(fetchLocalList).toHaveBeenCalledWith(7))
    expect(wrapper.get('[data-testid="mit-lista-apuracoes-empty"]').text())
      .toContain('Nenhuma apuração MIT 317 local')
  })
})
