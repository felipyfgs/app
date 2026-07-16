<script setup lang="ts">
/**
 * Lista admin de Offices (inclui pendentes).
 * Arquétipo lista: customers.vue / serpro contracts.
 */
import type { TableColumn } from '@nuxt/ui'
import type { PlatformOfficeAdminSummary } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import { apiErrorMessage } from '~/utils/api-error'

const api = useApi()
const { sessionEpoch, canAccessPlatformAdmin } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rows = ref<PlatformOfficeAdminSummary[]>([])
const q = ref('')
const lifecycleFilter = ref('')

const filterItems = [
  { label: 'Todos', value: '' },
  { label: 'Pendentes', value: 'PENDING_ACTIVATION' },
  { label: 'Ativos', value: 'ACTIVE' }
]

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return rows.value
  return rows.value.filter(o =>
    o.name.toLowerCase().includes(term)
    || o.slug.toLowerCase().includes(term)
    || String(o.id).includes(term)
  )
})

const columns: TableColumn<PlatformOfficeAdminSummary>[] = [
  { accessorKey: 'id', header: 'ID', meta: { class: { th: 'w-16', td: 'w-16' } } },
  { accessorKey: 'name', header: 'Nome' },
  { accessorKey: 'slug', header: 'Slug' },
  { id: 'lifecycle', header: 'Estado' },
  {
    id: 'plan',
    header: 'Plano',
    cell: ({ row }) => row.original.subscription?.plan || '—'
  },
  {
    id: 'activation',
    header: 'Ativação',
    cell: ({ row }) => {
      const a = row.original.activation
      if (!a) return '—'
      return `${methodLabel(String(a.method))} · ${a.status}`
    }
  }
]

function lifecycleLabel(status: string): string {
  if (status === 'PENDING_ACTIVATION') return 'Pendente'
  if (status === 'ACTIVE') return 'Ativo'
  return status
}

function methodLabel(method: string): string {
  if (method === 'MANUAL_LINK') return 'Link'
  if (method === 'TEMPORARY_PASSWORD') return 'Senha'
  return method
}

function lifecycleColor(status: string): 'warning' | 'success' | 'neutral' {
  if (status === 'PENDING_ACTIVATION') return 'warning'
  if (status === 'ACTIVE') return 'success'
  return 'neutral'
}

let loadSeq = 0

async function load() {
  if (!canAccessPlatformAdmin.value) return
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.offices.adminList({
      lifecycle_status: lifecycleFilter.value || undefined
    })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = res.data || []
  } catch (e) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = []
    loadError.value = apiErrorMessage(e, 'Falha ao listar escritórios.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

watch(lifecycleFilter, () => {
  void load()
})
watch(sessionEpoch, () => {
  rows.value = []
  void load()
})
onMounted(load)
</script>

<template>
  <UDashboardPanel
    id="admin-offices"
    data-testid="admin-offices-panel"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        title="Escritórios"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            to="/admin/offices/new"
            icon="i-lucide-plus"
            label="Novo escritório"
            data-testid="admin-offices-new"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <DashboardContent width="wide" class="gap-4">
        <div class="flex flex-wrap items-center gap-2">
          <UInput
            v-model="q"
            icon="i-lucide-search"
            placeholder="Buscar por nome, slug ou id…"
            class="w-full sm:max-w-sm"
            data-testid="admin-offices-search"
          />
          <USelect
            v-model="lifecycleFilter"
            :items="filterItems"
            value-key="value"
            label-key="label"
            class="w-40"
            data-testid="admin-offices-filter"
          />
          <UButton
            color="neutral"
            variant="soft"
            icon="i-lucide-refresh-cw"
            label="Atualizar"
            :loading="loading"
            @click="() => { void load() }"
          />
        </div>

        <UAlert
          v-if="loadError"
          color="error"
          icon="i-lucide-circle-x"
          :title="loadError"
          :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
        />

        <div
          v-else-if="loading && !rows.length"
          class="space-y-2"
        >
          <USkeleton class="h-10 w-full" />
          <USkeleton class="h-10 w-full" />
          <USkeleton class="h-10 w-2/3" />
        </div>

        <UEmpty
          v-else-if="!filtered.length"
          icon="i-lucide-building-2"
          title="Nenhum escritório"
          description="Crie o primeiro escritório pendente de ativação."
          data-testid="admin-offices-empty"
        />

        <div
          v-else
          class="overflow-x-auto"
        >
          <UTable
            :data="filtered"
            :columns="columns"
            :ui="DASHBOARD_TABLE_UI"
            class="min-w-full"
            data-testid="admin-offices-table"
          >
            <template #name-cell="{ row }">
              <NuxtLink
                :to="`/admin/offices/${row.original.id}`"
                class="font-medium text-primary hover:underline"
              >
                {{ row.original.name }}
              </NuxtLink>
            </template>
            <template #lifecycle-cell="{ row }">
              <UBadge
                size="sm"
                variant="subtle"
                :color="lifecycleColor(row.original.lifecycle_status)"
                :label="lifecycleLabel(row.original.lifecycle_status)"
              />
            </template>
          </UTable>
        </div>
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
