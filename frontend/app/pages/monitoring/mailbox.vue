<script setup lang="ts">
/**
 * Caixa Postal — mestre–detalhe (arquétipo Inbox).
 * Lista + NuxtPage (detalhe canônico em /monitoring/mailbox/[id]).
 * Desktop: painéis adjacentes; mobile: detalhe em USlideover.
 */
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import { MAILBOX_TRIAGE_FILTER_ITEMS } from '~/utils/mailbox-triage'
import type { MailboxListItem } from '~/components/monitoring/MailboxList.vue'

const api = useApi()
const route = useRoute()
const router = useRouter()
const { sessionEpoch } = useDashboard()
const {
  page, perPage, total, lastPage, clientId, q,
  loading, loadError, applyPaginator, syncUrl, resetPage
} = useServerPage()

type MailboxTriageFilter = (typeof MAILBOX_TRIAGE_FILTER_ITEMS)[number]['value']
const routeTriage = String(route.query.triage_status || 'all')
const triage = ref<MailboxTriageFilter>(
  MAILBOX_TRIAGE_FILTER_ITEMS.some(item => item.value === routeTriage)
    ? routeTriage as MailboxTriageFilter
    : 'all'
)
const rows = ref<MailboxListItem[]>([])
const listRef = ref<{ focusMessage: (id: number | null | undefined) => void } | null>(null)
/** ID a restaurar foco ao fechar detalhe (desktop/mobile). */
const lastFocusedId = ref<number | null>(null)

const selectedId = computed(() => {
  const id = Number(route.params.id)
  return Number.isFinite(id) && id > 0 ? id : null
})

const clientIdModel = computed<number | null>({
  get: () => {
    const n = Number(clientId.value)
    return Number.isFinite(n) && n > 0 ? n : null
  },
  set: (v) => {
    clientId.value = v && v > 0 ? String(v) : ''
  }
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

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    await syncUrl({
      triage_status: triage.value !== 'all' ? triage.value : undefined
    })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    const res = await api.fiscal.mailbox.list({
      page: page.value,
      per_page: perPage.value,
      client_id: clientId.value ? Number(clientId.value) : undefined,
      triage_status: triage.value !== 'all' ? triage.value : undefined
    }) as Record<string, unknown>
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = ((res.data as MailboxListItem[]) || [])
    applyPaginator(res)
    if (res.total == null && !(res.meta as { total?: number } | undefined)?.total) {
      total.value = rows.value.length
      lastPage.value = 1
    }
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

function selectMessage(id: number) {
  lastFocusedId.value = id
  void router.push({
    path: `/monitoring/mailbox/${id}`,
    query: { ...route.query }
  })
}

function closeDetail() {
  const restoreId = selectedId.value || lastFocusedId.value
  void router.push({ path: '/monitoring/mailbox', query: { ...route.query } }).then(() => {
    nextTick(() => {
      listRef.value?.focusMessage(restoreId)
    })
  })
}

function onTriaged() {
  void load()
}

watch(page, () => {
  void load()
})
watch([clientId, triage, q], () => {
  resetPage()
  void load()
})
watch(sessionEpoch, () => {
  rows.value = []
  total.value = 0
  void load()
})
onMounted(load)
</script>

<template>
  <div class="flex min-h-0 w-full flex-1">
    <UDashboardPanel id="mailbox-list" resizable :default-size="30" :min-size="22" :max-size="40">
  <template #header>
    <UDashboardNavbar title="Caixas Postais" data-testid="page-navbar">
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
      <template #trailing>

        <UBadge
          :label="String(total)"
          variant="subtle"
        />
      </template>
      <template #right>

        <MonitoringPortfolioActions
          module-key="mailbox"
          :client-id="clientIdModel"
          show-enqueue
          :show-export="true"
          @refreshed="load"
        />
      </template>
    </UDashboardNavbar>

        <UDashboardToolbar data-testid="page-toolbar">
          <template #left>
            <div class="flex min-w-0 flex-1 flex-col gap-2">
              <MonitoringModuleNav active="mailbox" />
              <div class="flex flex-wrap items-center gap-2 -ms-1">
                <USelect
                  v-model="triage"
                  :items="[...MAILBOX_TRIAGE_FILTER_ITEMS]"
                  value-key="value"
                  class="w-40"
                  aria-label="Triagem interna"
                  data-testid="mailbox-triage-filter"
                />
                <FiscalClientPicker
                  v-model="clientIdModel"
                  class="w-52 sm:w-64"
                />
                <UButton
                  color="neutral"
                  variant="ghost"
                  icon="i-lucide-refresh-cw"
                  :loading="loading"
                  aria-label="Atualizar"
                  @click="load"
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

        <!-- Erro de lista não vira empty silencioso: alerta acima + lista só se houver dados ou sucesso -->
        <MonitoringMailboxList
          v-if="!loadError || rows.length"
          ref="listRef"
          :messages="rows"
          :selected-id="selectedId"
          :loading="loading"
          @select="selectMessage"
        />

        <div
          v-if="lastPage > 1"
          class="mt-3 flex items-center justify-between border-t border-default pt-3"
        >
          <span class="text-xs text-muted">Pág. {{ page }}/{{ lastPage }}</span>
          <UPagination
            v-model="page"
            :total="total"
            :items-per-page="perPage"
            size="sm"
          />
        </div>
  </template>
</UDashboardPanel>

    <!-- Desktop: detalhe adjacente via NuxtPage -->
    <div class="hidden min-w-0 flex-1 lg:flex">
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
</template>
