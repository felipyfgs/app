<script setup lang="ts">
/**
 * Home — arquétipo copiado de
 * `.reference/nuxt-dashboard-template/app/pages/index.vue`
 * (UDashboardPanel + Navbar com alertas/plus + Toolbar + body em blocos).
 * Áreas nomeadas: Trabalho | Monitoramento fiscal (atalhos) | Operações/Infra.
 */
import type { DropdownMenuItem } from '@nuxt/ui'
import type { InboxItem, OperationsSummary } from '~/types/api'
import { quickActions } from '~/utils/navigation'

const api = useApi()
const toast = useToast()
const {
  isNotificationsSlideoverOpen,
  me,
  openClientCreate,
  openExportCreate,
  sessionEpoch
} = useDashboard()

const summary = ref<OperationsSummary | null>(null)
const lastGoodSummary = ref<OperationsSummary | null>(null)
const inboxItems = ref<InboxItem[]>([])
const lastValidAt = ref<string | null>(null)
const loading = ref(false)
const inboxLoading = ref(false)
const refreshError = ref<string | null>(null)

const officeLabel = computed(() => me.value?.office?.name || null)

const actionItems = computed<DropdownMenuItem[][]>(() => [[
  ...quickActions(me.value).map(action => ({
    label: action.label,
    icon: action.icon,
    to: action.to,
    onSelect: action.id === 'new-client'
      ? openClientCreate
      : action.id === 'new-export'
        ? openExportCreate
        : undefined
  }))
]])

const alertCount = computed(() => {
  if (!summary.value) return 0
  if (typeof summary.value.inbox_total === 'number') {
    return summary.value.inbox_total
  }
  return (summary.value.sync_blocked || 0) + (summary.value.sync_failures_24h || 0)
})

const contextItems = computed(() => {
  const items: Array<{ key: string, label: string, value: string, icon?: string }> = []
  if (officeLabel.value) {
    items.push({
      key: 'office',
      label: 'Escritório',
      value: officeLabel.value,
      icon: 'i-lucide-building-2'
    })
  }
  if (lastValidAt.value) {
    items.push({
      key: 'updated',
      label: 'Atualizado',
      value: formatDateTime(lastValidAt.value),
      icon: 'i-lucide-clock'
    })
  }
  return items
})

async function load() {
  const epoch = sessionEpoch.value
  const had = !!lastGoodSummary.value
  loading.value = !had
  inboxLoading.value = true
  try {
    const [summaryResult, inboxResult] = await Promise.allSettled([
      api.operations.summary(),
      api.operations.inbox({ limit: 5 })
    ])

    if (epoch !== sessionEpoch.value) return

    if (summaryResult.status === 'fulfilled') {
      summary.value = summaryResult.value.data
      lastGoodSummary.value = summaryResult.value.data
      lastValidAt.value = summaryResult.value.data.generated_at
      refreshError.value = null
    } else {
      const message = apiErrorMessage(summaryResult.reason, 'Não foi possível carregar o resumo operacional.')
      refreshError.value = message
      if (lastGoodSummary.value) {
        summary.value = lastGoodSummary.value
      } else {
        toast.add({ title: message, color: 'error' })
      }
    }

    if (inboxResult.status === 'fulfilled') {
      inboxItems.value = inboxResult.value.data
    } else if (summaryResult.status === 'fulfilled') {
      inboxItems.value = []
    }
  } finally {
    if (epoch === sessionEpoch.value) {
      loading.value = false
      inboxLoading.value = false
    }
  }
}

onMounted(load)

watch(sessionEpoch, () => {
  summary.value = null
  lastGoodSummary.value = null
  inboxItems.value = []
  lastValidAt.value = null
  refreshError.value = null
  void load()
})
</script>

<template>
  <DashboardListShell
    panel-id="home"
    title="Dashboard"
  >
    <template #navbar-right>
      <UTooltip
            text="Alertas"
            :shortcuts="['N']"
          >
            <UButton
              color="neutral"
              variant="ghost"
              square
              aria-label="Abrir alertas operacionais"
              @click="() => { isNotificationsSlideoverOpen = true }"
            >
              <UChip
                color="primary"
                :show="alertCount > 0"
                inset
              >
                <UIcon
                  name="i-lucide-bell"
                  class="size-5 shrink-0"
                />
              </UChip>
            </UButton>
          </UTooltip>
          <UDropdownMenu
            v-if="actionItems[0]?.length"
            :items="actionItems"
          >
            <UButton
              icon="i-lucide-plus"
              size="md"
              class="rounded-full"
              aria-label="Abrir ações rápidas"
            />
          </UDropdownMenu>
    </template>
    <template #toolbar>
      <UDashboardToolbar data-testid="page-toolbar">
        <template #left>
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-refresh-cw"
            label="Atualizar resumo"
            :loading="loading"
            class="-ms-1"
            @click="load"
          />
          <OperationalContext
            :items="contextItems"
            density="compact"
            class="hidden sm:flex"
          />
        </template>

        <template #right>
          <span
            v-if="lastValidAt"
            class="text-xs text-muted"
          >
            Última atualização válida: {{ formatDateTime(lastValidAt) }}
          </span>
        </template>
      </UDashboardToolbar>
    </template>
      <!-- Área: Operações / infraestrutura (cursores, certs, backup) -->
      <section
        aria-labelledby="home-ops-infra-heading"
        class="space-y-2"
      >
        <h2
          id="home-ops-infra-heading"
          class="sr-only"
        >
          Operações e infraestrutura
        </h2>
        <HomeStats
          :summary="summary"
          :loading="loading"
        />
      </section>

      <!-- Área: Trabalho operacional -->
      <div class="px-4 pb-2 pt-6 lg:px-6">
        <HomeWorkKpisBlock />
      </div>

      <!-- Área: Monitoramento fiscal (deep-links; sem inventar métricas) -->
      <section
        class="px-4 pb-2 pt-4 lg:px-6"
        aria-labelledby="home-fiscal-heading"
      >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2
              id="home-fiscal-heading"
              class="text-base font-semibold text-highlighted"
            >
              Monitoramento fiscal
            </h2>
            <p class="text-xs text-muted">
              Atalhos para módulos com dados reais — sem somar indicadores distintos
            </p>
          </div>
          <UButton
            size="sm"
            color="neutral"
            variant="ghost"
            to="/monitoring"
            label="Dashboard fiscal"
            trailing-icon="i-lucide-arrow-right"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <UButton
            to="/monitoring/simples-mei"
            size="sm"
            color="neutral"
            variant="subtle"
            icon="i-lucide-badge-percent"
            label="Simples / MEI"
          />
          <UButton
            to="/monitoring/dctfweb"
            size="sm"
            color="neutral"
            variant="subtle"
            icon="i-lucide-file-input"
            label="DCTFWeb"
          />
          <UButton
            to="/monitoring/sitfis"
            size="sm"
            color="neutral"
            variant="subtle"
            icon="i-lucide-clipboard-check"
            label="Situação fiscal"
          />
          <UButton
            to="/monitoring/mailbox"
            size="sm"
            color="neutral"
            variant="subtle"
            icon="i-lucide-mail"
            label="Caixa postal"
          />
          <UButton
            to="/monitoring/guides"
            size="sm"
            color="neutral"
            variant="subtle"
            icon="i-lucide-receipt"
            label="Guias"
          />
          <UButton
            to="/monitoring/fgts"
            size="sm"
            color="neutral"
            variant="subtle"
            icon="i-lucide-landmark"
            label="FGTS (parcial)"
          />
        </div>
      </section>

      <!-- Área: Atenção operacional (inbox / backup) -->
      <div class="pt-4">
        <HomeOperations
          :summary="summary"
          :loading="loading"
          :inbox-items="inboxItems"
          :inbox-loading="inboxLoading"
          :error="refreshError"
          @retry="load"
        />
      </div>

      <HomeTotals
        :summary="summary"
        :loading="loading"
      />
  </DashboardListShell>
</template>
