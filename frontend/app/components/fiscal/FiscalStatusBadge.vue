<script setup lang="ts">
/**
 * Badge acessível de situação fiscal — texto + ícone + cor (15.8).
 * Não depende só de cor: label e aria-label explícitos.
 */
import { fiscalStatusMeta } from '~/utils/fiscal-status'

const props = defineProps<{
  status?: string | null
  /** Rótulo opcional que sobrescreve o catálogo. */
  label?: string | null
  /** Exibe descrição curta (origem/limitação) ao lado. */
  showHint?: boolean
  size?: 'xs' | 'sm' | 'md'
}>()

const meta = computed(() => fiscalStatusMeta(props.status))
const displayLabel = computed(() => props.label || meta.value.label)
const aria = computed(() => {
  const parts = [displayLabel.value, meta.value.description]
  if (props.showHint && meta.value.sourceHint) {
    parts.push(meta.value.sourceHint)
  }
  return parts.filter(Boolean).join('. ')
})
</script>

<template>
  <span
    class="inline-flex max-w-full items-center gap-1.5"
    :aria-label="aria"
    data-testid="fiscal-status-badge"
  >
    <UBadge
      :color="meta.color"
      variant="subtle"
      :size="size || 'sm'"
      class="font-normal"
    >
      <UIcon
        :name="meta.icon"
        class="size-3.5 shrink-0"
        aria-hidden="true"
      />
      <span>{{ displayLabel }}</span>
    </UBadge>
    <span
      v-if="showHint && meta.sourceHint"
      class="truncate text-xs text-muted"
    >
      {{ meta.sourceHint }}
    </span>
  </span>
</template>
