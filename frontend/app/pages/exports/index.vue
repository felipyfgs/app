<script setup lang="ts">
/**
 * Lista de exportações ZIP — arquétipo lista admin (customers.vue) + modal form (AddModal).
 * Objetivo UX: linguagem de escritório, escopo legível e próximos passos óbvios.
 */
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'
import type { ExportFilters, ExportJob } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const route = useRoute()
const router = useRouter()
const { canCreateExport } = useDashboard()
const toast = useToast()

const items = ref<ExportJob[]>([])
const page = ref(Math.max(1, Number(route.query.page) || 1))
const perPage = 20
const total = ref(0)
const lastPage = ref(1)
const loading = ref(false)
const loadError = ref<string | null>(null)
const creating = ref(false)
const createOpen = ref(canCreateExport.value && route.query.new === '1')
const showAdvanced = ref(false)

const FILTER_ALL = 'all'

/** Presets de pedido — reduzem carga cognitiva do formulário. */
type ExportPreset = 'competence' | 'period' | 'single' | 'all'
const preset = ref<ExportPreset>('competence')

const schema = z.object({
  access_key: z.string().optional(),
  issuer_cnpj: z.string().optional(),
  taker_cnpj: z.string().optional(),
  competence: z.string().optional(),
  fiscal_role: z.string().optional(),
  status: z.string().optional(),
  direction: z.string().optional(),
  issued_from: z.string().optional(),
  issued_to: z.string().optional(),
  include_events: z.boolean().optional()
})

type Schema = z.output<typeof schema>

const state = reactive<Partial<Schema>>({
  access_key: '',
  issuer_cnpj: '',
  taker_cnpj: '',
  competence: defaultCompetence(),
  fiscal_role: FILTER_ALL,
  status: FILTER_ALL,
  direction: FILTER_ALL,
  issued_from: '',
  issued_to: '',
  include_events: false
})

const roleItems = [
  { label: 'Qualquer papel', value: FILTER_ALL },
  { label: 'Emitente', value: 'ISSUER' },
  { label: 'Tomador', value: 'TAKER' },
  { label: 'Intermediário', value: 'INTERMEDIARY' }
]
const statusItems = [
  { label: 'Qualquer situação', value: FILTER_ALL },
  { label: 'Autorizada', value: 'AUTHORIZED' },
  { label: 'Cancelada', value: 'CANCELLED' },
  { label: 'Em revisão', value: 'UNKNOWN' }
]
const directionItems = [
  { label: 'Entrada e saída', value: FILTER_ALL },
  { label: 'Somente saídas', value: 'OUT' },
  { label: 'Somente entradas', value: 'IN' }
]

const presetItems = [
  {
    value: 'competence' as const,
    label: 'Por competência',
    description: 'Mês fiscal (ex.: 2026-07) — o mais comum no fechamento.',
    icon: 'i-lucide-calendar'
  },
  {
    value: 'period' as const,
    label: 'Por período de emissão',
    description: 'Intervalo de datas de emissão.',
    icon: 'i-lucide-calendar-range'
  },
  {
    value: 'single' as const,
    label: 'Uma nota (chave)',
    description: 'Só a chave de acesso informada.',
    icon: 'i-lucide-key-round'
  },
  {
    value: 'all' as const,
    label: 'Tudo do escritório',
    description: 'Sem filtro de data — pode gerar ZIP grande.',
    icon: 'i-lucide-layers'
  }
]

const columns: TableColumn<ExportJob>[] = [
  {
    id: 'when',
    header: 'Solicitado em',
    meta: { class: { th: 'w-36', td: 'w-36' } }
  },
  {
    accessorKey: 'status',
    header: 'Situação'
  },
  {
    id: 'scope',
    header: 'O que entra no ZIP'
  },
  {
    id: 'package',
    header: 'Pacote',
    meta: { class: { th: 'hidden sm:table-cell', td: 'hidden sm:table-cell' } }
  },
  {
    id: 'actions',
    header: '',
    meta: { class: { th: 'w-40', td: 'w-40' } }
  }
]

const hasPending = computed(() =>
  items.value.some(item => ['PENDING', 'PROCESSING'].includes(item.status))
)

const counts = computed(() => {
  let ready = 0
  let queue = 0
  let failed = 0
  let expired = 0
  for (const job of items.value) {
    if (isExpired(job)) {
      expired++
    } else if (job.status === 'READY') {
      ready++
    } else if (['PENDING', 'PROCESSING'].includes(job.status)) {
      queue++
    } else if (job.status === 'FAILED') {
      failed++
    }
  }
  return { ready, queue, failed, expired, total: items.value.length }
})

function defaultCompetence(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function isExpired(job: ExportJob) {
  if (job.status === 'EXPIRED') return true
  if (job.status === 'READY' && job.expires_at) {
    return new Date(job.expires_at).getTime() < Date.now()
  }
  return false
}

function canDownload(job: ExportJob) {
  return job.status === 'READY' && !isExpired(job)
}

function effectiveStatus(job: ExportJob): string {
  if (isExpired(job) && job.status === 'READY') return 'EXPIRED'
  return job.status
}

function statusHint(job: ExportJob): string {
  const s = effectiveStatus(job)
  switch (s) {
    case 'PENDING':
      return 'Na fila — o servidor monta o ZIP em breve.'
    case 'PROCESSING':
      return 'Gerando arquivos… esta lista atualiza sozinha.'
    case 'READY':
      return job.expires_at
        ? `Pronto para baixar até ${formatDateTime(job.expires_at)}.`
        : 'Pronto para baixar.'
    case 'EXPIRED':
      return 'Link expirou (24h). Peça um ZIP novo com os mesmos filtros.'
    case 'FAILED':
      return job.error_message || 'Falha ao gerar. Tente de novo ou ajuste os filtros.'
    default:
      return ''
  }
}

const FILTER_LABELS: Record<string, string> = {
  competence: 'Competência',
  access_key: 'Chave',
  access_keys: 'Chaves',
  issuer_cnpj: 'Emitente',
  taker_cnpj: 'Tomador',
  fiscal_role: 'Papel',
  status: 'Situação',
  direction: 'Direção',
  issued_from: 'Emissão de',
  issued_to: 'Emissão até',
  client_id: 'Cliente',
  establishment_id: 'Estabelecimento',
  kind: 'Tipo',
  kinds: 'Tipos',
  monthly_export: 'Exportação mensal',
  readiness_status: 'Prontidão',
  absence_manifest_path: '' // interno — não exibir
}

function formatFilterValue(key: string, value: unknown): string {
  if (value === null || value === undefined || value === '') return ''
  if (key === 'fiscal_role') return statusLabel(String(value))
  if (key === 'status') return statusLabel(String(value))
  if (key === 'direction') {
    const d = String(value).toUpperCase()
    if (d === 'OUT') return 'Saídas'
    if (d === 'IN') return 'Entradas'
    return d
  }
  if (key === 'monthly_export') return value ? 'Sim' : ''
  if (key === 'readiness_status') {
    const map: Record<string, string> = {
      COMPLETE_KNOWN: 'Completo (conhecidos)',
      PARTIAL_CONFIRMED: 'Parcial confirmado',
      NOT_READY: 'Não pronto'
    }
    return map[String(value)] || String(value)
  }
  if (key === 'kind' || key === 'kinds') {
    const list = Array.isArray(value) ? value : [value]
    return list.map(v => String(v).toUpperCase()).join(', ')
  }
  if (key === 'access_key' || key === 'access_keys') {
    const keys = Array.isArray(value) ? value : [value]
    if (keys.length === 1) {
      const k = String(keys[0])
      return k.length > 14 ? `${k.slice(0, 6)}…${k.slice(-4)}` : k
    }
    return `${keys.length} chaves`
  }
  if (key === 'issuer_cnpj' || key === 'taker_cnpj') {
    return formatCnpj(String(value))
  }
  return String(value)
}

/** Escopo em português, sem `chave=valor` técnico. */
function scopeLines(job: ExportJob): string[] {
  const filters = (job.filters || {}) as Record<string, unknown>
  const lines: string[] = []

  if (filters.monthly_export) {
    lines.push('Fechamento mensal de saídas')
  }

  const order = [
    'competence',
    'direction',
    'issued_from',
    'issued_to',
    'issuer_cnpj',
    'taker_cnpj',
    'fiscal_role',
    'status',
    'access_key',
    'access_keys',
    'kind',
    'kinds',
    'readiness_status',
    'client_id',
    'establishment_id'
  ]

  for (const key of order) {
    if (!(key in filters)) continue
    const label = FILTER_LABELS[key]
    if (!label) continue
    const formatted = formatFilterValue(key, filters[key])
    if (!formatted) continue
    if (key === 'issued_from' && filters.issued_to) {
      lines.push(`Emissão: ${formatFilterValue('issued_from', filters.issued_from)} → ${formatFilterValue('issued_to', filters.issued_to)}`)
      // skip issued_to when already paired
      continue
    }
    if (key === 'issued_to' && filters.issued_from) continue
    lines.push(`${label}: ${formatted}`)
  }

  if (job.include_events) {
    lines.push('Inclui XMLs de eventos')
  }

  if (!lines.length) {
    return ['Todos os documentos do escritório (sem filtro)']
  }
  return lines
}

function scopeSummary(job: ExportJob): string {
  return scopeLines(job).join(' · ')
}

function applyPreset(next: ExportPreset) {
  preset.value = next
  // Limpa campos irrelevantes ao trocar o atalho
  if (next === 'competence') {
    state.access_key = ''
    state.issued_from = ''
    state.issued_to = ''
    if (!state.competence) state.competence = defaultCompetence()
  } else if (next === 'period') {
    state.access_key = ''
    state.competence = ''
  } else if (next === 'single') {
    state.competence = ''
    state.issued_from = ''
    state.issued_to = ''
    state.issuer_cnpj = ''
    state.taker_cnpj = ''
  } else if (next === 'all') {
    state.access_key = ''
    state.competence = ''
    state.issued_from = ''
    state.issued_to = ''
  }
}

async function load(silent = false) {
  if (!silent) loading.value = true
  try {
    const response = await api.exports.list({ page: page.value, per_page: perPage })
    items.value = response.data
    total.value = response.meta.total
    lastPage.value = response.meta.last_page
    await router.replace({
      query: { ...route.query, page: page.value > 1 ? String(page.value) : undefined }
    })
    loadError.value = null
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar exportações.')
    if (!silent) {
      toast.add({ title: loadError.value, color: 'error' })
    }
  } finally {
    loading.value = false
  }
}

function selectedFilters(): ExportFilters {
  const raw: Record<string, unknown> = { ...state }
  delete raw.include_events

  // Ajusta campos conforme preset (evita misturar competência + período sem querer)
  if (preset.value === 'competence') {
    delete raw.access_key
    delete raw.issued_from
    delete raw.issued_to
  } else if (preset.value === 'period') {
    delete raw.access_key
    delete raw.competence
  } else if (preset.value === 'single') {
    delete raw.competence
    delete raw.issued_from
    delete raw.issued_to
    delete raw.issuer_cnpj
    delete raw.taker_cnpj
  } else if (preset.value === 'all') {
    delete raw.access_key
    delete raw.competence
    delete raw.issued_from
    delete raw.issued_to
  }

  return Object.fromEntries(
    Object.entries(raw).filter(([, value]) =>
      value !== ''
      && value !== undefined
      && value !== null
      && value !== FILTER_ALL
    )
  ) as ExportFilters
}

function validateBeforeSubmit(): string | null {
  if (preset.value === 'competence' && !state.competence) {
    return 'Informe a competência (mês/ano).'
  }
  if (preset.value === 'period' && !state.issued_from && !state.issued_to) {
    return 'Informe pelo menos uma data de emissão.'
  }
  if (preset.value === 'single' && !String(state.access_key || '').trim()) {
    return 'Informe a chave de acesso da nota.'
  }
  return null
}

function resetForm() {
  preset.value = 'competence'
  showAdvanced.value = false
  state.access_key = ''
  state.issuer_cnpj = ''
  state.taker_cnpj = ''
  state.competence = defaultCompetence()
  state.fiscal_role = FILTER_ALL
  state.status = FILTER_ALL
  state.direction = FILTER_ALL
  state.issued_from = ''
  state.issued_to = ''
  state.include_events = false
}

async function onSubmit(_event: FormSubmitEvent<Schema>) {
  if (!canCreateExport.value || creating.value) return

  const validationError = validateBeforeSubmit()
  if (validationError) {
    toast.add({ title: validationError, color: 'warning' })
    return
  }

  creating.value = true
  try {
    await api.exports.create({
      filters: selectedFilters(),
      include_events: !!state.include_events
    })
    createOpen.value = false
    resetForm()
    await router.replace({ query: {} })
    toast.add({
      title: 'Exportação pedida',
      description: 'O ZIP está na fila. Quando ficar Disponível, o botão Baixar aparece na lista.',
      color: 'success'
    })
    if (page.value !== 1) {
      page.value = 1
    } else {
      await load()
    }
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao solicitar exportação.'), color: 'error' })
  } finally {
    creating.value = false
  }
}

function openCreate() {
  createOpen.value = true
}

const { pause, resume } = useIntervalFn(() => {
  if (hasPending.value) load(true)
}, 8000, { immediate: false })

watch(hasPending, pending => (pending ? resume() : pause()), { immediate: true })
watch(page, () => void load())
watch(createOpen, (open) => {
  if (!open) resetForm()
})
onMounted(load)
onBeforeUnmount(pause)
</script>

<template>
  <UDashboardPanel id="exports">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Exportações">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UTooltip text="Atualizar lista">
            <UButton
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="ghost"
              square
              aria-label="Atualizar lista de exportações"
              :loading="loading"
              @click="load()"
            />
          </UTooltip>
          <UButton
            v-if="canCreateExport"
            icon="i-lucide-plus"
            label="Pedir ZIP"
            class="hidden sm:inline-flex"
            @click="openCreate"
          />
          <UButton
            v-if="canCreateExport"
            icon="i-lucide-plus"
            square
            class="sm:hidden"
            aria-label="Pedir ZIP"
            @click="openCreate"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <!-- Como funciona — linguagem de escritório -->
      <UCard class="mb-4" data-testid="exports-how-it-works">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <p class="font-medium text-highlighted">
              Baixe um pacote ZIP com os XMLs do escritório
            </p>
            <p class="mt-1 text-sm text-muted">
              Você escolhe o recorte (competência, período ou uma nota), o sistema monta o ZIP
              e o arquivo fica disponível por <strong class="font-medium text-default">24 horas</strong>.
              Depois disso é preciso pedir de novo — não fica arquivo permanente aqui.
            </p>
          </div>
          <div class="flex flex-wrap gap-2 shrink-0">
            <UBadge color="neutral" variant="subtle" icon="i-lucide-list-ordered">
              1. Pedir
            </UBadge>
            <UBadge color="neutral" variant="subtle" icon="i-lucide-loader">
              2. Aguardar
            </UBadge>
            <UBadge color="neutral" variant="subtle" icon="i-lucide-download">
              3. Baixar
            </UBadge>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <UButton
            to="/closing"
            color="neutral"
            variant="soft"
            size="sm"
            icon="i-lucide-calendar-clock"
            label="Fechamento mensal de saídas"
          />
          <UButton
            to="/docs"
            color="neutral"
            variant="ghost"
            size="sm"
            icon="i-lucide-file-stack"
            label="Ver catálogo de documentos"
          />
        </div>
      </UCard>

      <!-- Resumo da lista -->
      <div
        v-if="items.length || loading"
        class="mb-4 grid grid-cols-2 sm:grid-cols-4 gap-2"
        data-testid="exports-stats"
      >
        <UCard :ui="{ body: 'p-3 sm:p-4' }">
          <p class="text-xs text-muted">
            Prontos p/ baixar
          </p>
          <p class="text-xl font-semibold tabular-nums text-success">
            {{ counts.ready }}
          </p>
        </UCard>
        <UCard :ui="{ body: 'p-3 sm:p-4' }">
          <p class="text-xs text-muted">
            Na fila / gerando
          </p>
          <p class="text-xl font-semibold tabular-nums text-warning">
            {{ counts.queue }}
          </p>
        </UCard>
        <UCard :ui="{ body: 'p-3 sm:p-4' }">
          <p class="text-xs text-muted">
            Expirados
          </p>
          <p class="text-xl font-semibold tabular-nums">
            {{ counts.expired }}
          </p>
        </UCard>
        <UCard :ui="{ body: 'p-3 sm:p-4' }">
          <p class="text-xs text-muted">
            Com falha
          </p>
          <p class="text-xl font-semibold tabular-nums" :class="counts.failed ? 'text-error' : ''">
            {{ counts.failed }}
          </p>
        </UCard>
      </div>

      <UAlert
        v-if="hasPending"
        class="mb-4"
        color="info"
        variant="subtle"
        icon="i-lucide-loader"
        title="Há pacotes sendo montados"
        description="A lista atualiza automaticamente a cada poucos segundos. Quando o status mudar para Disponível, use Baixar."
      />

      <UAlert
        v-if="loadError"
        class="mb-4"
        :color="items.length ? 'warning' : 'error'"
        icon="i-lucide-wifi-off"
        :title="items.length ? 'Falha ao atualizar a lista' : 'Não foi possível carregar exportações'"
        :description="loadError"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => load() }]"
      />

      <UTable
        v-if="loading || items.length"
        data-testid="data-table"
        :data="items"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
        :ui="{ ...DASHBOARD_TABLE_UI, td: `${DASHBOARD_TABLE_UI.td} align-top` }"
      >
        <template #when-cell="{ row }">
          <div class="text-sm">
            <p class="font-medium tabular-nums">
              {{ formatDateTime(row.original.created_at) }}
            </p>
            <p class="text-xs text-muted">
              #{{ row.original.id }}
            </p>
          </div>
        </template>

        <template #status-cell="{ row }">
          <div class="space-y-1 max-w-xs">
            <AppStatusBadge
              :status="effectiveStatus(row.original)"
              :label="statusLabel(effectiveStatus(row.original))"
            />
            <p class="text-xs text-muted leading-snug">
              {{ statusHint(row.original) }}
            </p>
          </div>
        </template>

        <template #scope-cell="{ row }">
          <ul class="text-sm space-y-0.5 max-w-md" :title="scopeSummary(row.original)">
            <li
              v-for="(line, i) in scopeLines(row.original).slice(0, 4)"
              :key="i"
              class="text-default"
            >
              {{ line }}
            </li>
            <li
              v-if="scopeLines(row.original).length > 4"
              class="text-xs text-muted"
            >
              +{{ scopeLines(row.original).length - 4 }} filtro(s)
            </li>
          </ul>
        </template>

        <template #package-cell="{ row }">
          <div class="text-sm tabular-nums">
            <template v-if="row.original.status === 'READY' || row.original.files_count">
              <p>
                {{ row.original.files_count ?? 0 }} arquivo(s)
              </p>
              <p class="text-xs text-muted">
                {{ formatBytes(row.original.byte_size) }}
              </p>
            </template>
            <span v-else class="text-muted">—</span>
          </div>
        </template>

        <template #actions-cell="{ row }">
          <div class="flex flex-col items-stretch gap-1">
            <UButton
              v-if="canDownload(row.original)"
              :href="api.exports.downloadUrl(row.original.id)"
              external
              download
              icon="i-lucide-download"
              label="Baixar ZIP"
              size="sm"
              block
              aria-label="Baixar pacote ZIP de exportação"
            />
            <UButton
              v-else-if="isExpired(row.original) && canCreateExport"
              color="neutral"
              variant="soft"
              size="sm"
              icon="i-lucide-refresh-cw"
              label="Pedir de novo"
              block
              @click="openCreate"
            />
            <UBadge
              v-else-if="['PENDING', 'PROCESSING'].includes(row.original.status)"
              color="warning"
              variant="subtle"
              class="justify-center"
            >
              Aguarde…
            </UBadge>
            <span
              v-else-if="row.original.status === 'FAILED'"
              class="text-xs text-error text-center"
            >
              Ver mensagem ao lado
            </span>
          </div>
        </template>
      </UTable>

      <UEmpty
        v-if="!loading && !loadError && !items.length"
        icon="i-lucide-package-open"
        title="Nenhum ZIP pedido ainda"
        description="Peça um pacote por competência (mais comum), por período ou por uma chave. O arquivo fica disponível por 24 horas."
      >
        <div class="flex flex-wrap justify-center gap-2">
          <UButton
            v-if="canCreateExport"
            label="Pedir primeiro ZIP"
            icon="i-lucide-plus"
            @click="openCreate"
          />
          <UButton
            to="/closing"
            color="neutral"
            variant="soft"
            label="Ir ao fechamento mensal"
            icon="i-lucide-calendar-clock"
          />
        </div>
      </UEmpty>

      <div
        v-if="total"
        class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-default pt-4"
      >
        <p class="text-sm text-muted">
          {{ total }} exportação(ões) · página {{ page }} de {{ lastPage }}
        </p>
        <UPagination
          v-if="lastPage > 1"
          v-model:page="page"
          :total="total"
          :items-per-page="perPage"
        />
      </div>

      <UModal
        v-if="canCreateExport"
        v-model:open="createOpen"
        title="Pedir pacote ZIP"
        description="Escolha o recorte dos documentos. Filtros vazios no modo “Tudo” incluem o catálogo inteiro do escritório."
        :ui="{ content: 'sm:max-w-lg' }"
      >
        <template #body>
          <UForm
            :schema="schema"
            :state="state"
            class="space-y-5"
            @submit="onSubmit"
          >
            <div>
              <p class="text-sm font-medium mb-2">
                Como você quer recortar?
              </p>
              <div class="grid gap-2 sm:grid-cols-2">
                <button
                  v-for="item in presetItems"
                  :key="item.value"
                  type="button"
                  class="text-left rounded-lg border p-3 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                  :class="preset === item.value
                    ? 'border-primary bg-primary/5'
                    : 'border-default hover:bg-elevated/50'"
                  :aria-pressed="preset === item.value"
                  @click="applyPreset(item.value)"
                >
                  <div class="flex items-start gap-2">
                    <UIcon :name="item.icon" class="size-4 mt-0.5 shrink-0 text-muted" />
                    <div>
                      <p class="text-sm font-medium">
                        {{ item.label }}
                      </p>
                      <p class="text-xs text-muted mt-0.5 leading-snug">
                        {{ item.description }}
                      </p>
                    </div>
                  </div>
                </button>
              </div>
            </div>

            <UFormField
              v-if="preset === 'competence'"
              label="Competência (mês)"
              name="competence"
              hint="Formato AAAA-MM, como no fechamento contábil."
            >
              <UInput
                v-model="state.competence"
                type="month"
                class="w-full"
                aria-label="Competência"
              />
            </UFormField>

            <div
              v-if="preset === 'period'"
              class="grid gap-4 sm:grid-cols-2"
            >
              <UFormField label="Emissão a partir de" name="issued_from">
                <UInput v-model="state.issued_from" type="date" class="w-full" />
              </UFormField>
              <UFormField label="Emissão até" name="issued_to">
                <UInput v-model="state.issued_to" type="date" class="w-full" />
              </UFormField>
            </div>

            <UFormField
              v-if="preset === 'single'"
              label="Chave de acesso da nota"
              name="access_key"
              hint="Cole a chave completa (44 caracteres em NF-e/NFC-e)."
            >
              <UInput
                v-model="state.access_key"
                class="w-full font-mono text-sm"
                placeholder="Chave de acesso"
                autocomplete="off"
              />
            </UFormField>

            <UAlert
              v-if="preset === 'all'"
              color="warning"
              variant="subtle"
              icon="i-lucide-triangle-alert"
              title="Pacote potencialmente grande"
              description="Sem filtro de data o ZIP pode demorar e ficar pesado. Prefira competência ou período quando possível."
            />

            <UFormField
              v-if="preset !== 'single'"
              label="Direção"
              name="direction"
              hint="Saídas = emitidas pelos clientes; entradas = recebidas."
            >
              <USelect v-model="state.direction" :items="directionItems" class="w-full" />
            </UFormField>

            <UCheckbox
              v-model="state.include_events"
              label="Incluir também os XMLs de eventos (cancelamento, etc.)"
              name="include_events"
            />

            <div v-if="preset !== 'single'">
              <UButton
                type="button"
                color="neutral"
                variant="link"
                size="sm"
                class="px-0"
                :icon="showAdvanced ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                :label="showAdvanced ? 'Ocultar filtros avançados' : 'Mais filtros (CNPJ, papel, situação)'"
                @click="() => { showAdvanced = !showAdvanced }"
              />
              <div
                v-if="showAdvanced"
                class="mt-3 grid gap-4 sm:grid-cols-2"
              >
                <UFormField label="CNPJ emitente" name="issuer_cnpj">
                  <UInput v-model="state.issuer_cnpj" class="w-full" placeholder="Opcional" />
                </UFormField>
                <UFormField label="CNPJ tomador" name="taker_cnpj">
                  <UInput v-model="state.taker_cnpj" class="w-full" placeholder="Opcional" />
                </UFormField>
                <UFormField label="Papel fiscal" name="fiscal_role">
                  <USelect v-model="state.fiscal_role" :items="roleItems" class="w-full" />
                </UFormField>
                <UFormField label="Situação do documento" name="status">
                  <USelect v-model="state.status" :items="statusItems" class="w-full" />
                </UFormField>
              </div>
            </div>

            <UAlert
              color="neutral"
              variant="subtle"
              icon="i-lucide-clock-3"
              title="Depois de enfileirar"
              description="O ZIP aparece na lista como Pendente → Processando → Disponível. Baixe em até 24 horas."
            />

            <div class="flex justify-end gap-2 pt-1">
              <UButton
                color="neutral"
                variant="subtle"
                type="button"
                label="Cancelar"
                :disabled="creating"
                @click="() => { createOpen = false }"
              />
              <UButton
                type="submit"
                icon="i-lucide-package"
                label="Enfileirar ZIP"
                :loading="creating"
                :disabled="creating"
              />
            </div>
          </UForm>
        </template>
      </UModal>
    </template>
  </UDashboardPanel>
</template>
