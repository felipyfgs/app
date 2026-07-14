<script setup lang="ts">
import { formatTimeAgo } from '@vueuse/core'
import type { AppNotification } from '~/types/api'

const { isNotificationsSlideoverOpen } = useDashboard()
const api = useApi()
const notifications = ref<AppNotification[]>([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const [summary, syncs] = await Promise.all([
      api.operations.summary(),
      api.sync.history({ limit: 10 })
    ])
    const generatedAt = summary.data.generated_at || new Date().toISOString()
    const operational: AppNotification[] = []

    if (summary.data.sync_blocked > 0) {
      operational.push({
        id: 'sync-blocked',
        title: 'Estabelecimentos bloqueados',
        body: `${summary.data.sync_blocked} cursor(es) exigem intervenção.`,
        date: generatedAt,
        unread: true,
        to: '/syncs',
        color: 'error'
      })
    }
    if (summary.data.credentials_expiring_30d > 0) {
      operational.push({
        id: 'credentials-expiring',
        title: 'Certificados a vencer',
        body: `${summary.data.credentials_expiring_30d} certificado(s) vencem em até 30 dias.`,
        date: generatedAt,
        unread: true,
        to: '/clients',
        color: 'warning'
      })
    }

    notifications.value = [
      ...operational,
      ...syncs.data
        .filter(sync => sync.status === 'FAILED')
        .map(sync => ({
          id: `sync-${sync.id}`,
          title: `Sincronização #${sync.id} falhou`,
          body: sync.error_message || 'Falha sanitizada na sincronização ADN.',
          date: sync.finished_at || sync.started_at || sync.created_at || generatedAt,
          unread: true,
          to: '/syncs',
          color: 'error' as const
        }))
    ]
  } catch {
    notifications.value = []
  } finally {
    loading.value = false
  }
}

watch(isNotificationsSlideoverOpen, (open) => {
  if (open) {
    load()
  }
})
</script>

<template>
  <USlideover v-model:open="isNotificationsSlideoverOpen" title="Alertas operacionais">
    <template #body>
      <div v-if="loading" class="space-y-3">
        <USkeleton v-for="index in 3" :key="index" class="h-16 w-full" />
      </div>
      <UEmpty
        v-else-if="!notifications.length"
        icon="i-lucide-circle-check"
        title="Nenhum alerta recente"
        description="A operação não possui ocorrências críticas no momento."
      />
      <template v-else>
        <NuxtLink
          v-for="notification in notifications"
          :key="notification.id"
          :to="notification.to || '/syncs'"
          class="px-3 py-2.5 rounded-md hover:bg-elevated/50 flex items-center gap-3 relative -mx-3 first:-mt-3 last:-mb-3"
        >
          <UChip :color="notification.color || 'error'" :show="!!notification.unread" inset>
            <div class="size-10 rounded-full bg-error/10 flex items-center justify-center">
              <UIcon name="i-lucide-triangle-alert" class="size-5 text-error" />
            </div>
          </UChip>

          <div class="text-sm flex-1">
            <p class="flex items-center justify-between gap-2">
              <span class="text-highlighted font-medium">{{ notification.title }}</span>
              <time
                :datetime="notification.date"
                class="text-muted text-xs shrink-0"
                v-text="formatTimeAgo(new Date(notification.date))"
              />
            </p>
            <p class="text-dimmed">
              {{ notification.body }}
            </p>
          </div>
        </NuxtLink>
      </template>
    </template>
  </USlideover>
</template>
