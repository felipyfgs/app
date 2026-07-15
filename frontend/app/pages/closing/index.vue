<script setup lang="ts">
/**
 * Visão de fechamento mensal de XMLs de saída (competência / prazo operacional).
 * Arquétipo: lista + filtros + stats (template customers/home + UDashboardPanel).
 * Não oferece retry remoto, aumento de frequência nem postergação de due_at.
 */
import type { TableColumn } from '@nuxt/ui'
import type {
  OutboundCapacityForecast,
  OutboundCompetenceSummary,
  OutboundDeadlineMetrics,
  OutboundDeadlinePendingItem,
  OutboundUrgencyBand
} from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { canCreateExport, canAccessAdministration, canImportDocuments, me } = useDashboard()

const FILTER_ALL = 'all'
const pendingPage = ref(1)
const pendingPerPage = 50
const pendingTotal = ref(0)
const pendingLastPage = ref(1)

function defaultCompetence(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

const competence = computed({
  get: () => {
    const q = String(route.query.competence || '')
    return /^\d{4}-\d{2}$/.test(q) ? q : defaultCompetence()
  },
  set: (value: string) => {
    void updateQuery({ competence: value || undefined })
  }
})

const bandFilter = computed({
  get: () => {
    const v = String(route.query.band || FILTER_ALL).toUpperCase()
    const allowed = ['PLANNED', 'ATTENTION', 'CONTINGENCY', 'OVERDUE', FILTER_ALL]
    return allowed.includes(v) ? v : FILTER_ALL
  },
  set: (value: string) => {
    void updateQuery({ band: value === FILTER_ALL ? undefined : value })
  }
})

const modelFilter = computed({
  get: () => {
    const v = String(route.query.model || FILTER_ALL)
    return ['55', '65', 'NFE', 'NFCE', FILTER_ALL].includes(v) ? v : FILTER_ALL
  },
  set: (value: string) => {
    void updateQuery({ model: value === FILTER_ALL ? undefined : value })
  }
})

const rootFilter = computed({
  get: () => String(route.query.root || ''),
  set: (value: string) => {
    void updateQuery({ root: value.trim() || undefined })
  }
})

const sourceFilter = computed({
  get: () => String(route.query.source || FILTER_ALL),
  set: (value: string) => {
    void updateQuery({ source: value === FILTER_ALL ? undefined : value })
  }
})

const clientFilter = computed({
  get: () => String(route.query.client_id || ''),
  set: (value: string) => {
    void updateQuery({ client_id: value.trim() || undefined })
  }
})

const summary = ref<OutboundCompetenceSummary | null>(null)
const capacity = ref<OutboundCapacityForecast | null>(null)
const metrics = ref<OutboundDeadlineMetrics | null>(null)
const items = ref<OutboundDeadlinePendingItem[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)
const actionLoading = ref(false)
const partialNotes = ref('')
const advanceTargetLocal = ref('')
const advanceOpen = ref(false)

const bandItems = [
  { label: 'Todas as faixas', value: FILTER_ALL },
  { label: 'Planejado', value: 'PLANNED' },
  { label: 'Atenção', value: 'ATTENTION' },
  { label: 'Contingência', value: 'CONTINGENCY' },
  { label: 'Vencido', value: 'OVERDUE' }
]

const modelItems = [
  { label: 'Todos os modelos', value: FILTER_ALL },
  { label: 'NF-e (55)', value: '55' },
  { label: 'NFC-e (65)', value: '65' }
]

const sourceItems = [
  { label: 'Todas as fontes', value: FILTER_ALL },
  { label: 'SVRS', value: 'SVRS' },
  { label: 'autXML', value: 'AUTXML' },
  { label: 'Upload / ZIP', value: 'MANUAL' },
  { label: 'Pacote oficial', value: 'PACKAGE' },
  { label: 'Vault', value: 'VAULT' }
]

const columns: TableColumn<OutboundDeadlinePendingItem>[] = [
  { accessorKey: 'urgency_band', header: 'Faixa' },
  { accessorKey: 'access_key_masked', header: 'Chave' },
  {
    accessorKey: 'model',
    header: 'Modelo',
    meta: { class: { th: 'hidden sm:table-cell', td: 'hidden sm:table-cell' } }
  },
  {
    accessorKey: 'due_at',
    header: 'Prazo (due)',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'target_at',
    header: 'Meta',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  {
    accessorKey: 'recovery_status',
    header: 'Técnico',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  { id: 'next', header: 'Próximo passo' }
]

const projection = computed(() => capacity.value?.projection ?? null)
const readiness = computed(() => summary.value?.readiness ?? null)
const fractionPct = computed(() =>
  Math.round(((projection.value?.auto_queue_fraction ?? 0.6) * 100))
)

const knownCompletenessPct = computed(() => {
  const known = summary.value?.known_total ?? 0
  const captured = summary.value?.captured_total ?? 0
  if (known <= 0) return null
  return Math.round((captured / known) * 100)
})

const filteredItems = computed(() => {
  let list = items.value
  if (modelFilter.value !== FILTER_ALL) {
    const m = modelFilter.value
    list = list.filter((row) => {
      const model = String(row.model || '')
      if (m === '55' || m === 'NFE') return model === '55' || model === 'NFE' || model.includes('55')
      if (m === '65' || m === 'NFCE') return model === '65' || model === 'NFCE' || model.includes('65')
      return true
    })
  }
  if (rootFilter.value) {
    const r = rootFilter.value.replace(/\D/g, '').slice(0, 8)
    if (r) {
      list = list.filter(row => String(row.root_cnpj || '').includes(r)
        || String(row.access_key_masked || '').includes(r))
    }
  }
  if (sourceFilter.value !== FILTER_ALL) {
    const s = sourceFilter.value.toUpperCase()
    list = list.filter((row) => {
      const src = String(row.capture_source || '').toUpperCase()
      if (!src) return true
      return src.includes(s)
    })
  }
  return list
})

const primaryAction = computed(() => {
  const bands = summary.value?.by_band ?? {}
  const overdue = bands.OVERDUE ?? 0
  const contingency = bands.CONTINGENCY ?? 0
  const attention = bands.ATTENTION ?? 0
  if (overdue > 0 || contingency > 0) {
    return {
      kind: 'import' as const,
      label: 'Importação assistida (XML/ZIP/pacote)',
      description: 'Contingência/vencidos: a ação principal é importar — a SVRS segue só nos slots seguros.'
    }
  }
  if (attention > 0) {
    return {
      kind: 'batch' as const,
      label: 'Preparar lote assistido',
      description: 'Atenção: prepare importação/pacote sem disparar retry remoto.'
    }
  }
  return {
    kind: 'wait' as const,
    label: 'Aguardar fontes preferenciais',
    description: 'Planejado: priorize autXML/vault; a SVRS só entra após acomodação e no slot calculado.'
  }
})

function bandColor(band?: OutboundUrgencyBand | null): 'success' | 'info' | 'warning' | 'error' | 'neutral' {
  switch ((band || '').toUpperCase()) {
    case 'CAPTURED': return 'success'
    case 'PLANNED': return 'info'
    case 'ATTENTION': return 'warning'
    case 'CONTINGENCY': return 'warning'
    case 'OVERDUE': return 'error'
    default: return 'neutral'
  }
}

function bandIcon(band?: OutboundUrgencyBand | null): string {
  switch ((band || '').toUpperCase()) {
    case 'CAPTURED': return 'i-lucide-check-circle-2'
    case 'PLANNED': return 'i-lucide-calendar'
    case 'ATTENTION': return 'i-lucide-triangle-alert'
    case 'CONTINGENCY': return 'i-lucide-life-buoy'
    case 'OVERDUE': return 'i-lucide-alarm-clock-off'
    default: return 'i-lucide-circle'
  }
}

function bandLabel(band?: OutboundUrgencyBand | null): string {
  switch ((band || '').toUpperCase()) {
    case 'CAPTURED': return 'Capturado'
    case 'PLANNED': return 'Planejado'
    case 'ATTENTION': return 'Atenção'
    case 'CONTINGENCY': return 'Contingência'
    case 'OVERDUE': return 'Vencido'
    default: return band || '—'
  }
}

function nextStepLabel(step?: string | null): string {
  switch (step) {
    case 'ASSISTED_IMPORT': return 'Importação assistida'
    case 'PREPARE_ASSISTED_BATCH': return 'Preparar lote'
    case 'WAIT_OR_PREFER_AUTXML': return 'Aguardar / autXML'
    default: return step || '—'
  }
}

function technicalLabel(row: OutboundDeadlinePendingItem): string {
  if (row.failure_label) return row.failure_label
  if (row.failure_reason) return String(row.failure_reason)
  if (row.recovery_status) return String(row.recovery_status)
  return 'OK / aguardando'
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

async function load() {
  loading.value = true
  try {
    const comp = competence.value
    const band = bandFilter.value === FILTER_ALL ? undefined : bandFilter.value
    const model = modelFilter.value === FILTER_ALL ? undefined : modelFilter.value
    const root = rootFilter.value || undefined
    const source = sourceFilter.value === FILTER_ALL ? undefined : sourceFilter.value
    const clientId = clientFilter.value ? Number(clientFilter.value) : undefined
    const [sumRes, capRes, pendRes, metRes] = await Promise.allSettled([
      api.outbound.deadline.competence(comp),
      api.outbound.deadline.capacity(comp),
      api.outbound.deadline.pending({
        competence: comp,
        urgency_band: band,
        model,
        root_cnpj: root,
        source,
        client_id: clientId && clientId > 0 ? clientId : undefined,
        page: pendingPage.value,
        per_page: pendingPerPage
      }),
      api.outbound.deadline.metrics(comp)
    ])

    if (sumRes.status === 'fulfilled') {
      summary.value = sumRes.value.data
    }
    if (capRes.status === 'fulfilled') {
      capacity.value = capRes.value.data
      if (capacity.value.projection?.target_at) {
        advanceTargetLocal.value = capacity.value.projection.target_at.slice(0, 16)
      }
    }
    if (pendRes.status === 'fulfilled') {
      items.value = pendRes.value.data
      pendingTotal.value = pendRes.value.meta.total
      pendingLastPage.value = pendRes.value.meta.last_page
    }
    if (metRes.status === 'fulfilled') {
      metrics.value = metRes.value.data
    }

    const failed = [sumRes, capRes, pendRes].filter(r => r.status === 'rejected')
    if (failed.length === 3) {
      loadError.value = apiErrorMessage(
        (failed[0] as PromiseRejectedResult).reason,
        'Não foi possível carregar o fechamento.'
      )
    } else {
      loadError.value = null
    }
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao carregar fechamento.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function confirmPartial() {
  if (!canCreateExport.value) {
    toast.add({ title: 'Somente OPERATOR/ADMIN pode confirmar parcial.', color: 'warning' })
    return
  }
  actionLoading.value = true
  try {
    await api.outbound.deadline.confirmPartial({
      competence: competence.value,
      notes: partialNotes.value || undefined
    })
    toast.add({ title: 'Exportação parcial confirmada (documentos conhecidos).', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao confirmar parcial.'), color: 'error' })
  } finally {
    actionLoading.value = false
  }
}

async function exportMonthly() {
  if (!canCreateExport.value) {
    toast.add({ title: 'Somente OPERATOR/ADMIN pode exportar.', color: 'warning' })
    return
  }
  actionLoading.value = true
  try {
    const res = await api.outbound.deadline.exportMonthly({
      competence: competence.value,
      notes: partialNotes.value || undefined
    })
    toast.add({
      title: res.data.has_manifest
        ? 'Exportação enfileirada com manifesto de ausências.'
        : 'Exportação mensal enfileirada.',
      color: 'success'
    })
    await router.push('/exports')
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao exportar competência.'), color: 'error' })
  } finally {
    actionLoading.value = false
  }
}

async function advanceTarget() {
  if (!canAccessAdministration.value) {
    toast.add({ title: 'Somente ADMIN com 2FA recente pode antecipar a meta.', color: 'warning' })
    return
  }
  if (!advanceTargetLocal.value) {
    toast.add({ title: 'Informe a nova meta (target_at).', color: 'warning' })
    return
  }
  actionLoading.value = true
  try {
    await api.outbound.deadline.advanceTarget({
      competence: competence.value,
      target_at: new Date(advanceTargetLocal.value).toISOString()
    })
    toast.add({ title: 'Meta interna antecipada (due_at e budgets inalterados).', color: 'success' })
    advanceOpen.value = false
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível antecipar a meta.'), color: 'error' })
  } finally {
    actionLoading.value = false
  }
}

watch(
  () => [
    route.query.competence,
    route.query.band,
    route.query.model,
    route.query.root,
    route.query.source,
    route.query.client_id
  ],
  () => {
    pendingPage.value = 1
    void load()
  }
)

watch(pendingPage, () => void load())

onMounted(() => {
  void load()
})
</script>

<template>
  <UDashboardPanel id="closing">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Fechamento de saídas">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UTooltip text="Atualizar">
            <UButton
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="ghost"
              square
              aria-label="Atualizar fechamento"
              :loading="loading"
              @click="load"
            />
          </UTooltip>
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UAlert
        icon="i-lucide-info"
        color="info"
        variant="subtle"
        title="SLA operacional interno (dia 1)"
        description="Prazo e meta são política do escritório, não obrigação legal presumida. Completude é sobre documentos conhecidos — não garante universo fiscal absoluto. Urgência não aumenta taxa SVRS nem fura breaker."
        class="mb-4"
      />

      <div class="flex flex-col gap-3 mb-4 lg:flex-row lg:items-end lg:flex-wrap">
        <UFormField label="Competência" class="w-full sm:w-40">
          <UInput
            v-model="competence"
            type="month"
            data-testid="closing-competence"
            aria-label="Competência (AAAA-MM)"
          />
        </UFormField>
        <UFormField label="Faixa" class="w-full sm:w-48">
          <USelect
            v-model="bandFilter"
            :items="bandItems"
            data-testid="closing-band"
            aria-label="Filtrar por faixa de urgência"
          />
        </UFormField>
        <UFormField label="Modelo" class="w-full sm:w-40">
          <USelect
            v-model="modelFilter"
            :items="modelItems"
            data-testid="closing-model"
            aria-label="Filtrar por modelo fiscal"
          />
        </UFormField>
        <UFormField label="Raiz (CNPJ)" class="w-full sm:w-40">
          <UInput
            v-model="rootFilter"
            placeholder="8 dígitos"
            data-testid="closing-root"
            aria-label="Filtrar por raiz CNPJ"
            maxlength="14"
          />
        </UFormField>
        <UFormField label="Cliente (id)" class="w-full sm:w-32">
          <UInput
            v-model="clientFilter"
            type="number"
            min="1"
            placeholder="ID"
            data-testid="closing-client"
            aria-label="Filtrar por cliente"
          />
        </UFormField>
        <UFormField label="Fonte" class="w-full sm:w-44">
          <USelect
            v-model="sourceFilter"
            :items="sourceItems"
            data-testid="closing-source"
            aria-label="Filtrar por fonte de captura"
          />
        </UFormField>
      </div>

      <UAlert
        v-if="loadError"
        color="error"
        icon="i-lucide-wifi-off"
        title="Falha ao carregar"
        :description="loadError"
        class="mb-4"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => load() }]"
      />

      <div
        class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4"
        data-testid="closing-stats"
      >
        <UCard>
          <div class="text-sm text-muted">Conhecidos / capturados</div>
          <div class="text-2xl font-semibold tabular-nums">
            {{ summary?.captured_total ?? '—' }}
            <span class="text-base font-normal text-muted">/ {{ summary?.known_total ?? '—' }}</span>
          </div>
          <div class="text-xs text-muted mt-1">
            Completude conhecida
            <template v-if="knownCompletenessPct != null">: {{ knownCompletenessPct }}%</template>
            <template v-else>: —</template>
          </div>
        </UCard>
        <UCard>
          <div class="text-sm text-muted">Meta / prazo</div>
          <div class="text-sm font-medium mt-1">
            Meta: {{ formatDateTime(projection?.target_at) }}
          </div>
          <div class="text-sm font-medium">
            Due: {{ formatDateTime(projection?.due_at) }}
          </div>
          <div class="text-xs text-muted mt-1">
            Conclusão est.: {{ formatDateTime(projection?.estimated_completion_at) }}
          </div>
        </UCard>
        <UCard>
          <div class="text-sm text-muted">Capacidade auto ({{ fractionPct }}%)</div>
          <div class="text-2xl font-semibold tabular-nums">
            {{ projection?.safe_capacity_exchanges ?? '—' }}
            <span class="text-base font-normal text-muted">exch.</span>
          </div>
          <div class="text-xs text-muted mt-1">
            Folga: {{ projection?.slack_exchanges ?? '—' }} · Demanda: {{ projection?.demand_exchanges ?? '—' }}
          </div>
        </UCard>
        <UCard>
          <div class="text-sm text-muted">Contingência / vencidos</div>
          <div class="text-2xl font-semibold tabular-nums">
            {{ summary?.by_band?.CONTINGENCY ?? 0 }}
            <span class="text-base font-normal text-muted">/ {{ summary?.by_band?.OVERDUE ?? 0 }}</span>
          </div>
          <div class="text-xs mt-1">
            <UBadge
              :color="readiness?.status === 'COMPLETE_KNOWN' ? 'success' : readiness?.status === 'PARTIAL_CONFIRMED' ? 'warning' : 'neutral'"
              variant="subtle"
            >
              {{ readiness?.status_label || readiness?.status || '—' }}
            </UBadge>
          </div>
        </UCard>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
        <UAlert
          :icon="primaryAction.kind === 'import' ? 'i-lucide-upload' : primaryAction.kind === 'batch' ? 'i-lucide-package-open' : 'i-lucide-hourglass'"
          :color="primaryAction.kind === 'import' ? 'warning' : primaryAction.kind === 'batch' ? 'warning' : 'info'"
          :title="primaryAction.label"
          :description="primaryAction.description"
        >
          <template v-if="canImportDocuments" #actions>
            <UButton
              v-if="primaryAction.kind !== 'wait'"
              to="/docs/imports"
              color="primary"
              :label="primaryAction.kind === 'import' ? 'Ir para importação' : 'Preparar importação'"
              icon="i-lucide-upload"
            />
          </template>
        </UAlert>

        <UAlert
          v-if="projection?.at_risk"
          icon="i-lucide-gauge"
          color="error"
          title="Capacidade insuficiente até a meta"
          description="Demanda excede 60% da capacidade segura. Contingência assistida — budgets e breaker inalterados."
        />
        <UAlert
          v-else
          icon="i-lucide-shield-check"
          color="success"
          variant="subtle"
          title="Canal e prazo são sinais distintos"
          description="Breaker/kill switch e falha técnica aparecem na coluna Técnico e em Saúde — não se misturam com a faixa de prazo."
        />
      </div>

      <UAlert
        v-for="alert in (metrics?.alerts || [])"
        :key="alert.code"
        class="mb-2"
        :color="alert.severity === 'critical' ? 'error' : alert.severity === 'high' ? 'warning' : 'info'"
        :title="alert.message"
        icon="i-lucide-bell"
      />

      <UCard class="mb-4" data-testid="closing-actions">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div class="flex-1">
            <p class="text-sm font-medium">
              Ações de fechamento
            </p>
            <p class="text-xs text-muted mt-1">
              VIEWER: somente leitura. Não há controle de cooldown, frequência SVRS ou postergação de due_at nesta tela.
            </p>
            <UFormField
              v-if="canCreateExport"
              label="Notas (parcial / export)"
              class="mt-2 max-w-md"
            >
              <UInput
                v-model="partialNotes"
                placeholder="Opcional — auditoria"
                aria-label="Notas de confirmação parcial"
              />
            </UFormField>
          </div>
          <div class="flex flex-wrap gap-2">
            <UButton
              v-if="canCreateExport"
              color="neutral"
              variant="soft"
              icon="i-lucide-file-check"
              label="Confirmar parcial"
              :loading="actionLoading"
              :disabled="(summary?.pending_total ?? 0) === 0"
              @click="confirmPartial"
            />
            <UButton
              v-if="canCreateExport"
              color="primary"
              icon="i-lucide-package"
              label="Exportar competência"
              :loading="actionLoading"
              @click="exportMonthly"
            />
            <UButton
              v-if="canAccessAdministration"
              color="neutral"
              variant="outline"
              icon="i-lucide-calendar-minus"
              label="Antecipar meta"
              @click="() => { advanceOpen = true }"
            />
          </div>
        </div>
      </UCard>

      <UModal v-model:open="advanceOpen" title="Antecipar meta interna">
        <template #body>
          <UAlert
            icon="i-lucide-shield"
            color="warning"
            variant="subtle"
            class="mb-3"
            title="Somente antecipação"
            description="Não é possível postergar além do due_at (dia 1), reduzir buffer abaixo de 24h ou alterar budget/coorte."
          />
          <UFormField label="Nova target_at (local)">
            <UInput
              v-model="advanceTargetLocal"
              type="datetime-local"
              aria-label="Nova meta interna"
            />
          </UFormField>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="Cancelar" @click="() => { advanceOpen = false }" />
            <UButton
              color="primary"
              label="Aplicar antecipação"
              :loading="actionLoading"
              @click="advanceTarget"
            />
          </div>
        </template>
      </UModal>

      <UTable
        data-testid="closing-table"
        :data="filteredItems"
        :loading="loading"
        :columns="columns"
        empty="Nenhuma pendência para os filtros."
        class="w-full"
        :ui="DASHBOARD_TABLE_UI"
      >
        <template #urgency_band-cell="{ row }">
          <div class="flex items-center gap-2">
            <UIcon :name="bandIcon(row.original.urgency_band)" class="size-4 shrink-0" :class="{
              'text-error': bandColor(row.original.urgency_band) === 'error',
              'text-warning': bandColor(row.original.urgency_band) === 'warning',
              'text-info': bandColor(row.original.urgency_band) === 'info',
              'text-success': bandColor(row.original.urgency_band) === 'success'
            }"
            />
            <UBadge :color="bandColor(row.original.urgency_band)" variant="subtle">
              {{ bandLabel(row.original.urgency_band) }}
            </UBadge>
            <UBadge
              v-if="row.original.capacity_at_risk"
              color="error"
              variant="outline"
              size="sm"
            >
              Capacidade em risco
            </UBadge>
          </div>
        </template>
        <template #access_key_masked-cell="{ row }">
          <span class="font-mono text-xs">{{ row.original.access_key_masked || '—' }}</span>
        </template>
        <template #due_at-cell="{ row }">
          {{ formatDateTime(row.original.due_at) }}
        </template>
        <template #target_at-cell="{ row }">
          {{ formatDateTime(row.original.target_at) }}
        </template>
        <template #recovery_status-cell="{ row }">
          <span class="text-xs text-muted" :title="technicalLabel(row.original)">
            {{ technicalLabel(row.original) }}
          </span>
        </template>
        <template #next-cell="{ row }">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm">{{ nextStepLabel(row.original.next_step) }}</span>
            <UButton
              v-if="canImportDocuments && ['CONTINGENCY', 'OVERDUE', 'ATTENTION'].includes(String(row.original.urgency_band || '').toUpperCase())"
              size="xs"
              color="primary"
              variant="soft"
              icon="i-lucide-upload"
              label="Importar"
              to="/docs/imports"
            />
          </div>
        </template>
      </UTable>

      <div
        v-if="pendingTotal"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-default pt-4"
      >
        <p class="text-sm text-muted">
          {{ pendingTotal }} pendência(s) · página {{ pendingPage }} de {{ pendingLastPage }}
        </p>
        <UPagination
          v-if="pendingLastPage > 1"
          v-model:page="pendingPage"
          :total="pendingTotal"
          :items-per-page="pendingPerPage"
        />
      </div>

      <p class="text-xs text-muted mt-3">
        Escopo: {{ summary?.completeness_scope || 'known_documents_only' }}.
        Papel atual: {{ me?.role || '—' }}.
        Atalho: <kbd class="px-1 rounded border">g</kbd> então <kbd class="px-1 rounded border">f</kbd>.
      </p>
    </template>
  </UDashboardPanel>
</template>
