<script setup lang="ts">
/**
 * Célula de lista de monitores: saldo, último snapshot, próxima execução, bloqueio.
 */
import {
  commercialBalanceLabel,
  commercialBlockLabel,
  lastSnapshotLabel,
  nextRunLabel
} from '~/utils/monitor-commercial'

const props = defineProps<{
  remaining?: number | null
  limit?: number | null
  used?: number | null
  blockReason?: string | null
  blockMessage?: string | null
  lastSnapshotAt?: string | null
  nextScheduledAt?: string | null
  isRecent?: boolean
}>()

const balance = computed(() => commercialBalanceLabel({
  remaining: props.remaining,
  limit: props.limit,
  used: props.used,
  block_reason: props.blockReason
}))

const block = computed(() =>
  props.blockMessage || commercialBlockLabel(props.blockReason)
)
</script>

<template>
  <div
    class="space-y-0.5 text-xs"
    data-testid="monitor-commercial-meta"
  >
    <p>
      <span class="text-muted">Saldo</span>
      <span class="ms-1 font-medium text-highlighted">{{ balance }}</span>
    </p>
    <p>
      <span class="text-muted">Snapshot</span>
      <span class="ms-1 text-highlighted">{{ lastSnapshotLabel(lastSnapshotAt) }}</span>
      <UBadge
        v-if="isRecent"
        class="ms-1"
        color="info"
        variant="subtle"
        size="sm"
      >
        recente
      </UBadge>
    </p>
    <p>
      <span class="text-muted">Próxima</span>
      <span class="ms-1 text-highlighted">{{ nextRunLabel(nextScheduledAt) }}</span>
    </p>
    <p
      v-if="block"
      class="text-warning"
      role="status"
    >
      {{ block }}
    </p>
  </div>
</template>
