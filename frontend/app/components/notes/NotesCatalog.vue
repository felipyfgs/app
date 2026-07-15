<script setup lang="ts">
/**
 * Catálogo de documentos fiscais — UTable no padrão customers.vue do template.
 * Fonte: .reference/nuxt-dashboard-template/app/pages/customers.vue (:ui + footer)
 */
import type { TableColumn } from '@nuxt/ui'
import type { NfseNote } from '~/types/api'
import { documentKindLabel } from '~/utils/documentKinds'
import { NOTES_TABLE_UI } from '~/utils/notes-filters'

const props = defineProps<{
  notes: NfseNote[]
  loading?: boolean
  error?: string | null
  selectedAccessKey?: string | null
  nextCursor?: string | null
  selectable?: boolean
  selectedKeys?: string[]
}>()

const emit = defineEmits<{
  'select': [note: NfseNote]
  'loadMore': []
  'retry': []
  'update:selectedKeys': [keys: string[]]
}>()

const UCheckbox = resolveComponent('UCheckbox')

const selectedSet = computed({
  get: () => new Set(props.selectedKeys || []),
  set: (set: Set<string>) => emit('update:selectedKeys', [...set])
})

function shortKey(accessKey: string) {
  if (accessKey.length <= 18) return accessKey
  return `${accessKey.slice(0, 8)}…${accessKey.slice(-6)}`
}

function noteTitle(note: NfseNote) {
  const kind = documentKindLabel(note.kind || 'NFSE')
  if (note.number) return `${kind} nº ${note.number}`
  return shortKey(note.access_key)
}

function counterpartyName(note: NfseNote): string | null {
  if (note.fiscal_role === 'ISSUER') return note.taker_name || null
  if (note.fiscal_role === 'TAKER' || note.fiscal_role === 'INTERMEDIARY') return note.issuer_name || null
  return note.issuer_name || note.taker_name || null
}

function counterpartyCnpj(note: NfseNote): string | null {
  if (note.fiscal_role === 'ISSUER') return note.taker_cnpj || null
  if (note.fiscal_role === 'TAKER' || note.fiscal_role === 'INTERMEDIARY') return note.issuer_cnpj || null
  return note.issuer_cnpj || note.taker_cnpj || null
}

function toggleKey(key: string, on: boolean) {
  const next = new Set(selectedSet.value)
  if (on) next.add(key)
  else next.delete(key)
  selectedSet.value = next
}

function toggleAllPage(on: boolean) {
  const next = new Set(selectedSet.value)
  for (const n of props.notes) {
    if (on) next.add(n.access_key)
    else next.delete(n.access_key)
  }
  selectedSet.value = next
}

const allPageSelected = computed(() =>
  props.notes.length > 0 && props.notes.every(n => selectedSet.value.has(n.access_key))
)
const somePageSelected = computed(() =>
  props.notes.some(n => selectedSet.value.has(n.access_key)) && !allPageSelected.value
)

const selectedCount = computed(() => selectedSet.value.size)

const columns = computed<TableColumn<NfseNote>[]>(() => {
  const cols: TableColumn<NfseNote>[] = []
  if (props.selectable) {
    cols.push({
      id: 'select',
      header: () => h(UCheckbox, {
        'modelValue': allPageSelected.value ? true : (somePageSelected.value ? 'indeterminate' : false),
        'ariaLabel': 'Selecionar notas carregadas',
        'onUpdate:modelValue': (v: boolean | 'indeterminate') => toggleAllPage(!!v)
      }),
      meta: { class: { th: 'w-10', td: 'w-10' } }
    })
  }
  cols.push(
    {
      id: 'kind',
      accessorFn: row => row.kind_label || documentKindLabel(row.kind || 'NFSE'),
      header: 'Tipo',
      meta: { class: { th: 'w-24', td: 'w-24' } }
    },
    {
      id: 'direction',
      accessorFn: row => row.direction_label || row.direction || '—',
      header: 'Direção',
      meta: { class: { th: 'hidden sm:table-cell w-24', td: 'hidden sm:table-cell w-24' } }
    },
    {
      id: 'number',
      accessorFn: row => row.number || row.access_key,
      header: 'Número',
      meta: { class: { th: 'min-w-32', td: 'min-w-32' } }
    },
    {
      id: 'fiscal_role',
      accessorKey: 'fiscal_role',
      header: 'Papel',
      meta: { class: { th: 'hidden lg:table-cell w-28', td: 'hidden lg:table-cell w-28' } }
    },
    {
      id: 'counterparty',
      header: 'Contraparte',
      meta: { class: { th: 'min-w-44', td: 'min-w-44' } }
    },
    {
      accessorKey: 'competence',
      header: 'Competência',
      meta: { class: { th: 'hidden md:table-cell w-28', td: 'hidden md:table-cell w-28' } }
    },
    {
      accessorKey: 'service_amount',
      header: 'Valor',
      meta: { class: { th: 'w-28', td: 'w-28' } }
    },
    {
      accessorKey: 'status',
      header: 'Situação',
      meta: { class: { th: 'w-28', td: 'w-28' } }
    },
    {
      id: 'actions',
      header: '',
      meta: { class: { th: 'w-12', td: 'w-12' } }
    }
  )
  return cols
})

defineShortcuts({
  arrowdown: () => {
    if (!props.notes.length) return
    const index = props.notes.findIndex(n => n.access_key === props.selectedAccessKey)
    if (index === -1) emit('select', props.notes[0]!)
    else if (index < props.notes.length - 1) emit('select', props.notes[index + 1]!)
  },
  arrowup: () => {
    if (!props.notes.length) return
    const index = props.notes.findIndex(n => n.access_key === props.selectedAccessKey)
    if (index === -1) emit('select', props.notes[props.notes.length - 1]!)
    else if (index > 0) emit('select', props.notes[index - 1]!)
  }
})
</script>

<template>
  <div data-testid="data-table" class="flex min-h-0 w-full flex-col gap-4">
    <UAlert
      v-if="error"
      :color="notes.length ? 'warning' : 'error'"
      icon="i-lucide-wifi-off"
      :title="notes.length ? 'Falha ao atualizar documentos' : 'Não foi possível carregar documentos'"
      :description="error"
      class="shrink-0"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => emit('retry') }]"
    />

    <!-- :ui idêntico a customers.vue do template -->
    <UTable
      :data="notes"
      :columns="columns"
      :loading="loading && !notes.length"
      class="shrink-0"
      :ui="NOTES_TABLE_UI"
    >
      <template v-if="selectable" #select-cell="{ row }">
        <UCheckbox
          :model-value="selectedSet.has(row.original.access_key)"
          :aria-label="`Selecionar ${noteTitle(row.original)}`"
          @update:model-value="(v: boolean | 'indeterminate') => toggleKey(row.original.access_key, !!v)"
          @click.stop
        />
      </template>

      <template #kind-cell="{ row }">
        <UBadge color="neutral" variant="subtle" size="sm">
          {{ row.original.kind_label || documentKindLabel(row.original.kind || 'NFSE') }}
        </UBadge>
      </template>

      <template #direction-cell="{ row }">
        <UBadge
          :color="row.original.direction === 'OUT' ? 'primary' : row.original.direction === 'IN' ? 'info' : 'neutral'"
          variant="subtle"
          size="sm"
        >
          {{ row.original.direction_label || row.original.direction || '—' }}
        </UBadge>
      </template>

      <template #number-cell="{ row }">
        <button
          type="button"
          class="block w-full truncate text-left font-medium text-highlighted hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          :class="selectedAccessKey === row.original.access_key ? 'text-primary' : ''"
          :aria-label="row.original.access_key"
          :aria-current="selectedAccessKey === row.original.access_key ? 'true' : undefined"
          @click="emit('select', row.original)"
        >
          {{ noteTitle(row.original) }}
        </button>
        <p class="mt-0.5 text-xs text-muted sm:hidden">
          {{ statusLabel(row.original.fiscal_role) }}
        </p>
      </template>

      <template #fiscal_role-cell="{ row }">
        <UBadge
          color="neutral"
          variant="subtle"
          size="sm"
          class="capitalize"
        >
          {{ statusLabel(row.original.fiscal_role) }}
        </UBadge>
      </template>

      <template #counterparty-cell="{ row }">
        <div class="min-w-0">
          <p class="truncate font-medium text-highlighted">
            {{ counterpartyName(row.original) || formatCnpj(counterpartyCnpj(row.original)) || '—' }}
          </p>
          <p
            v-if="counterpartyName(row.original) && counterpartyCnpj(row.original)"
            class="truncate font-mono text-sm text-muted"
          >
            {{ formatCnpj(counterpartyCnpj(row.original)) }}
          </p>
        </div>
      </template>

      <template #competence-cell="{ row }">
        <span class="tabular-nums">{{ row.original.competence || '—' }}</span>
      </template>

      <template #service_amount-cell="{ row }">
        <div class="text-right font-medium tabular-nums text-highlighted">
          {{ formatCurrency(row.original.service_amount) }}
        </div>
      </template>

      <template #status-cell="{ row }">
        <AppStatusBadge
          :status="row.original.status"
          :label="row.original.status_label"
        />
      </template>

      <template #actions-cell="{ row }">
        <div class="text-right">
          <UButton
            icon="i-lucide-ellipsis-vertical"
            color="neutral"
            variant="ghost"
            square
            class="ml-auto"
            :aria-label="`Abrir ${noteTitle(row.original)}`"
            @click="emit('select', row.original)"
          />
        </div>
      </template>
    </UTable>

    <!-- Footer customers.vue: contagem esquerda · ação direita -->
    <div class="mt-auto flex items-center justify-between gap-3 border-t border-default pt-4">
      <div class="text-sm text-muted">
        <template v-if="selectable">
          {{ selectedCount }} de {{ notes.length }} linha(s) selecionada(s)
        </template>
        <template v-else>
          {{ notes.length }} nota(s) carregada(s)
        </template>
      </div>
      <div class="flex items-center gap-1.5">
        <UButton
          v-if="nextCursor"
          :loading="loading"
          color="neutral"
          variant="outline"
          size="sm"
          label="Carregar mais"
          @click="emit('loadMore')"
        />
      </div>
    </div>

    <UEmpty
      v-if="!loading && !error && !notes.length"
      icon="i-lucide-file-search"
      title="Nenhuma nota encontrada"
      description="Revise os filtros ou aguarde a próxima sincronização do ADN."
    />
  </div>
</template>
