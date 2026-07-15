<script setup lang="ts">
/**
 * Banner persistente “Dados demonstrativos” quando a API indica origem sintética.
 * Não inventa demo a partir de resposta vazia/erro produtiva.
 */
import { dataOriginMeta } from '~/utils/fiscal-status'
import { isSyntheticFiscalOrigin } from '~/types/fiscal-modules'

const props = defineProps<{
  /** Origem vinda da API (overview.data_origin ou linha). */
  origin?: string | null
  /** Override explícito de is_synthetic da API. */
  isSynthetic?: boolean | null
  /** Rótulo sanitizado opcional da API (`source_label`). */
  sourceLabel?: string | null
  compact?: boolean
}>()

const show = computed(() => {
  if (props.isSynthetic === true) return true
  if (props.isSynthetic === false) return false
  return isSyntheticFiscalOrigin(props.origin)
})

const meta = computed(() => dataOriginMeta(props.origin || 'DEMO'))
const title = computed(() => meta.value.banner || 'Dados demonstrativos — sem validade fiscal')
const description = computed(() => {
  if (props.compact) return undefined
  const base = 'Este conteúdo pertence ao dataset de demonstração do escritório demo. Não possui validade fiscal e não substitui consulta oficial.'
  const label = String(props.sourceLabel || '').trim()
  return label ? `${base} Fonte: ${label}.` : base
})
</script>

<template>
  <UAlert
    v-if="show"
    color="warning"
    variant="subtle"
    icon="i-lucide-flask-conical"
    :title="title"
    :description="description"
    class="mb-4"
    data-testid="fiscal-demo-banner"
  />
</template>
