<script setup lang="ts">
/**
 * Toolbar de filtros no padrão customers.vue:
 * UInput max-w-sm à esquerda + ações à direita.
 */
import type { Client, Establishment } from '~/types/api'
import { documentKindFilterItems } from '~/utils/document-kinds'
import {
  FILTER_ALL,
  selectAllItem,
  type NotesFilterState,
  type NotesViewMode
} from '~/utils/notes-filters'

const filters = defineModel<NotesFilterState>('filters', { required: true })
/** Filtro operacional da lista de clientes (Documentos → Por cliente). */
const operationalFilter = defineModel<string>('operationalFilter', { default: 'total' })

const props = defineProps<{
  clients: Client[]
  establishments: Establishment[]
  loadingFilters?: boolean
  view?: NotesViewMode
  selectedCount?: number
  canExport?: boolean
  exporting?: boolean
}>()

const emit = defineEmits<{
  apply: []
  reset: []
  clientChange: []
  exportSelection: []
}>()

/** Só recortes de captura — certificado/cadastro ficam em /clients. */
const clientOperationalItems = [
  { label: 'Todos', value: 'total' },
  { label: 'Captura com problema', value: 'capture_problem' }
]

const open = ref(false)

const clientItems = computed(() => [
  selectAllItem('Todos os clientes'),
  ...props.clients.map(client => ({
    label: client.display_name || client.legal_name || client.name,
    value: String(client.id)
  }))
])

const establishmentItems = computed(() => [
  selectAllItem('Todos os est.'),
  ...props.establishments.map(establishment => ({
    label: establishment.trade_name
      ? `${establishment.trade_name} · ${establishment.cnpj}`
      : establishment.cnpj,
    value: String(establishment.id)
  }))
])

const kindItems = documentKindFilterItems('Todos os tipos')

/** Grupos operacionais — API expande CANCELLED/AUTHORIZED/UNKNOWN. */
const statusItems = [
  selectAllItem('Situação'),
  { label: 'Autorizada', value: 'AUTHORIZED' },
  { label: 'Cancelada', value: 'CANCELLED' },
  { label: 'Em revisão', value: 'UNKNOWN' }
]

const roleItems = [
  selectAllItem('Todos os papéis'),
  { label: 'Emitente', value: 'ISSUER' },
  { label: 'Tomador', value: 'TAKER' },
  { label: 'Intermediário', value: 'INTERMEDIARY' },
  { label: 'Remetente', value: 'SENDER' },
  { label: 'Destinatário', value: 'RECIPIENT' },
  { label: 'Expedidor', value: 'EXPEDITOR' },
  { label: 'Recebedor', value: 'RECEIVER' }
]

const directionItems = [
  selectAllItem('Todas as direções'),
  { label: 'Entrada', value: 'IN' },
  { label: 'Saída', value: 'OUT' }
]

const qualityItems = [
  selectAllItem('Todas as qualidades'),
  { label: 'Original', value: 'ORIGINAL' },
  { label: 'Original via autXML', value: 'AUTXML_ORIGINAL' },
  { label: 'Oficial redigido', value: 'AUTXML_REDACTED' }
]

const acquisitionSourceItems = [
  selectAllItem('Todas as origens'),
  { label: 'DistDFe CT-e do cliente', value: 'CTE_DIST_NSU' },
  { label: 'DistDFe autXML do escritório', value: 'CTE_AUTXML_DIST_NSU' },
  { label: 'Entrega autenticada do emissor', value: 'EMITTER_PUSH' },
  { label: 'Importação XML', value: 'MANUAL_XML' },
  { label: 'Importação ZIP', value: 'MANUAL_ZIP' }
]

/** Valores alinhados a App\Enums\CteCoverageStatus. */
const coverageItems = [
  selectAllItem('Todas as coberturas'),
  { label: 'Capturado (original)', value: 'CAPTURED_ORIGINAL' },
  { label: 'Capturado (autXML redigido)', value: 'CAPTURED_AUTXML_REDACTED' },
  { label: 'Pendente de importação', value: 'PENDING_IMPORT' },
  { label: 'Lacuna histórica', value: 'HISTORICAL_GAP' },
  { label: 'Bloqueado', value: 'BLOCKED' },
  { label: 'Sem atividade observada', value: 'NO_ACTIVITY' }
]

const hasClient = computed(() => filters.value.client_id !== FILTER_ALL && !!filters.value.client_id)

const searchPlaceholder = computed(() =>
  props.view === 'client'
    ? 'Filtrar por nome ou CNPJ/CPF…'
    : 'Buscar número, emitente, destinatário, CNPJ ou chave…'
)

const activeCount = computed(() => {
  let n = 0
  const f = filters.value
  if (f.q) n++
  if (props.view === 'client') {
    if (operationalFilter.value && operationalFilter.value !== 'total') n++
    return n
  }
  if (f.kind && f.kind !== FILTER_ALL) n++
  if (f.direction && f.direction !== FILTER_ALL) n++
  if (f.fiscal_role && f.fiscal_role !== FILTER_ALL) n++
  if (f.acquisition_source && f.acquisition_source !== FILTER_ALL) n++
  if (f.artifact_quality && f.artifact_quality !== FILTER_ALL) n++
  if (f.coverage_status && f.coverage_status !== FILTER_ALL) n++
  if (f.client_id && f.client_id !== FILTER_ALL) n++
  if (f.establishment_id && f.establishment_id !== FILTER_ALL) n++
  if (f.status && f.status !== FILTER_ALL) n++
  if (f.issuer_cnpj) n++
  if (f.taker_cnpj) n++
  if (f.competence) n++
  if (f.issued_from || f.issued_to) n++
  return n
})
</script>

<template>
  <div class="flex w-full flex-col gap-3">
    <!-- Toolbar customers.vue: busca esquerda · ações direita -->
    <div class="flex flex-wrap items-center justify-between gap-1.5">
      <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
        <UInput
          v-model="filters.q"
          class="max-w-sm"
          icon="i-lucide-search"
          :placeholder="searchPlaceholder"
          :aria-label="view === 'client' ? 'Filtrar clientes por nome ou CNPJ/CPF' : 'Buscar no catálogo de documentos'"
          @keydown.enter.prevent="emit('apply')"
        />
        <USelect
          v-if="view === 'client'"
          v-model="operationalFilter"
          :items="clientOperationalItems"
          value-key="value"
          class="min-w-44"
          aria-label="Filtro operacional de captura e certificado"
          @update:model-value="emit('apply')"
        />
      </div>

      <div class="flex flex-wrap items-center gap-1.5">
        <UButton
          v-if="canExport && selectedCount"
          color="primary"
          variant="subtle"
          icon="i-lucide-package"
          label="Exportar seleção"
          :loading="exporting"
          @click="emit('exportSelection')"
        >
          <template #trailing>
            <UKbd>{{ selectedCount }}</UKbd>
          </template>
        </UButton>

        <UButton
          v-if="view !== 'client'"
          color="neutral"
          variant="outline"
          icon="i-lucide-list-filter"
          :label="activeCount ? `Filtros (${activeCount})` : 'Filtros'"
          @click="() => { open = !open }"
        />
        <UButton
          color="primary"
          variant="soft"
          label="Aplicar"
          @click="emit('apply')"
        />
        <UButton
          v-if="view === 'client' && (filters.q || operationalFilter !== 'total')"
          color="neutral"
          variant="ghost"
          label="Limpar"
          @click="emit('reset')"
        />
      </div>
    </div>

    <form
      v-show="open && view !== 'client'"
      class="grid gap-2 rounded-lg border border-default bg-elevated/25 p-3 sm:grid-cols-2 lg:grid-cols-3"
      @submit.prevent="emit('apply')"
    >
      <UFormField label="Tipo">
        <USelect
          v-model="filters.kind"
          :items="kindItems"
          class="w-full"
          aria-label="Filtrar por tipo de documento"
        />
      </UFormField>
      <UFormField label="Cliente">
        <USelect
          v-model="filters.client_id"
          :items="clientItems"
          :loading="loadingFilters"
          class="w-full"
          aria-label="Filtrar por cliente"
          @update:model-value="emit('clientChange')"
        />
      </UFormField>
      <UFormField label="Estabelecimento">
        <USelect
          v-model="filters.establishment_id"
          :items="establishmentItems"
          :disabled="!hasClient"
          class="w-full"
          aria-label="Filtrar por estabelecimento"
        />
      </UFormField>
      <UFormField label="Situação">
        <USelect v-model="filters.status" :items="statusItems" class="w-full" />
      </UFormField>
      <UFormField label="Papel fiscal">
        <USelect v-model="filters.fiscal_role" :items="roleItems" class="w-full" />
      </UFormField>
      <UFormField label="Direção">
        <USelect v-model="filters.direction" :items="directionItems" class="w-full" />
      </UFormField>
      <UFormField
        v-if="filters.kind === 'CTE' || filters.kind === FILTER_ALL"
        label="Origem CT-e"
      >
        <USelect
          v-model="filters.acquisition_source"
          :items="acquisitionSourceItems"
          class="w-full"
          aria-label="Filtrar por origem de aquisição do CT-e"
        />
      </UFormField>
      <UFormField
        v-if="filters.kind === 'CTE' || filters.kind === FILTER_ALL"
        label="Qualidade CT-e"
      >
        <USelect
          v-model="filters.artifact_quality"
          :items="qualityItems"
          class="w-full"
          aria-label="Filtrar por qualidade do artefato CT-e"
        />
      </UFormField>
      <UFormField
        v-if="filters.kind === 'CTE' || filters.kind === FILTER_ALL"
        label="Cobertura CT-e"
      >
        <USelect
          v-model="filters.coverage_status"
          :items="coverageItems"
          class="w-full"
          aria-label="Filtrar por cobertura CT-e"
        />
      </UFormField>
      <UFormField
        label="CNPJ emitente / prestador"
        hint="NF-e/NFC-e: emit · NFS-e: prestador"
      >
        <UInput
          v-model="filters.issuer_cnpj"
          class="w-full font-mono"
          placeholder="Somente dígitos ou formatado"
          aria-label="Filtrar por CNPJ do emitente ou prestador"
        />
      </UFormField>
      <UFormField
        label="CNPJ destinatário / tomador"
        hint="NF-e/NFC-e: dest · NFS-e: tomador (toma)"
      >
        <UInput
          v-model="filters.taker_cnpj"
          class="w-full font-mono"
          placeholder="Somente dígitos ou formatado"
          aria-label="Filtrar por CNPJ do destinatário ou tomador"
        />
      </UFormField>
      <UFormField label="Competência">
        <UInput v-model="filters.competence" class="w-full" placeholder="AAAA-MM" />
      </UFormField>
      <UFormField label="Emissão de">
        <UInput
          v-model="filters.issued_from"
          type="date"
          class="w-full"
          aria-label="Data de emissão inicial"
        />
      </UFormField>
      <UFormField label="Emissão até">
        <UInput
          v-model="filters.issued_to"
          type="date"
          class="w-full"
          aria-label="Data de emissão final"
        />
      </UFormField>
      <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
        <UButton type="submit" color="primary" label="Aplicar filtros" />
        <UButton
          type="button"
          color="neutral"
          variant="ghost"
          label="Limpar"
          @click="emit('reset')"
        />
      </div>
    </form>
  </div>
</template>
