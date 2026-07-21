<script setup lang="ts">
/**
 * Caixa Postal — mestre–detalhe (arquétipo Inbox).
 * Página de Monitoramento em largura total, sem navegação global duplicada;
 * lista + NuxtPage (detalhe em /monitoring/mailbox/[id]) abaixo das tabs.
 * Desktop: painéis adjacentes; mobile: detalhe em USlideover.
 *
 * Filtros: triage (status) + client via ModuleToolbar + surface monitoring.mailbox
 * (presets salvos) no painel da lista. API mailbox não aplica `q` — busca desligada.
 */
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import type { MailboxListItem } from '~/components/monitoring/MailboxList.vue'
import type { MonitoringFilterConfig, MonitoringFilterValue } from '~/types/fiscal-modules'
import { consumeMailboxClientFilterHandoff } from '~/utils/mailbox-handoff'
import { MAILBOX_TRIAGE_FILTER_ITEMS } from '~/utils/mailbox-triage'
import {
  normalizeMonitoringFilters,
  resetMonitoringFilters
} from '~/utils/monitoring-filters'
import ShellTableFooter from '~/components/shell/TableFooter.vue'

const api = useApi()
const route = useRoute()
const router = useRouter()
const { sessionEpoch } = useDashboard()
const {
  page, perPage, total, lastPage, clientId, q,
  loading, loadError, applyPaginator, syncUrl, resetPage
} = useServerPage()

type MailboxTriageFilter = (typeof MAILBOX_TRIAGE_FILTER_ITEMS)[number]['value']
const triage = ref<MailboxTriageFilter>('all')
const rows = ref<MailboxListItem[]>([])
const listRef = ref<{ focusMessage: (id: number | null | undefined) => void } | null>(null)
/** ID a restaurar foco ao fechar detalhe (desktop/mobile). */
const lastFocusedId = ref<number | null>(null)
const alerts = ref<Array<Record<string, unknown>>>([])
const alertsError = ref<string | null>(null)
let loadSeq = 0
let filterTransactionDepth = 0
let handoffApplied = false

const triageItems = MAILBOX_TRIAGE_FILTER_ITEMS.map(item => ({
  label: item.label,
  value: item.value
}))

const filterConfig: MonitoringFilterConfig = {
  // List API de mailbox não aceita `q` — busca seria decorativa.
  search: false,
  fields: [
    {
      key: 'status',
      kind: 'option',
      label: 'Triagem',
      items: triageItems
    },
    { key: 'clientId', kind: 'client', label: 'Cliente', multiple: false }
  ]
}

const filters = computed<MonitoringFilterValue>(() => normalizeMonitoringFilters({
  // status reutilizado como eixo de triagem na surface mailbox
  status: triage.value,
  clientIds: (() => {
    const n = Number(clientId.value)
    return Number.isFinite(n) && n >= 1 ? [Math.floor(n)] : []
  })(),
  q: ''
}))

const selectedId = computed(() => {
  const id = Number(route.params.id)
  return Number.isFinite(id) && id > 0 ? id : null
})

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const mobileOpen = computed({
  get: () => Boolean(isMobile.value && selectedId.value),
  set: (open: boolean) => {
    if (!open && selectedId.value) {
      closeDetail()
    }
  }
})

async function loadAlerts() {
  alertsError.value = null
  try {
    const res = await api.fiscal.mailbox.alerts()
    const all = ((res.data as Array<Record<string, unknown>>) || [])
    const clientNum = Number(clientId.value)
    const scoped = Number.isFinite(clientNum) && clientNum > 0
      ? all.filter(a => Number(a.client_id) === clientNum)
      : all
    alerts.value = scoped.slice(0, 8)
  } catch (caught) {
    alerts.value = []
    alertsError.value = apiErrorMessage(caught, 'Falha ao carregar alertas.')
  }
}

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    if (!handoffApplied) {
      handoffApplied = true
      const handoffClientId = consumeMailboxClientFilterHandoff()
      if (handoffClientId != null) {
        clientId.value = String(handoffClientId)
      }
    }
    await syncUrl()
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    const clientNum = Number(clientId.value)
    const res = await api.fiscal.mailbox.list({
      page: page.value,
      per_page: perPage.value,
      client_id: Number.isFinite(clientNum) && clientNum > 0 ? clientNum : undefined,
      triage_status: triage.value !== 'all' ? triage.value : undefined
    }) as Record<string, unknown>
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = ((res.data as MailboxListItem[]) || [])
    applyPaginator(res)
    if (res.total == null && !(res.meta as { total?: number } | undefined)?.total) {
      total.value = rows.value.length
      lastPage.value = 1
    }
    void loadAlerts()
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = []
    total.value = 0
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar caixas postais.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function applyModuleFilters(nextValue: MonitoringFilterValue) {
  const next = normalizeMonitoringFilters(nextValue)
  const nextClient = next.clientIds[0] != null ? String(next.clientIds[0]) : ''
  const nextTriage = (next.status || 'all') as MailboxTriageFilter
  if (triage.value === nextTriage && clientId.value === nextClient) return

  filterTransactionDepth += 1
  try {
    triage.value = nextTriage
    clientId.value = nextClient
    // API mailbox não aplica q — limpar residual de useServerPage.
    q.value = ''
    resetPage()
    await nextTick()
  } finally {
    filterTransactionDepth -= 1
  }
  await load()
}

function resetModuleFilters() {
  void applyModuleFilters(resetMonitoringFilters())
}

function selectMessage(id: number) {
  lastFocusedId.value = id
  void router.push(`/monitoring/mailbox/${id}`)
}

function closeDetail() {
  const restoreId = selectedId.value || lastFocusedId.value
  void router.push('/monitoring/mailbox').then(() => {
    nextTick(() => {
      listRef.value?.focusMessage(restoreId)
    })
  })
}

function onTriaged() {
  void load()
}

function setPerPage(next: number) {
  const allowed = [10, 20, 50]
  const target = allowed.includes(Number(next)) ? Number(next) : 20
  if (perPage.value === target) return
  perPage.value = target
  resetPage()
  void load()
}

watch(page, () => {
  if (filterTransactionDepth > 0) return
  void load()
})
watch([clientId, triage], () => {
  if (filterTransactionDepth > 0) return
  resetPage()
  void load()
})
watch(sessionEpoch, () => {
  filterTransactionDepth += 1
  try {
    triage.value = 'all'
    clientId.value = ''
    q.value = ''
    rows.value = []
    total.value = 0
    lastPage.value = 1
    page.value = 1
    alerts.value = []
    handoffApplied = false
  } finally {
    filterTransactionDepth -= 1
  }
  void load()
})
onMounted(load)

function openAlert(alert: Record<string, unknown>) {
  const messageId = Number(alert.mailbox_message_id)
  if (Number.isFinite(messageId) && messageId > 0) {
    selectMessage(messageId)
    return
  }
  const deep = String(alert.deep_link || '')
  if (deep.startsWith('/monitoring/mailbox')) {
    void router.push(deep)
  }
}
</script>

<template>
  <div class="flex min-h-0 w-full flex-1 flex-col">
    <!-- Chrome Fiscal em largura total — acima do mestre–detalhe -->
    <UDashboardNavbar
      title="Caixas Postais"
      data-testid="page-navbar"
      class="shrink-0"
    >
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
      <template #trailing>
        <UBadge
          :label="String(total)"
          variant="subtle"
        />
      </template>
    </UDashboardNavbar>

    <div class="shrink-0 space-y-3 px-4 pt-4 sm:px-6">
      <FiscalModuleAvailabilityBanner module-key="mailbox" />

      <div
        v-if="alerts.length || alertsError"
        class="rounded-lg border border-default bg-elevated/40 p-3"
        data-testid="mailbox-alerts-strip"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <p class="text-sm font-medium text-highlighted">
            Alertas ativos
          </p>
          <UBadge
            v-if="alerts.length"
            :label="String(alerts.length)"
            variant="subtle"
            color="warning"
          />
        </div>
        <UAlert
          v-if="alertsError"
          color="error"
          variant="subtle"
          :title="alertsError"
          class="mb-2"
        />
        <ul
          v-else
          class="flex flex-col gap-1.5"
        >
          <li
            v-for="alert in alerts"
            :key="String(alert.id)"
          >
            <button
              type="button"
              class="flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-elevated"
              @click="openAlert(alert)"
            >
              <UBadge
                size="sm"
                variant="subtle"
                :color="String(alert.severity) === 'high' ? 'error' : 'warning'"
                :label="String(alert.severity || 'info')"
              />
              <span class="min-w-0 flex-1">
                <span class="font-medium text-highlighted">{{ alert.title }}</span>
                <span
                  v-if="alert.body"
                  class="mt-0.5 block text-xs text-muted"
                >{{ alert.body }}</span>
              </span>
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Inbox abaixo do navbar; navegação fiscal fica no sidebar. -->
    <div class="flex min-h-0 w-full flex-1">
      <UDashboardPanel
        id="mailbox-list"
        resizable
        :default-size="30"
        :min-size="22"
        :max-size="40"
        class="min-h-0"
      >
        <template #header>
          <!--
            Faixa compacta de filtros + presets (surface monitoring.mailbox).
            Sem reescrever a lista mestre–detalhe em ModuleTable.
          -->
          <UDashboardToolbar>
            <template #left>
              <div
                class="flex min-w-0 flex-1 flex-col gap-2"
                data-testid="mailbox-filter-strip"
              >
                <div data-testid="mailbox-triage-filter">
                  <MonitoringModuleToolbar
                    surface="monitoring.mailbox"
                    :filters="filters"
                    :filter-config="filterConfig"
                    :loading="loading"
                    :total="total"
                    :show-total="false"
                    :reset-key="sessionEpoch"
                    @apply-filters="applyModuleFilters"
                    @reset-filters="resetModuleFilters"
                    @refresh="load"
                  />
                </div>
              </div>
            </template>
          </UDashboardToolbar>
        </template>

        <template #body>
          <UAlert
            v-if="loadError"
            color="error"
            icon="i-lucide-circle-x"
            :title="loadError"
            class="mb-3"
          >
            <template #actions>
              <UButton
                size="xs"
                color="neutral"
                variant="outline"
                label="Tentar de novo"
                @click="load"
              />
            </template>
          </UAlert>

          <!-- Lista sempre montada (casca + empty interno); erro em alerta acima -->
          <MonitoringMailboxList
            ref="listRef"
            :messages="rows"
            :selected-id="selectedId"
            :loading="loading"
            @select="selectMessage"
          />

          <ShellTableFooter
            :total="total"
            :page="page"
            :items-per-page="perPage"
            per-page-aria-label="Mensagens por página"
            test-id="mailbox-pagination"
            @update:page="page = $event"
            @update:items-per-page="setPerPage"
          >
            <span class="tabular-nums">{{ total }}</span> mensagem(ns)
            <template v-if="lastPage > 1">
              <span class="text-dimmed"> · </span>
              página {{ page }} de {{ lastPage }}
            </template>
          </ShellTableFooter>
        </template>
      </UDashboardPanel>

      <!-- Desktop: detalhe adjacente via NuxtPage -->
      <div
        v-if="!isMobile"
        class="hidden min-h-0 min-w-0 flex-1 lg:flex"
      >
        <NuxtPage
          @close="closeDetail"
          @triaged="onTriaged"
        />
      </div>

      <!-- Mobile: slideover com detalhe -->
      <ClientOnly>
        <USlideover
          v-if="isMobile"
          v-model:open="mobileOpen"
          :ui="{ content: 'max-w-full' }"
        >
          <template #content>
            <MonitoringMailboxMail
              v-if="selectedId"
              :message-id="selectedId"
              show-close
              @close="closeDetail"
              @triaged="onTriaged"
            />
          </template>
        </USlideover>
      </ClientOnly>
    </div>
  </div>
</template>
