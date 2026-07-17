<script setup lang="ts">
/**
 * Busca server-side de cliente por razão social, nome fantasia ou CNPJ.
 * Single: UInputMenu (um id).
 * Multiple: lista embutida com checkboxes — abre com clientes, não fecha o popover pai.
 */
import type { Client } from '~/types/api'
import { formatCnpj, normalizeCnpj } from '~/utils/format'

type ClientItem = { label: string, value: number, client: Client }

const props = withDefaults(defineProps<{
  /** Single: number | null. Multiple: number[]. */
  modelValue?: number | number[] | null
  multiple?: boolean
  searchMode?: 'select' | 'query'
  placeholder?: string
  disabled?: boolean
  class?: string
  /** Multi: quantos carregar na abertura (sem digitar). */
  initialPerPage?: number
}>(), {
  modelValue: null,
  multiple: false,
  searchMode: 'select',
  placeholder: 'Cliente (nome ou CNPJ)',
  initialPerPage: 40
})

const emit = defineEmits<{
  'update:modelValue': [value: number | number[] | null]
  'update:query': [value: string]
  'select': [client: Client | null]
  'select-many': [clients: Client[]]
}>()

const api = useApi()

const searchTerm = ref('')
const items = ref<ClientItem[]>([])
const selectedCache = ref<Map<number, ClientItem>>(new Map())
const loading = ref(false)
let debounceTimer: ReturnType<typeof setTimeout> | null = null
let requestGen = 0

function toItem(c: Client): ClientItem {
  const cnpj = c.cnpj || c.root_cnpj
  const name = c.display_name || c.legal_name || c.name
  const cnpjLabel = cnpj ? formatCnpj(cnpj) : ''
  return {
    label: cnpjLabel ? `${name} · ${cnpjLabel}` : String(name || `Cliente #${c.id}`),
    value: c.id,
    client: c
  }
}

const selectedIds = computed(() => {
  if (!props.multiple) return [] as number[]
  const v = props.modelValue
  if (Array.isArray(v)) return v.filter(id => Number.isFinite(id) && id >= 1).map(id => Math.floor(id))
  if (typeof v === 'number' && v >= 1) return [v]
  return []
})

const selectedSingle = computed<number | undefined>({
  get: () => {
    if (props.multiple) return undefined
    const v = props.modelValue
    return typeof v === 'number' && v >= 1 ? v : undefined
  },
  set: (v: number | undefined) => {
    emit('update:modelValue', v ?? null)
    const found = items.value.find(i => i.value === v) || selectedCache.value.get(v ?? 0)
    emit('select', found?.client ?? null)
  }
})

const listItems = computed(() => {
  const byId = new Map(items.value.map(item => [item.value, item]))
  // Selecionados no topo se não estiverem na página atual
  for (const id of selectedIds.value) {
    const cached = selectedCache.value.get(id)
    if (cached && !byId.has(id)) byId.set(id, cached)
  }
  const list = [...byId.values()]
  // Ordena: selecionados primeiro, depois label
  list.sort((a, b) => {
    const sa = selectedIds.value.includes(a.value) ? 0 : 1
    const sb = selectedIds.value.includes(b.value) ? 0 : 1
    if (sa !== sb) return sa - sb
    return a.label.localeCompare(b.label, 'pt-BR')
  })
  return list
})

const selectedSummary = computed(() => {
  return selectedIds.value.map((id) => {
    const item = selectedCache.value.get(id) || listItems.value.find(i => i.value === id)
    return item?.label || `Cliente #${id}`
  })
})

function emitMulti(ids: number[]) {
  const unique = [...new Set(ids.map(id => Math.floor(Number(id))).filter(id => id >= 1))]
  emit('update:modelValue', unique)
  const clients = unique
    .map(id => selectedCache.value.get(id)?.client || listItems.value.find(i => i.value === id)?.client)
    .filter((c): c is Client => c != null)
  emit('select-many', clients)
  emit('select', clients[clients.length - 1] ?? null)
}

function toggleClient(item: ClientItem) {
  if (props.disabled) return
  selectedCache.value.set(item.value, item)
  const set = new Set(selectedIds.value)
  if (set.has(item.value)) set.delete(item.value)
  else set.add(item.value)
  emitMulti([...set])
}

function removeSelected(id: number) {
  if (props.disabled) return
  emitMulti(selectedIds.value.filter(x => x !== id))
}

function isSelected(id: number) {
  return selectedIds.value.includes(id)
}

async function fetchClients(term: string, opts?: { initial?: boolean }) {
  const gen = ++requestGen
  const raw = term.trim()
  // Abertura: lista sem q. Busca: a partir de 1 caractere (antes exigia 2).
  if (!opts?.initial && raw.length === 0) {
    // Volta à lista inicial
    return fetchClients('', { initial: true })
  }

  loading.value = true
  try {
    const digits = normalizeCnpj(raw)
    const q = digits.length >= 8 && /^[A-Z0-9]+$/i.test(digits) && digits.length <= 14
      ? digits
      : raw

    const res = await api.clients.list({
      ...(q ? { q } : {}),
      per_page: opts?.initial ? props.initialPerPage : 30,
      page: 1
    })
    if (gen !== requestGen) return
    items.value = (res.data || []).map(toItem)
    for (const item of items.value) {
      if (selectedIds.value.includes(item.value) || selectedSingle.value === item.value) {
        selectedCache.value.set(item.value, item)
      }
    }
  } catch {
    if (gen !== requestGen) return
    items.value = []
  } finally {
    if (gen === requestGen) loading.value = false
  }
}

function onSearchChange(term: string) {
  searchTerm.value = term
  emit('update:query', term)
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    void fetchClients(term, { initial: term.trim().length === 0 })
  }, 220)
}

function onQueryInput(value: string | number) {
  const term = String(value ?? '')
  searchTerm.value = term
  emit('update:query', term)
}

// Carrega lista ao montar (multi e single select)
onMounted(() => {
  if (props.searchMode === 'select') {
    void fetchClients('', { initial: true })
  }
})

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<template>
  <div
    data-testid="fiscal-client-picker"
    :class="props.class || 'w-56 sm:w-72'"
  >
    <!-- Multi: lista embutida (não portala — não fecha o UPopover do filtro). -->
    <div
      v-if="searchMode === 'select' && multiple"
      class="flex min-w-0 flex-col gap-2"
      data-testid="fiscal-client-picker-multi"
    >
      <div
        v-if="selectedSummary.length"
        class="flex min-w-0 flex-wrap gap-1"
        data-testid="fiscal-client-picker-selected"
      >
        <UBadge
          v-for="(label, idx) in selectedSummary"
          :key="selectedIds[idx]"
          color="neutral"
          variant="subtle"
          class="max-w-full"
        >
          <span class="truncate">{{ label }}</span>
          <UButton
            type="button"
            color="neutral"
            variant="link"
            size="xs"
            icon="i-lucide-x"
            square
            class="ms-0.5"
            :aria-label="`Remover ${label}`"
            :disabled="disabled"
            @click.stop="removeSelected(selectedIds[idx]!)"
          />
        </UBadge>
      </div>

      <UInput
        :model-value="searchTerm"
        :placeholder="placeholder || 'Filtrar por nome ou CNPJ'"
        icon="i-lucide-search"
        :loading="loading"
        :disabled="disabled"
        class="w-full min-w-0"
        aria-label="Filtrar clientes"
        data-testid="fiscal-client-picker-search"
        @update:model-value="onSearchChange(String($event ?? ''))"
      />

      <div
        class="max-h-52 min-h-24 overflow-y-auto rounded-md border border-default"
        role="listbox"
        aria-multiselectable="true"
        aria-label="Lista de clientes"
        data-testid="fiscal-client-picker-list"
      >
        <p
          v-if="loading && !listItems.length"
          class="px-3 py-4 text-center text-sm text-muted"
        >
          Carregando clientes…
        </p>
        <p
          v-else-if="!listItems.length"
          class="px-3 py-4 text-center text-sm text-muted"
        >
          Nenhum cliente encontrado
        </p>
        <button
          v-for="item in listItems"
          :key="item.value"
          type="button"
          role="option"
          :aria-selected="isSelected(item.value)"
          class="flex w-full min-w-0 items-center gap-2 border-b border-default px-2.5 py-2 text-left text-sm last:border-b-0 hover:bg-elevated/80 focus-visible:bg-elevated focus-visible:outline-none"
          :class="isSelected(item.value) ? 'bg-primary/5' : ''"
          :disabled="disabled"
          data-testid="fiscal-client-picker-option"
          @click.stop.prevent="toggleClient(item)"
        >
          <UCheckbox
            :model-value="isSelected(item.value)"
            :disabled="disabled"
            tabindex="-1"
            class="pointer-events-none shrink-0"
          />
          <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
        </button>
      </div>

      <p class="text-xs text-muted">
        {{ selectedIds.length
          ? `${selectedIds.length} selecionado(s) — clique para marcar/desmarcar`
          : 'Selecione um ou mais clientes na lista' }}
      </p>
    </div>

    <!-- Single: menu compacto (ainda carrega lista inicial). -->
    <UInputMenu
      v-else-if="searchMode === 'select'"
      v-model="selectedSingle"
      v-model:search-term="searchTerm"
      :items="listItems"
      value-key="value"
      label-key="label"
      :loading="loading"
      :disabled="disabled"
      :placeholder="placeholder"
      icon="i-lucide-user-search"
      trailing-icon="i-lucide-chevrons-up-down"
      ignore-filter
      class="w-full"
      aria-label="Selecionar cliente"
      :ui="{ content: 'z-[100]' }"
      @update:search-term="onSearchChange"
      @update:open="(open: boolean) => { if (open && !items.length) void fetchClients('', { initial: true }) }"
    >
      <template #empty>
        <span class="text-sm text-muted">
          {{ loading ? 'Carregando…' : 'Nenhum cliente encontrado' }}
        </span>
      </template>
    </UInputMenu>

    <UInput
      v-else
      :model-value="searchTerm"
      :placeholder="placeholder"
      icon="i-lucide-search"
      :disabled="disabled"
      class="w-full"
      aria-label="Buscar cliente por nome ou CNPJ"
      data-testid="fiscal-client-query"
      @update:model-value="onQueryInput"
      @keyup.enter="emit('update:query', searchTerm)"
    />
  </div>
</template>
