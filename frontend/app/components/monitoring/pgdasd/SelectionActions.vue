<script setup lang="ts">
/**
 * Ações PGDAS-D / REGIMEAPURACAO / DEFIS na toolbar ao selecionar linhas.
 * Consultas SERPRO em lote = uma chamada por CNPJ (catálogo Integra Contador).
 */
import type { DropdownMenuItem } from '@nuxt/ui'
import type { SimplesMeiClientRow } from '~/types/fiscal-modules'
import {
  buildPgdasdSelectionMenu,
  type PgdasdActionHandlers,
  type PgdasdBatchConsultKind
} from '~/utils/pgdasd-action-items'
import { COMPACT_BUTTON_LABEL_UI } from '~/utils/list-filter-layout'

const props = defineProps<{
  selectedClientIds: number[]
  selectedCount: number
  rows: SimplesMeiClientRow[]
  handlers: PgdasdActionHandlers
}>()

const emit = defineEmits<{
  clear: []
  refresh: []
}>()

const {
  requestConsult: requestRegimeCalendar
} = useRegimeCalendarMonitoring()
const { requestConsult: requestRegimeOption } = useRegimeOptionMonitoring()
const { requestConsult: requestRegimeResolution } = useRegimeResolutionMonitoring()
const { requestConsult: requestDefisConsult } = useDefisDeclarationsMonitoring()
const { requestConsult: requestDefisLatestConsult } = useDefisLatestDeclarationMonitoring()
const toast = useToast()

const busy = ref(false)
const confirmOpen = ref(false)
const pendingKind = ref<PgdasdBatchConsultKind | null>(null)
const pendingIds = ref<number[]>([])

const pendingLabel = computed(() => {
  switch (pendingKind.value) {
    case 'regime': return 'regimes (CONSULTARANOSCALENDARIOS102)'
    case 'regime_option': return 'opção anual de regime (CONSULTAROPCAOREGIME103)'
    case 'regime_resolution': return 'resolução Regime de Caixa (CONSULTARRESOLUCAO104)'
    case 'defis': return 'declarações DEFIS (CONSDECLARACAO142)'
    case 'defis_latest': return 'última DEFIS (CONSULTIMADECREC143)'
    default: return 'consulta SERPRO'
  }
})

function openBatchConfirm(kind: PgdasdBatchConsultKind, clientIds: number[]) {
  if (!clientIds.length || busy.value) return
  if (clientIds.length > 100) {
    toast.add({ title: 'Selecione no máximo 100 clientes.', color: 'warning' })
    return
  }
  pendingKind.value = kind
  pendingIds.value = [...clientIds]
  confirmOpen.value = true
}

const handlersWithBatch = computed<PgdasdActionHandlers>(() => ({
  ...props.handlers,
  onBatchConsult: openBatchConfirm
}))

const items = computed<DropdownMenuItem[][]>(() => {
  if (props.selectedCount < 1) return []
  return buildPgdasdSelectionMenu({
    clientIds: props.selectedClientIds,
    rows: props.rows,
    handlers: handlersWithBatch.value,
    onClear: () => emit('clear')
  })
})

async function runOne(kind: PgdasdBatchConsultKind, clientId: number) {
  const year = new Date().getFullYear()
  switch (kind) {
    case 'regime':
      await requestRegimeCalendar(clientId)
      return
    case 'regime_option':
      await requestRegimeOption(clientId, year)
      return
    case 'regime_resolution':
      await requestRegimeResolution(clientId, year)
      return
    case 'defis':
      await requestDefisConsult(clientId)
      return
    case 'defis_latest':
      await requestDefisLatestConsult(clientId, year)
  }
}

async function confirmBatch() {
  const kind = pendingKind.value
  const ids = pendingIds.value
  if (!kind || !ids.length || busy.value) return
  busy.value = true
  let ok = 0
  let fail = 0
  try {
    for (const clientId of ids) {
      try {
        await runOne(kind, clientId)
        ok++
      } catch {
        fail++
      }
    }
    toast.add({
      title: 'Consultas SERPRO enfileiradas',
      description: `${ok} ok${fail ? ` · ${fail} falha(s)` : ''} de ${ids.length} · ${pendingLabel.value}`,
      color: fail && !ok ? 'error' : fail ? 'warning' : 'success'
    })
    confirmOpen.value = false
    pendingKind.value = null
    pendingIds.value = []
    if (ok) {
      emit('clear')
      emit('refresh')
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div
    v-if="selectedCount > 0"
    data-testid="pgdasd-selection-actions"
  >
    <UDropdownMenu
      :items="items"
      :content="{ align: 'start' }"
    >
      <UButton
        color="primary"
        variant="soft"
        icon="i-lucide-list-checks"
        label="Ações"
        aria-label="Ações PGDAS-D, regime e DEFIS da seleção"
        :ui="COMPACT_BUTTON_LABEL_UI"
        :loading="busy"
        data-testid="pgdasd-selection-actions-menu"
      >
        <template #trailing>
          <UKbd>{{ selectedCount }}</UKbd>
        </template>
      </UButton>
    </UDropdownMenu>

    <UModal
      v-model:open="confirmOpen"
      title="Confirmar consultas SERPRO"
      :description="`Será feita 1 chamada Consultar por cliente (${pendingIds.length}) — ${pendingLabel}. Pode ser faturável.`"
      :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-lg', footer: 'justify-end' }"
    >
      <template #body>
        <UAlert
          color="warning"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          title="Uma chamada por CNPJ (Integra Contador)"
        >
          <template #description>
            O catálogo SERPRO não tem lote nativo para essas operações. O hub enfileira
            uma consulta por contribuinte selecionado.
          </template>
        </UAlert>
      </template>
      <template #footer>
        <UButton
          color="neutral"
          variant="ghost"
          label="Cancelar"
          :disabled="busy"
          @click="confirmOpen = false"
        />
        <UButton
          color="primary"
          icon="i-lucide-refresh-cw"
          label="Confirmar consultas"
          :loading="busy"
          data-testid="pgdasd-batch-consult-confirm"
          @click="confirmBatch"
        />
      </template>
    </UModal>
  </div>
</template>
