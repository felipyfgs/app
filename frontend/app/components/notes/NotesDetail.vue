<script setup lang="ts">
/**
 * Detalhe da NFS-e — organização tipo documento fiscal:
 * header (InboxMail) → resumo valor/status → partes → dados fiscais →
 * documento original (tabela metadados) → eventos → alertas.
 *
 * Componentes: UDashboardNavbar, UPageCard, UTable, UBadge (Nuxt UI 4).
 */
import type { TableColumn } from '@nuxt/ui'
import type { NfseEvent, NfseNote, NoteDetail } from '~/types/api'

const props = defineProps<{
  accessKey: string
  /** Metadados opcionais da lista enquanto o detalhe carrega. */
  preview?: NfseNote | null
  showClose?: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const api = useApi()
const toast = useToast()
const detail = ref<NoteDetail | null>(null)
/** true só quando não há nada exibível para a chave atual (evita “piscar” ao trocar de nota). */
const loading = ref(false)
const refreshing = ref(false)
const notFound = ref(false)
/** Gera respostas fora de ordem quando o usuário clica rápido no catálogo. */
let loadGeneration = 0

/**
 * Prefere detalhe/preview da MESMA access_key.
 * Sem isso, o detail da nota anterior cobre o preview da nova e a UI
 * mostra documento A → skeleton/vazio → documento B (efeito de piscar).
 */
const note = computed(() => {
  const key = props.accessKey
  const fromDetail = detail.value?.note
  if (fromDetail?.access_key === key) {
    return fromDetail
  }
  if (props.preview?.access_key === key) {
    return props.preview
  }
  return null
})
const documentMeta = computed(() => {
  if (detail.value?.note?.access_key === props.accessKey) {
    return detail.value.document || detail.value.note.document || null
  }
  return note.value?.document || null
})
const events = computed(() => {
  if (detail.value?.note?.access_key === props.accessKey) {
    return detail.value.events || []
  }
  return []
})

const shortTitle = computed(() => {
  const key = props.accessKey
  if (key.length <= 22) return key
  return `${key.slice(0, 10)}…${key.slice(-8)}`
})

type MetaRow = { field: string, value: string, mono?: boolean }

const partyRows = computed<MetaRow[]>(() => {
  const n = note.value
  if (!n) return []
  return [
    { field: 'Emitente', value: n.issuer_cnpj || '—', mono: true },
    { field: 'Tomador', value: n.taker_cnpj || '—', mono: true },
    { field: 'Intermediário', value: n.intermediary_cnpj || '—', mono: true }
  ]
})

const fiscalRows = computed<MetaRow[]>(() => {
  const n = note.value
  if (!n) return []
  return [
    { field: 'Papel fiscal', value: statusLabel(n.fiscal_role) },
    { field: 'Competência', value: n.competence || '—' },
    { field: 'Emissão', value: formatDateTime(n.issued_at) },
    { field: 'Valor do serviço', value: formatCurrency(n.service_amount) }
  ]
})

const documentRows = computed<MetaRow[]>(() => {
  const d = documentMeta.value
  if (!d) {
    return [{ field: 'Documento', value: 'Metadados ainda não disponíveis' }]
  }
  return [
    { field: 'Tipo', value: d.document_type || '—' },
    { field: 'Versão do schema', value: d.schema_version || 'Desconhecida' },
    { field: 'Tamanho', value: formatBytes(d.byte_size) },
    { field: 'Parse', value: statusLabel(d.parse_status) },
    { field: 'SHA-256', value: d.sha256 || '—', mono: true }
  ]
})

const metaColumns: TableColumn<MetaRow>[] = [
  {
    accessorKey: 'field',
    header: 'Campo',
    meta: { class: { th: 'w-40', td: 'w-40 align-top' } }
  },
  {
    accessorKey: 'value',
    header: 'Valor'
  }
]

const eventColumns: TableColumn<NfseEvent>[] = [
  {
    accessorKey: 'event_type',
    header: 'Tipo',
    meta: { class: { th: 'w-36', td: 'w-36' } }
  },
  {
    accessorKey: 'event_at',
    header: 'Quando',
    meta: { class: { th: 'w-40', td: 'w-40' } }
  },
  {
    accessorKey: 'status',
    header: 'Situação'
  }
]

const tableUi = {
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'py-1.5 px-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r text-xs',
  td: 'border-b border-default py-1.5 px-2 text-sm',
  separator: 'h-0'
}

async function copyText(label: string, value?: string | null) {
  const text = (value || '').trim()
  if (!text || text === '—') return
  try {
    await navigator.clipboard.writeText(text)
    toast.add({ title: `${label} copiado`, color: 'success' })
  } catch {
    toast.add({ title: `Não foi possível copiar ${label.toLowerCase()}`, color: 'error' })
  }
}

async function load() {
  const key = props.accessKey
  const generation = ++loadGeneration
  notFound.value = false

  const hasMatchingDetail = detail.value?.note?.access_key === key
  const hasMatchingPreview = props.preview?.access_key === key

  // Descarta detalhe de outra nota para não “vazar” dados na UI.
  if (!hasMatchingDetail) {
    detail.value = null
  }

  // Skeleton só quando não há preview/lista nem detalhe da chave atual.
  if (!hasMatchingDetail && !hasMatchingPreview) {
    loading.value = true
  } else {
    refreshing.value = true
  }

  try {
    const data = (await api.notes.get(key)).data
    if (generation !== loadGeneration) {
      return
    }
    detail.value = data
  } catch (caught) {
    if (generation !== loadGeneration) {
      return
    }
    detail.value = null
    notFound.value = true
    toast.add({
      title: apiErrorMessage(caught, 'Nota não encontrada.'),
      color: 'error'
    })
  } finally {
    if (generation === loadGeneration) {
      loading.value = false
      refreshing.value = false
    }
  }
}

watch(() => props.accessKey, load, { immediate: true })
</script>

<template>
  <!--
    Estrutura InboxMail: navbar + faixa de contexto + body scrollável.
    Conteúdo em seções de documento fiscal (partes / fiscal / XML / eventos).
  -->
  <div
    data-testid="note-detail"
    class="flex h-full min-h-0 min-w-0 flex-1 flex-col border-l border-default bg-default"
  >
    <UDashboardNavbar :title="shortTitle" :toggle="false">
      <template #leading>
        <UButton
          v-if="showClose"
          icon="i-lucide-x"
          color="neutral"
          variant="ghost"
          class="-ms-1.5"
          aria-label="Fechar detalhe da nota"
          @click="emit('close')"
        />
      </template>
      <template #trailing>
        <UBadge
          v-if="note"
          color="neutral"
          variant="subtle"
          class="hidden font-mono text-xs sm:inline-flex"
        >
          {{ note.access_key.slice(0, 12) }}…
        </UBadge>
      </template>
      <template #right>
        <UTooltip text="Copiar chave de acesso">
          <UButton
            icon="i-lucide-copy"
            color="neutral"
            variant="ghost"
            square
            aria-label="Copiar chave de acesso"
            @click="copyText('Chave', accessKey)"
          />
        </UTooltip>
        <UTooltip v-if="note" text="Baixar XML original">
          <UButton
            :href="api.notes.xmlUrl(note.access_key)"
            external
            download
            icon="i-lucide-download"
            color="neutral"
            variant="ghost"
            square
            aria-label="Baixar XML original da nota"
          />
        </UTooltip>
      </template>
    </UDashboardNavbar>

    <!-- Faixa de contexto (como from/date do InboxMail) -->
    <div
      v-if="note"
      class="flex flex-col gap-3 border-b border-default p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
    >
      <div class="flex flex-wrap items-center gap-2">
        <UBadge color="info" variant="subtle" icon="i-lucide-briefcase">
          {{ statusLabel(note.fiscal_role) }}
        </UBadge>
        <AppStatusBadge :status="note.status" />
        <UBadge
          v-if="documentMeta?.parse_status"
          :color="documentMeta.parse_status === 'OK' ? 'success' : 'warning'"
          variant="subtle"
          icon="i-lucide-file-code-2"
        >
          XML {{ statusLabel(documentMeta.parse_status) }}
        </UBadge>
      </div>
      <div class="text-right">
        <p class="text-xs text-muted">
          Valor do serviço
        </p>
        <p class="text-xl font-semibold tabular-nums text-highlighted">
          {{ formatCurrency(note.service_amount) }}
        </p>
      </div>
    </div>

    <div
      class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4 sm:p-6"
      :class="refreshing && note ? 'opacity-80 transition-opacity' : ''"
    >
      <div v-if="loading && !note" class="space-y-4">
        <USkeleton class="h-20 w-full" />
        <USkeleton class="h-40 w-full" />
        <USkeleton class="h-32 w-full" />
      </div>

      <UEmpty
        v-else-if="notFound && !note"
        icon="i-lucide-file-x"
        title="Nota não encontrada"
        description="A chave não existe ou pertence a outro escritório."
      >
        <UButton label="Voltar ao catálogo" @click="emit('close')" />
      </UEmpty>

      <template v-else-if="note">
        <!-- Partes (CNPJs) -->
        <section>
          <div class="mb-2 flex items-center gap-2">
            <UIcon name="i-lucide-users" class="size-4 text-muted" />
            <h3 class="text-sm font-semibold text-highlighted">
              Partes
            </h3>
          </div>
          <UTable
            :data="partyRows"
            :columns="metaColumns"
            class="shrink-0"
            :ui="tableUi"
          >
            <template #value-cell="{ row }">
              <button
                v-if="row.original.mono && row.original.value !== '—'"
                type="button"
                class="group inline-flex max-w-full items-center gap-1 font-mono text-xs text-highlighted hover:text-primary"
                @click="copyText(row.original.field, row.original.value)"
              >
                <span class="truncate">{{ row.original.value }}</span>
                <UIcon name="i-lucide-copy" class="size-3 shrink-0 opacity-0 group-hover:opacity-70" />
              </button>
              <span v-else :class="row.original.mono ? 'font-mono text-xs' : ''">
                {{ row.original.value }}
              </span>
            </template>
          </UTable>
        </section>

        <!-- Dados fiscais -->
        <section>
          <div class="mb-2 flex items-center gap-2">
            <UIcon name="i-lucide-receipt" class="size-4 text-muted" />
            <h3 class="text-sm font-semibold text-highlighted">
              Dados fiscais
            </h3>
          </div>
          <UTable
            :data="fiscalRows"
            :columns="metaColumns"
            class="shrink-0"
            :ui="tableUi"
          />
        </section>

        <!-- Documento original (metadados + ação) -->
        <section>
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-file-code-2" class="size-4 text-muted" />
              <h3 class="text-sm font-semibold text-highlighted">
                Documento original
              </h3>
            </div>
            <UButton
              :href="api.notes.xmlUrl(note.access_key)"
              external
              download
              size="xs"
              color="primary"
              variant="soft"
              icon="i-lucide-download"
              label="Baixar XML"
            />
          </div>
          <UTable
            :data="documentRows"
            :columns="metaColumns"
            class="shrink-0"
            :ui="tableUi"
          >
            <template #value-cell="{ row }">
              <button
                v-if="row.original.mono && row.original.value !== '—'"
                type="button"
                class="group inline-flex max-w-full items-center gap-1 break-all text-left font-mono text-[11px] leading-snug text-highlighted hover:text-primary"
                @click="copyText(row.original.field, row.original.value)"
              >
                <span class="break-all">{{ row.original.value }}</span>
                <UIcon name="i-lucide-copy" class="size-3 shrink-0 opacity-0 group-hover:opacity-70" />
              </button>
              <span
                v-else
                :class="row.original.mono ? 'break-all font-mono text-[11px]' : ''"
              >
                {{ row.original.value }}
              </span>
            </template>
          </UTable>
        </section>

        <!-- Eventos (se houver) -->
        <section v-if="events.length">
          <div class="mb-2 flex items-center gap-2">
            <UIcon name="i-lucide-history" class="size-4 text-muted" />
            <h3 class="text-sm font-semibold text-highlighted">
              Eventos
            </h3>
            <UBadge color="neutral" variant="subtle" size="sm">
              {{ events.length }}
            </UBadge>
          </div>
          <UTable
            :data="events"
            :columns="eventColumns"
            class="shrink-0"
            :ui="tableUi"
          >
            <template #event_type-cell="{ row }">
              <span class="text-xs font-medium">
                {{ statusLabel(row.original.event_type) }}
              </span>
            </template>
            <template #event_at-cell="{ row }">
              <span class="text-xs text-muted">
                {{ formatDateTime(row.original.event_at) }}
              </span>
            </template>
            <template #status-cell="{ row }">
              <AppStatusBadge v-if="row.original.status" :status="row.original.status" />
              <span v-else class="text-xs text-muted">—</span>
            </template>
          </UTable>
        </section>

        <UAlert
          v-if="documentMeta?.parse_status && documentMeta.parse_status !== 'OK'"
          color="warning"
          variant="subtle"
          icon="i-lucide-file-warning"
          title="XML preservado para revisão"
          :description="documentMeta.parse_alert || 'A versão ou o XSD ainda não é reconhecido. O XML original permanece disponível para download.'"
        />
        <UAlert
          color="neutral"
          variant="subtle"
          icon="i-lucide-shield-check"
          title="Download auditado"
          description="O XML sai do cofre e o download é registrado. O conteúdo bruto não é renderizado nesta tela."
        />
      </template>
    </div>
  </div>
</template>
