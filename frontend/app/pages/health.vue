<script setup lang="ts">
import type { TableColumn, TableRow } from '@nuxt/ui'
import OperationsSectionNav from '~/components/navigation/OperationsSectionNav.vue'
import type { InboxItem, InboxItemType, InboxSeverity } from '~/types/api'
import { resolveInboxItemLink, SERPRO_INBOX_TYPE_FILTERS } from '~/utils/inbox-links'
import { TABLE_CELL_BADGE_CLASS, TABLE_CELL_BADGE_UI } from '~/utils/table-ui'

const api = useApi()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const items = ref<InboxItem[]>([])
const cursor = ref<string | null>(null)
const totalEstimate = ref<number | null>(null)
const loading = ref(false)
const loadError = ref<string | null>(null)
const generatedAt = ref<string | null>(null)

const FILTER_ALL = 'all'

const initialSeverity = String(route.query.severity || FILTER_ALL)
const severityFilter = ref(
  ['critical', 'high', 'medium', 'low', FILTER_ALL].includes(initialSeverity)
    ? initialSeverity
    : FILTER_ALL
)
const typeFilter = ref(String(route.query.type || FILTER_ALL) || FILTER_ALL)

const severityItems = [
  { label: 'Todas as severidades', value: FILTER_ALL },
  { label: 'Crítica', value: 'critical' },
  { label: 'Alta', value: 'high' },
  { label: 'Média', value: 'medium' },
  { label: 'Baixa', value: 'low' }
]

const typeItems = [
  { label: 'Todos os tipos', value: FILTER_ALL },
  { label: 'Cursor bloqueado', value: 'cursor_blocked' },
  { label: 'Cursor com erro', value: 'cursor_error' },
  { label: 'Falha de sync recente', value: 'sync_failed_recent' },
  { label: 'Certificado vencido', value: 'credential_expired' },
  { label: 'Certificado (7d)', value: 'credential_expiring_7d' },
  { label: 'Certificado (30d)', value: 'credential_expiring_30d' },
  { label: 'Backup atrasado', value: 'backup_stale' },
  { label: 'Backup nunca executado', value: 'backup_never' },
  { label: 'Lacuna esgotada (nNF)', value: 'outbound_gap_exhausted' },
  { label: '562 sem chave', value: 'outbound_562_no_key' },
  { label: 'Bloqueio 656 / série MA', value: 'outbound_656' },
  { label: 'Recuperação MA expirada', value: 'outbound_retrieval_expired' },
  { label: 'XML divergente MA', value: 'outbound_xml_divergent' },
  { label: 'Autorização inesperada MA', value: 'outbound_authorized_unexpected' },
  { label: 'Cancelamento falho MA', value: 'outbound_cancel_failed' },
  { label: 'Quarentena: emitente sem vínculo', value: 'quarantine_unmatched_issuer' },
  { label: 'Quarentena: tag autXML', value: 'quarantine_autxml_tag' },
  { label: 'Quarentena: evento órfão', value: 'quarantine_orphan_event' },
  { label: 'Quarentena: bytes conflitantes', value: 'quarantine_bytes_diverge' },
  { label: 'Quarentena: schema incompleto', value: 'quarantine_schema' },
  { label: 'Quarentena: outros', value: 'quarantine_other' },
  ...SERPRO_INBOX_TYPE_FILTERS
]

const killSwitch = ref<{
  global_active: boolean
  m2m_status: string
  enabled: boolean
  protocol_query_enabled?: boolean
  mutating_probe_enabled?: boolean
}>({
  global_active: false,
  m2m_status: 'NO_GO_M2M',
  enabled: false
})
const killReason = ref('')
const killLoading = ref(false)
const { canManageCredentials, sessionEpoch } = useDashboard()

async function loadKillSwitch() {
  try {
    killSwitch.value = (await api.outbound.killSwitchStatus()).data
  } catch {
    // Mantém defaults seguros (flags off / NO_GO_M2M) se a API não responder.
  }
}

async function toggleKill(active: boolean) {
  if (!canManageCredentials.value || !killReason.value.trim()) {
    toast.add({ title: 'Informe o motivo do kill switch.', color: 'warning' })
    return
  }
  killLoading.value = true
  try {
    await api.outbound.killSwitch({ active, reason: killReason.value.trim() })
    toast.add({ title: active ? 'Kill switch global MA ligado.' : 'Kill switch desligado.', color: 'warning' })
    await loadKillSwitch()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha no kill switch.'), color: 'error' })
  } finally {
    killLoading.value = false
  }
}

onMounted(() => {
  void loadKillSwitch()
})

const columns: TableColumn<InboxItem>[] = [
  { accessorKey: 'severity', header: 'Severidade' },
  { accessorKey: 'type', header: 'Tipo' },
  { accessorKey: 'title', header: 'Item' },
  {
    accessorKey: 'occurred_at',
    header: 'Quando',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  { id: 'actions', header: '' }
]

function severityColor(severity: string): 'error' | 'warning' | 'info' | 'neutral' {
  return inboxSeverityColor(severity)
}

function severityLabel(severity: string): string {
  return severityItems.find(item => item.value === severity)?.label || severity
}

function typeLabel(type: string): string {
  return typeItems.find(item => item.value === type)?.label || type
}

function itemLink(item: InboxItem): string {
  return resolveInboxItemLink(item)
}

async function load(reset = false) {
  const epoch = sessionEpoch.value
  if (reset) {
    cursor.value = null
  }
  loading.value = true
  try {
    const response = await api.operations.inbox({
      limit: 50,
      ...(cursor.value ? { cursor: cursor.value } : {}),
      ...(severityFilter.value !== FILTER_ALL
        ? { severity: severityFilter.value as InboxSeverity }
        : {}),
      ...(typeFilter.value !== FILTER_ALL
        ? { type: typeFilter.value as InboxItemType }
        : {})
    })
    if (epoch !== sessionEpoch.value) return
    items.value = reset ? response.data : [...items.value, ...response.data]
    cursor.value = response.meta.next_cursor
    totalEstimate.value = response.meta.total_estimate ?? null
    generatedAt.value = response.meta.generated_at
    loadError.value = null
  } catch (caught) {
    if (epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(caught, 'Erro ao carregar a inbox operacional.')
    if (reset) {
      toast.add({ title: loadError.value, color: 'error' })
    }
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

function selectRow(_event: Event, row: TableRow<InboxItem>) {
  const to = itemLink(row.original)
  if (to && to !== '/health') {
    void router.push(to)
  }
}

watch(
  [severityFilter, typeFilter],
  () => {
    void load(true)
  },
  { immediate: true }
)

watch(sessionEpoch, () => {
  items.value = []
  cursor.value = null
  loadError.value = null
  void load(true)
})

async function syncHealthUrl() {
  const query: Record<string, string> = {}
  if (severityFilter.value && severityFilter.value !== FILTER_ALL) {
    query.severity = severityFilter.value
  }
  if (typeFilter.value && typeFilter.value !== FILTER_ALL) {
    query.type = typeFilter.value
  }
  await router.replace({ path: route.path, query })
}

watch([severityFilter, typeFilter], () => {
  void syncHealthUrl()
})
</script>

<template>
  <UDashboardPanel id="health">
    <template #header>
      <UDashboardNavbar title="Saúde operacional" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UTooltip text="Atualizar inbox">
            <UButton
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="ghost"
              square
              aria-label="Atualizar inbox operacional"
              :loading="loading"
              @click="load(true)"
            />
          </UTooltip>
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="operations-section-tabs">
        <OperationsSectionNav />
      </UDashboardToolbar>

      <UDashboardToolbar data-testid="page-toolbar">
        <div
          class="flex w-full min-w-0 flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between"
          data-testid="health-filter-toolbar"
        >
          <div
            class="flex min-w-0 items-center gap-2 overflow-x-auto overscroll-x-contain touch-pan-x pb-0.5 sm:overflow-visible sm:pb-0"
          >
            <USelect
              v-model="severityFilter"
              :items="severityItems"
              value-key="value"
              class="w-40 shrink-0 sm:w-44"
              aria-label="Filtrar por severidade"
            />
            <USelect
              v-model="typeFilter"
              :items="typeItems"
              value-key="value"
              class="w-48 shrink-0 sm:w-56"
              aria-label="Filtrar por tipo"
            />
          </div>
          <span
            v-if="generatedAt"
            class="shrink-0 text-xs text-muted"
          >
            Gerado em {{ formatDateTime(generatedAt) }}
            <template v-if="totalEstimate !== null"> · {{ totalEstimate }} item(ns)</template>
          </span>
        </div>
      </UDashboardToolbar>
    </template>

    <template #body>
      <p class="mb-4 text-sm text-muted">
        Restore de backup fora do painel.
      </p>

      <div data-testid="ma-kill-switch-card" class="mb-4">
        <UPageCard
          title="Kill switch MA"
          description="Bloqueia novas consultas. Não apaga dados."
          variant="subtle"
        >
          <div class="flex flex-wrap items-center gap-3 text-sm">
            <UBadge :color="killSwitch.global_active ? 'error' : 'success'" variant="subtle">
              {{ killSwitch.global_active ? 'GLOBAL ATIVO' : 'Global off' }}
            </UBadge>
            <span class="text-muted">Canal {{ killSwitch.enabled ? 'enabled' : 'disabled' }}</span>
            <span class="text-muted">M2M: {{ killSwitch.m2m_status }}</span>
          </div>
          <div v-if="canManageCredentials" class="mt-3 flex flex-wrap items-end gap-2">
            <UFormField label="Motivo" class="min-w-[16rem] flex-1">
              <UInput v-model="killReason" class="w-full" placeholder="Motivo auditável…" />
            </UFormField>
            <UButton
              color="error"
              variant="soft"
              label="Ligar"
              :loading="killLoading"
              data-testid="ma-kill-on"
              @click="toggleKill(true)"
            />
            <UButton
              variant="ghost"
              label="Desligar"
              :loading="killLoading"
              @click="toggleKill(false)"
            />
          </div>
        </UPageCard>
      </div>

      <UAlert
        v-if="loadError"
        :color="items.length ? 'warning' : 'error'"
        icon="i-lucide-wifi-off"
        :title="loadError"
        class="mb-4"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => load(true) }]"
      />

      <UEmpty
        v-if="!loading && !loadError && !items.length"
        icon="i-lucide-circle-check"
        title="Nenhum problema operacional"
      />

      <UTable
        v-else
        data-testid="data-table"
        :data="items"
        :loading="loading"
        :columns="columns"
        class="shrink-0 min-w-0 w-full"
        :ui="{
          base: 'table-fixed border-separate border-spacing-0',
          thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
          tbody: '[&>tr]:last:[&>td]:border-b-0',
          th: 'px-3 py-1.5 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
          td: 'px-3 py-1 border-b border-default',
          separator: 'h-0'
        }"
        @select="selectRow"
      >
        <template #severity-cell="{ row }">
          <UBadge
            :color="severityColor(row.original.severity)"
            variant="subtle"
            size="md"
            class="capitalize"
            :class="TABLE_CELL_BADGE_CLASS"
            :ui="TABLE_CELL_BADGE_UI"
          >
            {{ severityLabel(row.original.severity) }}
          </UBadge>
        </template>
        <template #type-cell="{ row }">
          <span class="text-sm text-muted">{{ typeLabel(row.original.type) }}</span>
        </template>
        <template #title-cell="{ row }">
          <div class="min-w-0">
            <p class="truncate font-medium text-highlighted">
              {{ row.original.title }}
            </p>
            <p class="truncate text-xs text-muted">
              {{ row.original.body }}
            </p>
          </div>
        </template>
        <template #occurred_at-cell="{ row }">
          {{ formatDateTime(row.original.occurred_at) }}
        </template>
        <template #actions-cell="{ row }">
          <UButton
            v-if="itemLink(row.original) !== '/health'"
            size="xs"
            color="neutral"
            variant="ghost"
            icon="i-lucide-arrow-up-right"
            :to="itemLink(row.original)"
            aria-label="Abrir item"
            @click.stop
          />
        </template>
      </UTable>

      <div v-if="cursor" class="mt-4 flex justify-center">
        <UButton
          color="neutral"
          variant="subtle"
          label="Carregar mais"
          :loading="loading"
          @click="load(false)"
        />
      </div>
    </template>
  </UDashboardPanel>
</template>
