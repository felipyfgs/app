<script setup lang="ts">
import type { TableColumn, TableRow } from '@nuxt/ui'
import type { InboxItem, InboxItemType, InboxSeverity } from '~/types/api'

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

const severityFilter = computed({
  get: () => {
    const value = String(route.query.severity || FILTER_ALL)
    return ['critical', 'high', 'medium', 'low', FILTER_ALL].includes(value) ? value : FILTER_ALL
  },
  set: (value: string) => {
    void updateQuery({ severity: value === FILTER_ALL ? undefined : value })
  }
})

const typeFilter = computed({
  get: () => {
    const value = String(route.query.type || FILTER_ALL)
    return value || FILTER_ALL
  },
  set: (value: string) => {
    void updateQuery({ type: value === FILTER_ALL ? undefined : value })
  }
})

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
  { label: 'Backup nunca executado', value: 'backup_never' }
]

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

function typeLabel(type: string): string {
  return typeItems.find(item => item.value === type)?.label || type
}

function itemLink(item: InboxItem): string {
  if (item.type.startsWith('credential') && item.links?.credential) {
    return item.links.credential
  }
  if (item.links?.sync) {
    return item.links.sync
  }
  if (item.links?.client) {
    return item.links.client
  }
  return '/health'
}

async function updateQuery(patch: Record<string, string | undefined>) {
  const next = { ...route.query, ...patch }
  for (const key of Object.keys(next)) {
    if (next[key] === undefined || next[key] === '') {
      delete next[key]
    }
  }
  await router.replace({ query: next })
}

async function load(reset = false) {
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
    items.value = reset ? response.data : [...items.value, ...response.data]
    cursor.value = response.meta.next_cursor
    totalEstimate.value = response.meta.total_estimate ?? null
    generatedAt.value = response.meta.generated_at
    loadError.value = null
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao carregar a inbox operacional.')
    if (reset) {
      toast.add({ title: loadError.value, color: 'error' })
    }
  } finally {
    loading.value = false
  }
}

function selectRow(_event: Event, row: TableRow<InboxItem>) {
  const to = itemLink(row.original)
  if (to && to !== '/health') {
    void router.push(to)
  }
}

watch(
  () => [route.query.severity, route.query.type] as const,
  () => {
    void load(true)
  },
  { immediate: true }
)
</script>

<template>
  <UDashboardPanel id="health">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Saúde operacional">
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

      <UDashboardToolbar data-testid="page-toolbar">
        <template #left>
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <USelect
              v-model="severityFilter"
              :items="severityItems"
              value-key="value"
              class="w-44"
              aria-label="Filtrar por severidade"
            />
            <USelect
              v-model="typeFilter"
              :items="typeItems"
              value-key="value"
              class="w-56"
              aria-label="Filtrar por tipo"
            />
          </div>
        </template>
        <template #right>
          <span v-if="generatedAt" class="text-xs text-muted">
            Gerado em {{ formatDateTime(generatedAt) }}
            <template v-if="totalEstimate !== null"> · {{ totalEstimate }} item(ns)</template>
          </span>
        </template>
      </UDashboardToolbar>
    </template>

    <template #body>
      <UAlert
        icon="i-lucide-info"
        title="Fila acionável do escritório"
        description="Itens derivados de cursores, certificados e backup da instância. Não há restore nem avanço de NSU por esta tela."
        class="mb-4"
      />

      <UAlert
        v-if="loadError"
        :color="items.length ? 'warning' : 'error'"
        icon="i-lucide-wifi-off"
        :title="items.length ? 'Falha ao atualizar a saúde' : 'Não foi possível carregar a saúde'"
        :description="loadError"
        class="mb-4"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => load(true) }]"
      />

      <UEmpty
        v-if="!loading && !loadError && !items.length"
        icon="i-lucide-circle-check"
        title="Nenhum problema operacional"
        description="A inbox está vazia. Cursores, certificados e backup não exigem atenção no momento."
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
          th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
          td: 'border-b border-default',
          separator: 'h-0'
        }"
        @select="selectRow"
      >
        <template #severity-cell="{ row }">
          <UBadge
            :color="severityColor(row.original.severity)"
            variant="subtle"
            class="capitalize"
          >
            {{ row.original.severity }}
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
