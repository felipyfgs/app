<script setup lang="ts">
import type { OperationsSummary } from '~/types/api'

const props = defineProps<{
  summary: OperationsSummary | null
  loading?: boolean
}>()

const stats = computed(() => [{
  title: 'Cursores bloqueados',
  icon: 'i-lucide-triangle-alert',
  value: props.summary?.sync_blocked ?? 0,
  to: '/syncs',
  critical: true
}, {
  title: 'Falhas (24h)',
  icon: 'i-lucide-circle-x',
  value: props.summary?.sync_failures_24h ?? 0,
  to: '/syncs',
  critical: true
}, {
  title: 'Sincronizações vencidas',
  icon: 'i-lucide-clock-alert',
  value: props.summary?.sync_due ?? 0,
  to: '/syncs',
  critical: false
}, {
  title: 'Certificados a vencer',
  icon: 'i-lucide-badge-alert',
  value: props.summary?.credentials_expiring_30d ?? 0,
  to: '/clients',
  critical: false
}])
</script>

<template>
  <UPageGrid data-testid="home-stats" class="lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-px">
    <UPageCard
      v-for="(stat, index) in stats"
      :key="index"
      :icon="stat.icon"
      :title="stat.title"
      :to="stat.to"
      variant="subtle"
      :ui="{
        container: 'gap-y-1.5',
        wrapper: 'items-start',
        leading: 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col',
        title: 'font-normal text-muted text-xs uppercase'
      }"
      class="lg:rounded-none first:rounded-l-lg last:rounded-r-lg hover:z-1"
    >
      <div class="flex items-center gap-2">
        <span class="text-2xl font-semibold text-highlighted">
          {{ loading && !summary ? '…' : stat.value }}
        </span>

        <UIcon
          v-if="stat.critical && stat.value > 0"
          name="i-lucide-triangle-alert"
          class="size-4 shrink-0 text-error"
          aria-label="Requer atenção"
        />
      </div>
    </UPageCard>
  </UPageGrid>
</template>
