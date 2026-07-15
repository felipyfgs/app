<script setup lang="ts">
/**
 * Célula de identidade do cliente na carteira: razão social + CNPJ mascarado.
 */
import { truncateText } from '~/utils/format'

const props = withDefaults(defineProps<{
  name?: string | null
  legalName?: string | null
  displayName?: string | null
  cnpjMasked?: string | null
  rootCnpjMasked?: string | null
  clientId?: number | null
  to?: string | null
  maxName?: number
}>(), {
  maxName: 40
})

const primaryName = computed(() => {
  const n = props.displayName || props.name || props.legalName || ''
  return String(n).trim() || (props.clientId ? `Cliente #${props.clientId}` : '—')
})

const secondaryName = computed(() => {
  const legal = String(props.legalName || '').trim()
  if (!legal) return null
  if (legal === primaryName.value) return null
  return legal
})

const cnpj = computed(() =>
  props.cnpjMasked || props.rootCnpjMasked || null
)

const href = computed(() => {
  if (props.to) return props.to
  if (props.clientId) return `/monitoring/clients/${props.clientId}`
  return null
})
</script>

<template>
  <div
    class="flex min-w-0 flex-col gap-0.5"
    data-testid="fiscal-client-cell"
  >
    <NuxtLink
      v-if="href"
      :to="href"
      class="min-w-0 truncate font-medium text-highlighted hover:underline focus-visible:underline"
      :title="primaryName"
    >
      {{ truncateText(primaryName, maxName) || primaryName }}
    </NuxtLink>
    <span
      v-else
      class="min-w-0 truncate font-medium text-highlighted"
      :title="primaryName"
    >
      {{ truncateText(primaryName, maxName) || primaryName }}
    </span>
    <span
      v-if="secondaryName"
      class="min-w-0 truncate text-xs text-muted"
      :title="secondaryName"
    >
      {{ truncateText(secondaryName, maxName) }}
    </span>
    <span
      v-if="cnpj"
      class="font-mono text-xs tabular-nums text-muted"
      :title="cnpj"
      data-testid="fiscal-client-cnpj"
    >
      {{ cnpj }}
    </span>
  </div>
</template>
