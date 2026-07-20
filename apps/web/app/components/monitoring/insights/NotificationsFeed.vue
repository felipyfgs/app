<script setup lang="ts">
import type { MonitoringInsightsNotification } from '~/types/monitoring-insights'
import { formatDateTime } from '~/utils/format'

const props = defineProps<{
  items: MonitoringInsightsNotification[] | null
  loading?: boolean
  error?: string | null
}>()

const q = ref('')
const filtered = computed(() => {
  const list = props.items ?? []
  const needle = q.value.trim().toLowerCase()
  if (!needle) return list
  return list.filter((item) => {
    const hay = `${item.title} ${item.body ?? ''} ${item.type}`.toLowerCase()
    return hay.includes(needle)
  })
})

function typeIcon(type: string) {
  if (type === 'pending') return 'i-lucide-circle-dashed'
  if (type === 'finding') return 'i-lucide-triangle-alert'
  if (type === 'alert') return 'i-lucide-mail'
  return 'i-lucide-bell'
}
</script>

<template>
  <UPageCard
    variant="subtle"
    data-testid="insights-notifications-card"
    :ui="{ body: 'space-y-3' }"
  >
    <template #header>
      <div>
        <p class="text-xs uppercase text-muted">
          Notificações
        </p>
        <p class="mt-1 text-sm text-muted">
          Pendências, findings e alertas e-CAC.
        </p>
      </div>
    </template>

    <UInput
      v-model="q"
      icon="i-lucide-search"
      size="sm"
      placeholder="Filtrar…"
      :disabled="loading && !items"
    />

    <p
      v-if="error"
      class="text-sm text-error"
    >
      {{ error }}
    </p>
    <div
      v-else-if="loading && !items"
      class="py-6 text-center text-sm text-muted"
    >
      Carregando…
    </div>
    <MonitoringTableEmptyState
      v-else-if="!filtered.length"
      kind="empty"
      title="Sem notificações"
      description="Nada para exibir no momento."
    />
    <ul
      v-else
      class="max-h-72 divide-y divide-default overflow-y-auto"
    >
      <li
        v-for="item in filtered"
        :key="item.id"
        class="flex gap-3 py-2.5"
      >
        <UIcon
          :name="typeIcon(item.type)"
          class="mt-0.5 size-4 shrink-0 text-muted"
        />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-highlighted">
            {{ item.title }}
          </p>
          <p
            v-if="item.body"
            class="truncate text-xs text-muted"
          >
            {{ item.body }}
          </p>
          <p
            v-if="item.occurred_at"
            class="mt-0.5 text-[10px] text-muted"
          >
            {{ formatDateTime(item.occurred_at) }}
          </p>
        </div>
      </li>
    </ul>
  </UPageCard>
</template>
