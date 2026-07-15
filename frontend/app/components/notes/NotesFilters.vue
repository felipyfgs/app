<script setup lang="ts">
/**
 * Toolbar de filtros no padrão customers.vue:
 * UInput max-w-sm à esquerda + ações à direita.
 */
import type { Client, Establishment } from '~/types/api'
import { documentKindFilterItems } from '~/utils/documentKinds'
import {
  FILTER_ALL,
  selectAllItem,
  type NotesFilterState,
  type NotesViewMode
} from '~/utils/notes-filters'

const filters = defineModel<NotesFilterState>('filters', { required: true })

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

const hasClient = computed(() => filters.value.client_id !== FILTER_ALL && !!filters.value.client_id)

const searchPlaceholder = computed(() =>
  props.view === 'client'
    ? 'Filtrar por cliente, CNPJ…'
    : 'Buscar número, emitente, destinatário, CNPJ ou chave…'
)

const activeCount = computed(() => {
  let n = 0
  const f = filters.value
  if (f.q) n++
  if (f.kind && f.kind !== FILTER_ALL) n++
  // direction / fiscal_role saíram da grade: recorte por CNPJ emit/dest (padrão leiaute)
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
      <UInput
        v-model="filters.q"
        class="max-w-sm"
        icon="i-lucide-search"
        :placeholder="searchPlaceholder"
        aria-label="Buscar no catálogo de documentos"
        @keydown.enter.prevent="emit('apply')"
      />

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
      </div>
    </div>

    <form
      v-show="open"
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
