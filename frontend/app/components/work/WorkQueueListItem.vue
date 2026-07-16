<script setup lang="ts">
/**
 * Linha da fila — anatomia de InboxList.vue (borda esquerda, densidade, foco).
 */
import type { OperationalTaskSummary } from '~/types/work'
import {
  formatDueDate,
  highestRiskColor,
  queueBucketLabel,
  taskStatusLabel,
  workRiskLabel
} from '~/utils/work-labels'

const props = defineProps<{
  item: OperationalTaskSummary
  selected: boolean
}>()

const emit = defineEmits<{
  select: [id: number]
}>()

const rootEl = ref<HTMLElement | null>(null)

defineExpose({ el: rootEl })

function onClick() {
  emit('select', props.item.id)
}
</script>

<template>
  <div
    ref="rootEl"
    role="option"
    :aria-selected="selected"
    tabindex="-1"
    class="p-4 sm:px-6 text-sm cursor-pointer border-l-2 transition-colors"
    :class="[
      selected
        ? 'border-primary bg-primary/10'
        : 'border-transparent hover:border-primary hover:bg-primary/5'
    ]"
    data-testid="work-queue-item"
    @click="onClick"
  >
    <div class="flex items-center justify-between gap-2">
      <div class="flex min-w-0 items-center gap-2 font-medium text-highlighted">
        <span class="truncate">{{ item.title }}</span>
        <UChip v-if="item.is_critical" color="warning" size="sm" />
      </div>
      <span class="shrink-0 text-xs text-muted">
        {{ formatDueDate(item.effective_due_date || item.due_date) }}
      </span>
    </div>
    <p class="mt-0.5 truncate text-toned">
      {{ item.process?.client?.name || 'Sem cliente' }}
      <span v-if="item.process?.title"> · {{ item.process.title }}</span>
    </p>
    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted">
      <UBadge
        size="sm"
        variant="subtle"
        :color="highestRiskColor(item.risks)"
        :label="item.risks?.[0] ? workRiskLabel(item.risks[0]) : taskStatusLabel(item.status)"
      />
      <span v-if="item.department">{{ item.department.name }}</span>
      <span v-if="item.assignee">· {{ item.assignee.name }}</span>
      <span v-else class="text-warning">· Sem responsável</span>
      <span v-if="item.bucket" class="sr-only">{{ queueBucketLabel(item.bucket) }}</span>
    </div>
  </div>
</template>
