<script setup lang="ts">
import type { TableColumn, TableRow } from '@nuxt/ui'
import type { SyncRun } from '~/types/api'

const api = useApi()
const items = ref<SyncRun[]>([])
const cursor = ref<string | null>(null)
const loading = ref(false)
const loadError = ref<string | null>(null)
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
    loadError.value = null
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao carregar sincronizações.')
    toast.add({ title: loadError.value, color: 'error' })
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

/** Bloqueio é sinalizado no status/mensagem — nunca oferecer salto de NSU. */
function isBlocked(run: SyncRun) {
  const message = (run.error_message || '').toLowerCase()
  return run.status === 'FAILED' && (message.includes('bloque') || message.includes('block'))
}

onMounted(() => load(true))
</script>

<template>
  <UDashboardPanel id="syncs">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Sincronizações">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UTooltip text="Atualizar histórico">
            <UButton
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="ghost"
              square
              aria-label="Atualizar histórico de sincronizações"
              :loading="loading"
              @click="load(true)"
            />
          </UTooltip>
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UAlert
        icon="i-lucide-info"
        title="Sincronização por NSU (multi-canal)"
        description="O cursor só avança após a persistência integral da página. Canais independentes: ADN (NFS-e), DistDFe NF-e, CT-e e MDF-e — cada um com NSU próprio. Não há salto manual. Bloqueios SEFAZ (ex. cStat 656) aparecem na inbox de operações e não param o ADN."
      />

      <UAlert
        v-if="loadError"
        :color="items.length ? 'warning' : 'error'"
        icon="i-lucide-wifi-off"
        :title="items.length ? 'Falha ao atualizar sincronizações' : 'Não foi possível carregar sincronizações'"
        :description="loadError"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => load(true) }]"
      />

      <UTable
        data-testid="data-table"
        :data="items"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
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
        <template #status-cell="{ row }">
          <div class="flex flex-wrap items-center gap-2">
            <AppStatusBadge :status="row.original.status" />
            <UBadge
              v-if="isBlocked(row.original)"
              color="error"
              variant="subtle"
              icon="i-lucide-ban"
            >
              Cursor bloqueado
            </UBadge>
          </div>
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
        v-if="!loading && !loadError && !items.length"
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

      <USlideover
        v-model:open="detailOpen"
        :title="selected ? `Execução #${selected.id}` : 'Execução'"
      >
        <template #body>
          <div v-if="selected" class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <AppStatusBadge :status="selected.status" />
              <UBadge color="neutral" variant="subtle">
                {{ statusLabel(selected.trigger) }}
              </UBadge>
            </div>

            <UAlert
              v-if="isBlocked(selected)"
              color="error"
              icon="i-lucide-ban"
              title="Cursor bloqueado"
              description="Falhas consecutivas de decodificação bloquearam o avanço. Não há ação de salto ou edição manual de NSU."
            />

            <dl class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-muted">
                  Origem
                </dt>
                <dd class="text-highlighted">
                  {{ statusLabel(selected.trigger) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Resultado
                </dt>
                <dd class="text-highlighted">
                  {{ statusLabel(selected.status) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Início
                </dt>
                <dd class="text-highlighted">
                  {{ formatDateTime(selected.started_at) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Fim
                </dt>
                <dd class="text-highlighted">
                  {{ formatDateTime(selected.finished_at) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  NSU inicial
                </dt>
                <dd class="font-mono text-highlighted">
                  {{ selected.from_nsu }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  NSU final
                </dt>
                <dd class="font-mono text-highlighted">
                  {{ selected.to_nsu }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Páginas
                </dt>
                <dd class="text-highlighted">
                  {{ selected.pages_processed }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Documentos
                </dt>
                <dd class="text-highlighted">
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
