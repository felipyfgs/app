<script setup lang="ts">
import type { PgdasdRbt12Summary } from '~/types/fiscal-modules'

const props = defineProps<{
  rbt12?: PgdasdRbt12Summary | null
}>()

const available = computed(() => {
  const parsed = props.rbt12?.status === 'PARSED' || props.rbt12?.status === 'RESOLVED'
  return parsed && (props.rbt12?.total_cents != null || Boolean(props.rbt12?.rbt12_value))
})
const value = computed(() => available.value
  ? props.rbt12?.total_cents != null
    ? formatAmountCents(props.rbt12.total_cents)
    : formatCurrency(props.rbt12?.rbt12_value)
  : '—'
)
</script>

<template>
  <UTooltip :text="pgdasdRbt12Tooltip(rbt12)">
    <span
      class="inline-flex min-w-24 items-center gap-1 tabular-nums"
      :class="available ? 'font-medium text-highlighted' : 'text-muted'"
      :aria-label="available ? `RBT12 ${value}` : 'RBT12 indisponível'"
      data-testid="pgdasd-rbt12-value"
    >
      <UIcon
        :name="available ? 'i-lucide-chart-no-axes-column-increasing' : 'i-lucide-circle-help'"
        class="size-4 shrink-0"
      />
      {{ value }}
    </span>
  </UTooltip>
</template>
