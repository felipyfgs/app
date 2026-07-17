<script setup lang="ts">
/**
 * Busca server-side de cliente por razão social, nome fantasia ou CNPJ.
 * Single: um id; multiple: vários ids (carteira / filtros estruturados).
 */
import type { Client } from '~/types/api'
import { formatCnpj, normalizeCnpj } from '~/utils/format'

const props = withDefaults(defineProps<{
  /** Single: number | null. Multiple: number[]. */
  modelValue?: number | number[] | null
  multiple?: boolean
  /** Texto livre opcional (quando o pai controla só o q da carteira). */
  searchMode?: 'select' | 'query'
  placeholder?: string
  disabled?: boolean
  class?: string
}>(), {
  modelValue: null,
  multiple: false,
  searchMode: 'select',
  placeholder: 'Cliente (nome ou CNPJ)'
})

const emit = defineEmits<{
  'update:modelValue': [value: number | number[] | null]
  'update:query': [value: string]
  'select': [client: Client | null]
  /** Multi: emite lista de clientes conhecidos (rótulos). */
  'select-many': [clients: Client[]]
}>()

const api = useApi()

const searchTerm = ref('')
const items = ref<Array<{ label: string, value: number, client: Client }>>([])
/** Mantém itens já escolhidos no multi (fora da última busca). */
const selectedCache = ref<Map<number, { label: string, value: number, client: Client }>>(new Map())
const loading = ref(false)
let debounceTimer: ReturnType<typeof setTimeout> | null = null
let requestGen = 0

function toItem(c: Client) {
  const cnpj = c.cnpj || c.root_cnpj
  const name = c.display_name || c.legal_name || c.name
  const cnpjLabel = cnpj ? formatCnpj(cnpj) : ''
  return {
    label: cnpjLabel ? `${name} · ${cnpjLabel}` : name,
    value: c.id,
    client: c
  }
}

const menuItems = computed(() => {
  if (!props.multiple) return items.value
  const byId = new Map(items.value.map(item => [item.value, item]))
  for (const [id, item] of selectedCache.value) {
    if (!byId.has(id)) byId.set(id, item)
  }
  return [...byId.values()]
})

const selectedSingle = computed<number | undefined>({
  get: () => {
    if (props.multiple) return undefined
    const v = props.modelValue
    return typeof v === 'number' && v >= 1 ? v : undefined
  },
  set: (v: number | undefined) => {
    emit('update:modelValue', v ?? null)
    const found = menuItems.value.find(i => i.value === v)
    emit('select', found?.client ?? null)
  }
})

const selectedMulti = computed<number[]>({
  get: () => {
    if (!props.multiple) return []
    const v = props.modelValue
    if (Array.isArray(v)) return v.filter(id => Number.isFinite(id) && id >= 1)
    if (typeof v === 'number' && v >= 1) return [v]
    return []
  },
  set: (ids: number[]) => {
    const unique = [...new Set(ids.map(id => Math.floor(Number(id))).filter(id => id >= 1))]
    emit('update:modelValue', unique)
    const clients = unique
      .map(id => selectedCache.value.get(id)?.client || menuItems.value.find(i => i.value === id)?.client)
      .filter((c): c is Client => c != null)
    for (const c of clients) {
      const item = toItem(c)
      selectedCache.value.set(item.value, item)
    }
    emit('select-many', clients)
    emit('select', clients[clients.length - 1] ?? null)
  }
})

async function search(term: string) {
  const gen = ++requestGen
  const raw = term.trim()
  if (raw.length < 2) {
    items.value = []
    loading.value = false
    return
  }

  loading.value = true
  try {
    const digits = normalizeCnpj(raw)
    const q = digits.length >= 8 && /^[A-Z0-9]+$/i.test(digits) && digits.length <= 14
      ? digits
      : raw

    const res = await api.clients.list({ q, per_page: 15, page: 1 })
    if (gen !== requestGen) return
    items.value = (res.data || []).map(toItem)
    for (const item of items.value) {
      if (selectedMulti.value.includes(item.value) || selectedSingle.value === item.value) {
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
    void search(term)
  }, 280)
}

function onQueryInput(value: string | number) {
  const term = String(value ?? '')
  searchTerm.value = term
  emit('update:query', term)
}

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<template>
  <div
    data-testid="fiscal-client-picker"
    :class="props.class || 'w-56 sm:w-72'"
  >
    <UInputMenu
      v-if="searchMode === 'select' && multiple"
      v-model="selectedMulti"
      v-model:search-term="searchTerm"
      :items="menuItems"
      value-key="value"
      label-key="label"
      multiple
      :loading="loading"
      :disabled="disabled"
      :placeholder="placeholder || 'Clientes (nome ou CNPJ)'"
      icon="i-lucide-users"
      trailing-icon="i-lucide-chevrons-up-down"
      ignore-filter
      class="w-full"
      aria-label="Selecionar clientes"
      data-testid="fiscal-client-picker-multi"
      @update:search-term="onSearchChange"
    >
      <template #empty>
        <span class="text-sm text-muted">
          {{ searchTerm.length < 2 ? 'Digite ao menos 2 caracteres' : 'Nenhum cliente encontrado' }}
        </span>
      </template>
    </UInputMenu>

    <UInputMenu
      v-else-if="searchMode === 'select'"
      v-model="selectedSingle"
      v-model:search-term="searchTerm"
      :items="menuItems"
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
      @update:search-term="onSearchChange"
    >
      <template #empty>
        <span class="text-sm text-muted">
          {{ searchTerm.length < 2 ? 'Digite ao menos 2 caracteres' : 'Nenhum cliente encontrado' }}
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
