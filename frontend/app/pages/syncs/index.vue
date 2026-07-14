<script setup lang="ts">
import type { TableColumn, TableRow } from '@nuxt/ui'
import type { SyncRun } from '~/types/api'

const api = useApi()
const items = ref<SyncRun[]>([])
const cursor = ref<string | null>(null)
const loading = ref(false)
const selected = ref<SyncRun | null>(null)
const detailOpen = ref(false)
const toast = useToast()

const columns: TableColumn<SyncRun>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'status', header: 'Resultado' },
  { accessorKey: 'trigger', header: 'Origem' },
  {
    accessorKey: 'pages_processed',
    header: 'Páginas',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'documents_persisted',
    header: 'Documentos',
    meta: { class: { th: 'hidden sm:table-cell', td: 'hidden sm:table-cell' } }
  },
  {
    accessorKey: 'started_at',
    header: 'Início',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  { id: 'actions', header: '' }
]

async function load(reset = false) {
  if (reset) {
    cursor.value = null
  }
  loading.value = true
  try {
    const response = await api.sync.history({
      limit: 50,
      ...(cursor.value ? { cursor: cursor.value } : {})
    })
    items.value = reset ? response.data : [...items.value, ...response.data]
    cursor.value = response.meta.next_cursor
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Erro ao carregar sincronizações.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

function openDetail(run: SyncRun) {
  selected.value = run
  detailOpen.value = true
}

function selectRow(_event: Event, row: TableRow<SyncRun>) {
  openDetail(row.original)
}

onMounted(() => load(true))
</script>

<template>
  <UDashboardPanel id="syncs">
    <template #header>
      <UDashboardNavbar title="Sincronizações">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="ghost"
            square
            :loading="loading"
            @click="load(true)"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UAlert
        icon="i-lucide-info"
        title="Sincronização por NSU"
        description="O cursor só avança após a persistência integral da página. Falhas exibidas abaixo são sanitizadas."
      />

      <UTable
        :data="items"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
        @select="selectRow"
      >
        <template #status-cell="{ row }">
          <AppStatusBadge :status="row.original.status" />
        </template>
        <template #trigger-cell="{ row }">
          {{ statusLabel(row.original.trigger) }}
        </template>
        <template #started_at-cell="{ row }">
          {{ formatDateTime(row.original.started_at || row.original.created_at) }}
        </template>
        <template #actions-cell="{ row }">
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-eye"
            square
            aria-label="Ver detalhes da execução"
            @click.stop="openDetail(row.original)"
          />
        </template>
      </UTable>

      <UEmpty
        v-if="!loading && !items.length"
        icon="i-lucide-history"
        title="Nenhuma execução registrada"
        description="O histórico aparecerá após a primeira sincronização automática ou manual."
      />

      <div v-if="cursor" class="flex justify-center border-t border-default pt-4">
        <UButton
          :loading="loading"
          color="neutral"
          variant="subtle"
          label="Carregar mais"
          @click="load(false)"
        />
      </div>

      <USlideover v-model:open="detailOpen" :title="selected ? `Execução #${selected.id}` : 'Execução'">
        <template #body>
          <div v-if="selected" class="space-y-5">
            <div class="flex items-center justify-between">
              <AppStatusBadge :status="selected.status" />
              <UBadge color="neutral" variant="subtle">
                {{ statusLabel(selected.trigger) }}
              </UBadge>
            </div>
            <dl class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-muted">
                  Início
                </dt><dd class="text-highlighted">
                  {{ formatDateTime(selected.started_at) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Fim
                </dt><dd class="text-highlighted">
                  {{ formatDateTime(selected.finished_at) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  NSU inicial
                </dt><dd class="font-mono text-highlighted">
                  {{ selected.from_nsu }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  NSU final
                </dt><dd class="font-mono text-highlighted">
                  {{ selected.to_nsu }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Páginas
                </dt><dd class="text-highlighted">
                  {{ selected.pages_processed }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Documentos
                </dt><dd class="text-highlighted">
                  {{ selected.documents_persisted }}
                </dd>
              </div>
            </dl>
            <UAlert
              v-if="selected.error_message"
              color="error"
              icon="i-lucide-circle-x"
              title="Falha registrada"
              :description="selected.error_message"
            />
            <p class="text-xs text-muted">
              Respostas remotas, XML, PFX, senha e material criptográfico não são exibidos no histórico.
            </p>
          </div>
        </template>
      </USlideover>
    </template>
  </UDashboardPanel>
</template>
