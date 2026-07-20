/**
 * Menu de ações da carteira PGDAS-D, agrupado pelo catálogo SERPRO Integra Contador
 * (Integra-SN): PGDASD · REGIMEAPURACAO · DEFIS + ações locais do hub.
 *
 * Cada chamada SERPRO é por contribuinte; “lote” no hub = uma Consultar por client_id.
 * @see https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/catalogo_de_servicos/
 */
import type { DropdownMenuItem } from '@nuxt/ui'
import type { SimplesMeiClientRow } from '~/types/fiscal-modules'

/** Escopo: single = precisa 1 cliente; batch = 1..N (uma chamada SERPRO por CNPJ). */
export type PgdasdActionScope = 'single' | 'batch'

export type PgdasdBatchConsultKind
  = 'regime'
    | 'regime_option'
    | 'regime_resolution'
    | 'defis'
    | 'defis_latest'

export type PgdasdActionHandlers = {
  canQueryRegime: boolean
  canQueryRegimeOption: boolean
  canQueryRegimeResolution: boolean
  canQueryDefis: boolean
  canQueryDefisLatest: boolean
  /** Cliente único (histórico local / preferências registradas / destinatários). */
  onConfigure: (row: SimplesMeiClientRow) => void
  onRegimeHistory: (row: SimplesMeiClientRow) => void
  onRegimeOptionHistory: (row: SimplesMeiClientRow) => void
  onRegimeResolutionHistory: (row: SimplesMeiClientRow) => void
  onDefisHistory: (row: SimplesMeiClientRow) => void
  onDefisLatestHistory: (row: SimplesMeiClientRow) => void
  onDefisSpecificHistory: (row: SimplesMeiClientRow) => void
  onPreview?: (row: SimplesMeiClientRow) => void
  onTracking?: (row: SimplesMeiClientRow) => void
  onHistory?: (row: SimplesMeiClientRow) => void
  /** Consultas SERPRO — aceitam 1..N client_ids (loop no hub). Injetado pela SelectionActions. */
  onBatchConsult?: (kind: PgdasdBatchConsultKind, clientIds: number[]) => void
}

function firstRow(
  rows: SimplesMeiClientRow[],
  clientIds: number[]
): SimplesMeiClientRow | null {
  if (clientIds.length !== 1) return null
  const id = clientIds[0]
  return rows.find(row => row.client_id === id) || null
}

/**
 * Monta o menu agrupado por família SERPRO.
 * Itens `single` ficam disabled quando N ≠ 1 (com descrição); `batch` ativos para N≥1.
 */
export function buildPgdasdSelectionMenu(args: {
  clientIds: number[]
  rows: SimplesMeiClientRow[]
  handlers: PgdasdActionHandlers
  onClear: () => void
}): DropdownMenuItem[][] {
  const { clientIds, rows, handlers, onClear } = args
  const count = clientIds.length
  const single = firstRow(rows, clientIds)
  const singleOnly = count !== 1
  const singleHint = 'Selecione um único cliente para abrir o histórico local.'

  const hub: DropdownMenuItem[] = [
    {
      label: 'Comunicação · somente leitura',
      type: 'label'
    },
    {
      label: 'Preferências registradas',
      icon: 'i-lucide-info',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onConfigure(single)
    }
  ]
  if (handlers.onPreview) {
    hub.push({
      label: 'Destinatários e documentos locais',
      icon: 'i-lucide-message-square-text',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onPreview?.(single)
    })
  }
  if (handlers.onTracking) {
    hub.push({
      label: 'Histórico local de comunicação',
      icon: 'i-lucide-search',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onTracking?.(single)
    })
  }

  const regime: DropdownMenuItem[] = [
    {
      label: 'REGIMEAPURACAO · Integra-SN',
      type: 'label'
    },
    {
      label: 'Histórico de regimes',
      icon: 'i-lucide-calendar-range',
      disabled: singleOnly,
      description: singleOnly ? singleHint : 'Consulta local (102).',
      onSelect: () => single && handlers.onRegimeHistory(single)
    },
    {
      label: count > 1 ? `Atualizar regimes (${count})` : 'Atualizar regimes',
      icon: 'i-lucide-refresh-cw',
      disabled: !handlers.canQueryRegime || count < 1,
      description: 'SERPRO CONSULTARANOSCALENDARIOS102 — uma chamada por CNPJ.',
      onSelect: () => handlers.onBatchConsult?.('regime', clientIds)
    },
    {
      label: 'Opção anual de regime',
      icon: 'i-lucide-calendar-check-2',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onRegimeOptionHistory(single)
    },
    {
      label: count > 1 ? `Atualizar opção anual (${count})` : 'Atualizar opção anual (ano atual)',
      icon: 'i-lucide-calendar-sync',
      disabled: !handlers.canQueryRegimeOption || count < 1,
      description: 'SERPRO CONSULTAROPCAOREGIME103 — uma chamada por CNPJ.',
      onSelect: () => handlers.onBatchConsult?.('regime_option', clientIds)
    },
    {
      label: 'Resoluções do Regime de Caixa',
      icon: 'i-lucide-file-text',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onRegimeResolutionHistory(single)
    },
    {
      label: count > 1 ? `Atualizar resolução (${count})` : 'Atualizar resolução (ano atual)',
      icon: 'i-lucide-file-down',
      disabled: !handlers.canQueryRegimeResolution || count < 1,
      description: 'SERPRO CONSULTARRESOLUCAO104 — uma chamada por CNPJ.',
      onSelect: () => handlers.onBatchConsult?.('regime_resolution', clientIds)
    }
  ]

  const defis: DropdownMenuItem[] = [
    {
      label: 'DEFIS · Integra-SN',
      type: 'label'
    },
    {
      label: 'Declarações DEFIS',
      icon: 'i-lucide-files',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onDefisHistory(single)
    },
    {
      label: count > 1 ? `Atualizar declarações DEFIS (${count})` : 'Atualizar declarações DEFIS',
      icon: 'i-lucide-refresh-cw',
      disabled: !handlers.canQueryDefis || count < 1,
      description: 'SERPRO CONSDECLARACAO142 — uma chamada por CNPJ.',
      onSelect: () => handlers.onBatchConsult?.('defis', clientIds)
    },
    {
      label: 'Última DEFIS e recibo',
      icon: 'i-lucide-file-check-2',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onDefisLatestHistory(single)
    },
    {
      label: count > 1 ? `Atualizar última DEFIS (${count})` : 'Atualizar última DEFIS (ano atual)',
      icon: 'i-lucide-file-down',
      disabled: !handlers.canQueryDefisLatest || count < 1,
      description: 'SERPRO CONSULTIMADECREC143 — uma chamada por CNPJ.',
      onSelect: () => handlers.onBatchConsult?.('defis_latest', clientIds)
    },
    {
      label: 'Declaração DEFIS e recibo',
      icon: 'i-lucide-files',
      disabled: singleOnly,
      description: singleOnly ? singleHint : 'Exige declaração específica do histórico.',
      onSelect: () => single && handlers.onDefisSpecificHistory(single)
    }
  ]

  const navigation: DropdownMenuItem[] = [
    {
      label: 'Cliente',
      type: 'label'
    }
  ]
  if (handlers.onHistory) {
    navigation.push({
      label: 'Histórico de busca PGDAS-D',
      icon: 'i-lucide-history',
      disabled: singleOnly,
      description: singleOnly ? singleHint : undefined,
      onSelect: () => single && handlers.onHistory?.(single)
    })
  }
  navigation.push({
    label: 'Abrir cliente',
    icon: 'i-lucide-user-round',
    disabled: singleOnly,
    description: singleOnly ? singleHint : undefined,
    to: single ? `/monitoring/clients/${single.client_id}` : undefined
  })

  return [
    hub,
    regime,
    defis,
    navigation,
    [{
      label: 'Limpar seleção',
      icon: 'i-lucide-x',
      onSelect: onClear
    }]
  ]
}
