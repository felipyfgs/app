<script setup lang="ts">
/**
 * Busca server-side de cliente por razão social, nome fantasia ou CNPJ.
 * Não exige ID numérico como fluxo principal.
 */
import type { Client } from '~/types/api'
import { formatCnpj, normalizeCnpj } from '~/utils/format'

const props = withDefaults(defineProps<{
  modelValue?: number | null
  /** Texto livre opcional (quando o pai controla só o q da carteira). */
  searchMode?: 'select' | 'query'
  placeholder?: string
  disabled?: boolean
  class?: string
}>(), {
  modelValue: null,
  searchMode: 'select',
  placeholder: 'Cliente (nome ou CNPJ)'
})

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  'update:query': [value: string]
  'select': [client: Client | null]
}>()

const api = useApi()

const searchTerm = ref('')
const items = ref<Array<{ label: string, value: number, client: Client }>>([])
const loading = ref(false)
let debounceTimer: ReturnType<typeof setTimeout> | null = null
let requestGen = 0

const selected = computed<number | undefined>({
  get: () => props.modelValue ?? undefined,
  set: (v: number | undefined) => {
    emit('update:modelValue', v ?? null)
    const found = items.value.find(i => i.value === v)
    emit('select', found?.client ?? null)
  }
})

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
    // Normaliza CNPJ se o termo parecer documento.
    const digits = normalizeCnpj(raw)
    const q = digits.length >= 8 && /^[A-Z0-9]+$/i.test(digits) && digits.length <= 14
      ? digits
      : raw

    const res = await api.clients.list({ q, per_page: 15, page: 1 })
    if (gen !== requestGen) return
    items.value = (res.data || []).map(toItem)
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

// query mode: só emite o texto (carteira usa `q` na API de modules/clients)
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
      v-if="searchMode === 'select'"
      v-model="selected"
      v-model:search-term="searchTerm"
      :items="items"
      value-key="value"
      label-key="label"
      :loading="loading"
      :disabled="disabled"
      :placeholder="placeholder"
      icon="i-lucide-search"
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
