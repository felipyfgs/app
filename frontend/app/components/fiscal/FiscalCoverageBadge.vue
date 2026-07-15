<script setup lang="ts">
/**
 * Badge de cobertura (FULL / PARTIAL / UNSUPPORTED…) — texto + ícone, não só cor.
 */
import { coverageMeta } from '~/utils/fiscal-status'

const props = withDefaults(defineProps<{
  coverage?: string | null
  size?: 'xs' | 'sm' | 'md'
  showDescription?: boolean
}>(), {
  size: 'sm',
  showDescription: false
})

const meta = computed(() => coverageMeta(props.coverage))
const aria = computed(() =>
  [meta.value.label, meta.value.description].filter(Boolean).join('. ')
)
</script>

<template>
  <span
    class="inline-flex max-w-full items-center gap-1.5"
    :aria-label="aria"
    data-testid="fiscal-coverage-badge"
  >
    <UBadge
      :color="meta.color"
      variant="subtle"
      :size="size"
      class="font-normal"
    >
      <UIcon
        :name="meta.icon"
        class="size-3.5 shrink-0"
        aria-hidden="true"
      />
      <span>{{ meta.label }}</span>
    </UBadge>
    <span
      v-if="showDescription"
      class="truncate text-xs text-muted"
    >
      {{ meta.description }}
    </span>
  </span>
</template>
