<script setup lang="ts">
/**
 * Filtros compactos do catálogo — cabem no painel mestre do inbox.
 */
import type { Client, Establishment } from '~/types/api'
import {
  FILTER_ALL,
  selectAllItem,
  type NotesFilterState
} from '~/utils/notes-filters'

const filters = defineModel<NotesFilterState>('filters', { required: true })

const props = defineProps<{
  clients: Client[]
  establishments: Establishment[]
  loadingFilters?: boolean
}>()

const emit = defineEmits<{
  apply: []
  reset: []
  clientChange: []
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

const roleItems = [
  selectAllItem('Papel'),
  { label: 'Emitente', value: 'ISSUER' },
  { label: 'Tomador', value: 'TAKER' },
  { label: 'Intermediário', value: 'INTERMEDIARY' }
]

const statusItems = [
  selectAllItem('Situação'),
  { label: 'Ativa', value: 'ACTIVE' },
  { label: 'Cancelada', value: 'CANCELLED' },
  { label: 'Em revisão', value: 'UNKNOWN' },
  { label: 'Autorizada', value: 'AUTHORIZED' }
]

const hasClient = computed(() => filters.value.client_id !== FILTER_ALL && !!filters.value.client_id)

const activeCount = computed(() => {
  let n = 0
  const f = filters.value
  if (f.access_key) n++
  if (f.client_id && f.client_id !== FILTER_ALL) n++
  if (f.establishment_id && f.establishment_id !== FILTER_ALL) n++
  if (f.fiscal_role && f.fiscal_role !== FILTER_ALL) n++
  if (f.status && f.status !== FILTER_ALL) n++
  if (f.issuer_cnpj) n++
  if (f.taker_cnpj) n++
  if (f.competence) n++
  if (f.issued_from || f.issued_to) n++
  return n
})
</script>

<template>
  <div class="border-b border-default px-3 py-2 sm:px-4">
    <div class="flex flex-wrap items-center gap-2">
      <UInput
        v-model="filters.access_key"
        icon="i-lucide-search"
        placeholder="Chave de acesso…"
        class="min-w-0 flex-1"
        size="sm"
        aria-label="Filtrar por chave de acesso"
        @keydown.enter.prevent="emit('apply')"
      />
      <UButton
        color="neutral"
        variant="outline"
        size="sm"
        icon="i-lucide-list-filter"
        :label="activeCount ? `Filtros (${activeCount})` : 'Filtros'"
        @click="() => { open = !open }"
      />
      <UButton
        color="primary"
        size="sm"
        label="Aplicar"
        @click="emit('apply')"
      />
    </div>

    <form
      v-show="open"
      class="mt-3 grid gap-2 sm:grid-cols-2"
      @submit.prevent="emit('apply')"
    >
      <UFormField label="Cliente">
        <USelect
          v-model="filters.client_id"
          :items="clientItems"
          :loading="loadingFilters"
          size="sm"
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
          size="sm"
          class="w-full"
          aria-label="Filtrar por estabelecimento"
        />
      </UFormField>
      <UFormField label="Papel">
        <USelect v-model="filters.fiscal_role" :items="roleItems" size="sm" class="w-full" />
      </UFormField>
      <UFormField label="Situação">
        <USelect v-model="filters.status" :items="statusItems" size="sm" class="w-full" />
      </UFormField>
      <UFormField label="Emitente">
        <UInput v-model="filters.issuer_cnpj" size="sm" class="w-full font-mono" />
      </UFormField>
      <UFormField label="Tomador">
        <UInput v-model="filters.taker_cnpj" size="sm" class="w-full font-mono" />
      </UFormField>
      <UFormField label="Competência">
        <UInput v-model="filters.competence" size="sm" class="w-full" placeholder="AAAA-MM" />
      </UFormField>
      <div class="flex items-end gap-2 sm:col-span-2">
        <UButton type="submit" color="primary" size="sm" label="Aplicar filtros" />
        <UButton
          type="button"
          color="neutral"
          variant="ghost"
          size="sm"
          label="Limpar"
          @click="emit('reset')"
        />
      </div>
    </form>
  </div>
</template>
