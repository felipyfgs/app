<script setup lang="ts">
import type { PgdasdRbt12Summary } from '~/types/fiscal-modules'
import { TABLE_CELL_BADGE_CLASS, TABLE_CELL_BADGE_UI } from '~/utils/table-ui'

const props = defineProps<{
  rbt12?: PgdasdRbt12Summary | null
}>()

const available = computed(() => {
  const parsed = props.rbt12?.status === 'PARSED'
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
    <div class="block w-full min-w-0">
      <UBadge
        :label="value"
        :icon="available ? 'i-lucide-chart-no-axes-column-increasing' : 'i-lucide-circle-help'"
        :color="available ? 'success' : 'neutral'"
        :variant="available ? 'outline' : 'subtle'"
        size="md"
        :class="TABLE_CELL_BADGE_CLASS"
        :ui="TABLE_CELL_BADGE_UI"
        :aria-label="available ? `RBT12 ${value}` : 'RBT12 indisponível'"
        data-testid="pgdasd-rbt12-value"
      />
    </div>
  </UTooltip>
</template>
