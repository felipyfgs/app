<script setup lang="ts">
import type { OperationsSummary } from '~/types/api'

const api = useApi()
const toast = useToast()
const { canManageClients, isNotificationsSlideoverOpen } = useDashboard()
const summary = ref<OperationsSummary | null>(null)
const loading = ref(false)

const cards = computed(() => [{
  title: 'Clientes ativos',
  icon: 'i-lucide-building-2',
  value: summary.value?.clients ?? 0,
  to: '/clients'
}, {
  title: 'Estabelecimentos',
  icon: 'i-lucide-map-pin-house',
  value: summary.value?.establishments ?? 0,
  to: '/clients'
}, {
  title: 'Notas fiscais',
  icon: 'i-lucide-file-text',
  value: summary.value?.notes ?? 0,
  to: '/notes'
}, {
  title: 'Exportações prontas',
  icon: 'i-lucide-package-check',
  value: summary.value?.exports_ready ?? 0,
  to: '/exports'
}, {
  title: 'Sincronizações vencidas',
  icon: 'i-lucide-clock-alert',
  value: summary.value?.sync_due ?? 0,
  to: '/syncs'
}, {
  title: 'Cursores bloqueados',
  icon: 'i-lucide-triangle-alert',
  value: summary.value?.sync_blocked ?? 0,
  to: '/syncs'
}])

async function load() {
  loading.value = true
  try {
    summary.value = (await api.operations.summary()).data
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível carregar o resumo operacional.'),
      color: 'error'
    })
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <UDashboardPanel id="home">
    <template #header>
      <UDashboardNavbar title="Dashboard" :ui="{ right: 'gap-3' }">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>

        <template #right>
          <UTooltip text="Atualizar resumo">
            <UButton
              color="neutral"
              variant="ghost"
              icon="i-lucide-refresh-cw"
              square
              aria-label="Atualizar resumo operacional"
              :loading="loading"
              @click="load"
            />
          </UTooltip>
          <UTooltip text="Alertas" :shortcuts="['N']">
            <UButton
              color="neutral"
              variant="ghost"
              square
              aria-label="Abrir alertas operacionais"
              @click="isNotificationsSlideoverOpen = true"
            >
              <UChip
                color="error"
                :show="(summary?.sync_blocked || 0) + (summary?.sync_failures_24h || 0) > 0"
                inset
              >
                <UIcon name="i-lucide-bell" class="size-5 shrink-0" />
              </UChip>
            </UButton>
          </UTooltip>
          <UButton
            v-if="canManageClients"
            icon="i-lucide-plus"
            to="/clients?new=1"
            label="Novo cliente"
            class="hidden rounded-full sm:flex"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <UPageCard
          v-for="card in cards"
          :key="card.title"
          :icon="card.icon"
          :title="card.title"
          :to="card.to"
          variant="subtle"
        >
          <template #description>
            <span class="text-2xl font-semibold text-highlighted">
              {{ loading && !summary ? '…' : card.value }}
            </span>
          </template>
        </UPageCard>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <UAlert
          v-if="summary?.sync_failures_24h"
          color="error"
          icon="i-lucide-circle-x"
          title="Falhas de sincronização nas últimas 24 horas"
          :description="`${summary.sync_failures_24h} execução(ões) exigem revisão do histórico.`"
          :actions="[{ label: 'Ver histórico', to: '/syncs', color: 'error', variant: 'subtle' }]"
        />
        <UAlert
          v-if="summary?.credentials_expiring_30d"
          color="warning"
          icon="i-lucide-badge-alert"
          title="Certificados próximos do vencimento"
          :description="`${summary.credentials_expiring_30d} certificado(s) vencem em até 30 dias.`"
          :actions="[{ label: 'Ver clientes', to: '/clients', color: 'warning', variant: 'subtle' }]"
        />
        <UAlert
          v-if="summary?.exports_pending"
          color="info"
          icon="i-lucide-loader-circle"
          title="Exportações em processamento"
          :description="`${summary.exports_pending} solicitação(ões) estão na fila.`"
          :actions="[{ label: 'Acompanhar', to: '/exports', color: 'info', variant: 'subtle' }]"
        />
        <UAlert
          v-if="summary && !summary.sync_failures_24h && !summary.sync_blocked && !summary.credentials_expiring_30d"
          color="success"
          icon="i-lucide-circle-check"
          title="Operação sem alertas críticos"
          description="Nenhuma falha recente, cursor bloqueado ou certificado próximo do vencimento."
        />
      </div>

      <p v-if="summary?.generated_at" class="text-xs text-muted">
        Resumo atualizado em {{ formatDateTime(summary.generated_at) }}.
      </p>
    </template>
  </UDashboardPanel>
</template>
