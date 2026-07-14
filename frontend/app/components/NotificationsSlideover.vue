<script setup lang="ts">
import { formatTimeAgo } from '@vueuse/core'
import type { AppNotification } from '~/types/api'

const { isNotificationsSlideoverOpen, me } = useDashboard()
const api = useApi()
const notifications = ref<AppNotification[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const loadState = ref<'idle' | 'loading' | 'success' | 'error'>('idle')

async function load() {
  loading.value = true
  loadState.value = 'loading'
  errorMessage.value = null

  try {
    const results = await Promise.allSettled([
      api.operations.summary(),
      api.sync.history({ limit: 10 })
    ])

    const summaryResult = results[0]
    const syncsResult = results[1]
    const operational: AppNotification[] = []
    let partial = false

    if (summaryResult.status === 'fulfilled') {
      const summary = summaryResult.value.data
      const generatedAt = summary.generated_at || new Date().toISOString()

      if (summary.sync_blocked > 0) {
        operational.push({
          id: 'sync-blocked',
          title: 'Estabelecimentos bloqueados',
          body: `${summary.sync_blocked} cursor(es) exigem intervenção.`,
          date: generatedAt,
          unread: true,
          to: '/syncs',
          color: 'error'
        })
      }
      if (summary.credentials_expiring_30d > 0) {
        operational.push({
          id: 'credentials-expiring',
          title: 'Certificados a vencer',
          body: `${summary.credentials_expiring_30d} certificado(s) vencem em até 30 dias.`,
          date: generatedAt,
          unread: true,
          to: '/clients',
          color: 'warning'
        })
      }
      if (summary.sync_failures_24h > 0) {
        operational.push({
          id: 'sync-failures-24h',
          title: 'Falhas recentes de sincronização',
          body: `${summary.sync_failures_24h} execução(ões) nas últimas 24 horas.`,
          date: generatedAt,
          unread: true,
          to: '/syncs',
          color: 'error'
        })
      }
    } else {
      partial = true
    }

    if (syncsResult.status === 'fulfilled') {
      const generatedAt = new Date().toISOString()
      operational.push(
        ...syncsResult.value.data
          .filter(sync => sync.status === 'FAILED')
          .map(sync => ({
            id: `sync-${sync.id}`,
            title: `Sincronização #${sync.id} falhou`,
            body: sync.error_message || 'Falha sanitizada na sincronização ADN.',
            date: sync.finished_at || sync.started_at || sync.created_at || generatedAt,
            unread: true,
            to: '/syncs' as const,
            color: 'error' as const
          }))
      )
    } else {
      partial = true
    }

    if (summaryResult.status === 'rejected' && syncsResult.status === 'rejected') {
      notifications.value = []
      errorMessage.value = 'Não foi possível carregar os alertas operacionais.'
      loadState.value = 'error'
      return
    }

    notifications.value = operational
    if (partial) {
      errorMessage.value = 'Parte dos alertas não pôde ser carregada.'
      loadState.value = 'error'
    } else {
      errorMessage.value = null
      loadState.value = 'success'
    }
  } catch {
    notifications.value = []
    errorMessage.value = 'Não foi possível carregar os alertas operacionais.'
    loadState.value = 'error'
  } finally {
    loading.value = false
  }
}

function clearNotifications() {
  notifications.value = []
  errorMessage.value = null
  loadState.value = 'idle'
}

watch(isNotificationsSlideoverOpen, (open) => {
  if (open) {
    load()
  }
})

watch(() => me.value?.id, (next, prev) => {
  if (prev !== undefined && next !== prev) {
    clearNotifications()
    if (isNotificationsSlideoverOpen.value) {
      load()
    }
  }
  if (!next) {
    clearNotifications()
  }
})
</script>

<template>
  <USlideover v-model:open="isNotificationsSlideoverOpen" title="Alertas operacionais">
    <template #body>
      <div
        v-if="loading"
        class="space-y-3"
        role="status"
        aria-label="Carregando alertas"
      >
        <USkeleton v-for="index in 3" :key="index" class="h-16 w-full" />
      </div>

      <div v-else-if="loadState === 'error' && !notifications.length" class="space-y-4">
        <UAlert
          color="error"
          icon="i-lucide-circle-x"
          title="Falha ao consultar alertas"
          :description="errorMessage || 'Tente novamente em instantes.'"
        />
        <UButton
          icon="i-lucide-refresh-cw"
          label="Tentar novamente"
          color="neutral"
          variant="subtle"
          @click="load"
        />
      </div>

      <template v-else>
        <UAlert
          v-if="errorMessage && notifications.length"
          color="warning"
          icon="i-lucide-triangle-alert"
          class="mb-4"
          title="Carga parcial"
          :description="errorMessage"
          :actions="[{
            label: 'Tentar novamente',
            color: 'neutral',
            variant: 'subtle',
            onClick: load
          }]"
        />

        <UEmpty
          v-if="!notifications.length"
          icon="i-lucide-circle-check"
          title="Nenhum alerta recente"
          description="A operação não possui ocorrências críticas no momento."
        />

        <NuxtLink
          v-for="notification in notifications"
          :key="notification.id"
          :to="notification.to || '/syncs'"
          class="relative -mx-3 flex items-center gap-3 rounded-md px-3 py-2.5 first:-mt-3 last:-mb-3 hover:bg-elevated/50"
        >
          <UChip :color="notification.color || 'error'" :show="!!notification.unread" inset>
            <div
              class="flex size-10 items-center justify-center rounded-full"
              :class="{
                'bg-error/10': notification.color === 'error' || !notification.color,
                'bg-warning/10': notification.color === 'warning',
                'bg-info/10': notification.color === 'info'
              }"
            >
              <UIcon
                :name="notification.color === 'warning' ? 'i-lucide-badge-alert' : 'i-lucide-triangle-alert'"
                class="size-5"
                :class="{
                  'text-error': notification.color === 'error' || !notification.color,
                  'text-warning': notification.color === 'warning',
                  'text-info': notification.color === 'info'
                }"
              />
            </div>
          </UChip>

          <div class="flex-1 text-sm">
            <p class="flex items-center justify-between gap-2">
              <span class="font-medium text-highlighted">{{ notification.title }}</span>
              <time
                :datetime="notification.date"
                class="shrink-0 text-xs text-muted"
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
