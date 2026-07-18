<script setup lang="ts">
/**
 * Lista admin de Offices (inclui pendentes).
 * Arquétipo lista: customers.vue / serpro contracts.
 */
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { PlatformOfficeAdminSummary } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import { apiErrorMessage } from '~/utils/api-error'

const api = useApi()
const { sessionEpoch, canAccessPlatformAdmin } = useDashboard()
const { selectOffice, switching: switchingOffice } = usePlatformOfficeSelect()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rows = ref<PlatformOfficeAdminSummary[]>([])
const q = ref('')
const actionOfficeId = ref<number | null>(null)
// Reka/USelect proíbe value "" nos items (reserva para limpar seleção).
const lifecycleFilter = ref('all')

const filterItems = [
  { label: 'Todos', value: 'all' },
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

const hasFilters = computed(() => Boolean(q.value.trim()) || lifecycleFilter.value !== 'all')

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
      if (!a) return row.original.lifecycle_status === 'ACTIVE' ? 'Não se aplica' : '—'
      return `${methodLabel(String(a.method))} · ${a.status}`
    }
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    meta: {
      class: {
        th: 'w-16 text-right',
        td: 'w-16 text-right'
      }
    }
  }
]

function officeDetailPath(office: PlatformOfficeAdminSummary): string {
  return `/admin/offices/${office.id}`
}

async function manageOffice(office: PlatformOfficeAdminSummary) {
  actionOfficeId.value = office.id
  try {
    await selectOffice(office.id, '/settings')
  } finally {
    actionOfficeId.value = null
  }
}

function rowActions(office: PlatformOfficeAdminSummary): DropdownMenuItem[][] {
  if (office.lifecycle_status === 'PENDING_ACTIVATION') {
    return [[{
      label: 'Gerenciar ativação',
      icon: 'i-lucide-user-check',
      to: officeDetailPath(office)
    }]]
  }

  const details: DropdownMenuItem = {
    label: 'Ver detalhes',
    icon: 'i-lucide-eye',
    to: officeDetailPath(office)
  }

  if (office.lifecycle_status !== 'ACTIVE') {
    return [[details]]
  }

  return [[{
    label: 'Gerenciar escritório',
    icon: 'i-lucide-settings-2',
    disabled: switchingOffice.value,
    onSelect: () => {
      void manageOffice(office)
    }
  }], [details]]
}

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

function clearFilters() {
  q.value = ''
  lifecycleFilter.value = 'all'
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
      lifecycle_status: lifecycleFilter.value === 'all' ? undefined : lifecycleFilter.value
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
      <div
        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        data-testid="admin-offices-toolbar"
      >
        <UInput
          v-model="q"
          icon="i-lucide-search"
          placeholder="Buscar escritórios…"
          class="w-full sm:max-w-sm"
          aria-label="Buscar escritórios"
          data-testid="admin-offices-search"
        />

        <div class="flex items-center gap-2">
          <USelect
            v-model="lifecycleFilter"
            :items="filterItems"
            value-key="value"
            label-key="label"
            class="min-w-0 flex-1 sm:w-40 sm:flex-none"
            aria-label="Filtrar por estado"
            data-testid="admin-offices-filter"
          />
          <UButton
            color="neutral"
            variant="outline"
            icon="i-lucide-refresh-cw"
            label="Atualizar"
            :loading="loading"
            @click="() => { void load() }"
          />
        </div>
      </div>

      <UAlert
        v-if="loadError"
        color="error"
        icon="i-lucide-circle-x"
        :title="loadError"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
      />

      <UEmpty
        v-else-if="!loading && !filtered.length"
        icon="i-lucide-building-2"
        :title="hasFilters ? 'Nenhum resultado' : 'Nenhum escritório'"
        data-testid="admin-offices-empty"
      >
        <template #actions>
          <UButton
            v-if="hasFilters"
            color="neutral"
            variant="outline"
            icon="i-lucide-rotate-ccw"
            label="Limpar filtros"
            @click="clearFilters"
          />
          <UButton
            v-else
            to="/admin/offices/new"
            icon="i-lucide-plus"
            label="Novo escritório"
          />
        </template>
      </UEmpty>

      <div
        v-else
        class="overflow-x-auto"
      >
        <UTable
          :data="filtered"
          :columns="columns"
          :loading="loading"
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
          <template #actions-cell="{ row }">
            <div class="flex justify-end">
              <UDropdownMenu
                :items="rowActions(row.original)"
                :content="{ align: 'end' }"
              >
                <UButton
                  icon="i-lucide-ellipsis-vertical"
                  color="neutral"
                  variant="ghost"
                  size="sm"
                  square
                  :loading="switchingOffice && actionOfficeId === row.original.id"
                  :disabled="switchingOffice && actionOfficeId !== row.original.id"
                  :aria-label="`Ações de ${row.original.name}`"
                  data-testid="admin-office-row-actions"
                />
              </UDropdownMenu>
            </div>
          </template>
        </UTable>
      </div>
    </template>
  </UDashboardPanel>
</template>
