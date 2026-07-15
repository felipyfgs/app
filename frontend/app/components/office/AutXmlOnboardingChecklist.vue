<script setup lang="ts">
/**
 * Checklist autXML por estabelecimento.
 * Arquétipo: settings/members.vue (card + lista) — adaptado a enrollment.
 * Cobertura: NF-e 55 apenas; NFC-e 65 → import XML/ZIP.
 */
import type { TableColumn } from '@nuxt/ui'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const toast = useToast()
const { canManageClients } = useDashboard()

const loading = ref(false)
const acting = ref<number | null>(null)
const error = ref<string | null>(null)
const officeCnpj = ref<string | null>(null)
const stream = ref<{
  stream_ready: boolean
  stream_reason: string | null
  quiet_hours: number
  activated_at: string | null
  ready_at: string | null
} | null>(null)
const coverage = ref<Record<string, unknown> | null>(null)
const rows = ref<Array<Record<string, unknown>>>([])

const columns: TableColumn<Record<string, unknown>>[] = [
  { accessorKey: 'establishment_cnpj', header: 'Estabelecimento' },
  { accessorKey: 'status', header: 'Estado' },
  {
    accessorKey: 'first_seen_at',
    header: '1ª evidência',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'last_seen_at',
    header: 'Última',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  { id: 'actions', header: '' }
]

const streamReady = computed(() => !!stream.value?.stream_ready)
const streamHint = computed(() => {
  const reason = stream.value?.stream_reason
  if (!reason) return null
  if (reason === 'CURSOR_MISSING' || reason === 'NOT_ACTIVATED') {
    return 'Ative o stream autXML (primeira distNSU) antes de confirmar enrollments. O canal não é retroativo.'
  }
  if (reason === 'QUIET_PENDING') {
    return `Aguarde o quiet mínimo de ${stream.value?.quiet_hours ?? 1}h após a primeira consulta (até ${stream.value?.ready_at ? formatDateTime(stream.value.ready_at) : '—'}).`
  }
  return 'Stream ainda não apto a confirmações operacionais.'
})

async function load() {
  loading.value = true
  try {
    const res = await api.officeAutXml.overview()
    officeCnpj.value = res.data.office_cnpj
    stream.value = res.data.stream
    coverage.value = res.data.coverage
    rows.value = res.data.enrollments || []
    error.value = null
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Não foi possível carregar o onboarding autXML.')
  } finally {
    loading.value = false
  }
}

async function copyOfficeCnpj() {
  const c = officeCnpj.value
  if (!c) return
  try {
    await navigator.clipboard.writeText(c)
    toast.add({ title: 'CNPJ do escritório copiado para o ERP', color: 'success' })
  } catch {
    toast.add({ title: 'Não foi possível copiar o CNPJ', color: 'error' })
  }
}

function statusColor(status: string): 'success' | 'warning' | 'neutral' | 'error' {
  if (status === 'CONFIRMED') return 'success'
  if (status === 'PENDING') return 'warning'
  if (status === 'INACTIVE') return 'neutral'
  return 'neutral'
}

function statusLabel(status: string) {
  const map: Record<string, string> = {
    NONE: 'Não iniciado',
    PENDING: 'Pendente (ERP)',
    CONFIRMED: 'Confirmado',
    INACTIVE: 'Inativo'
  }
  return map[status] || status
}

async function enroll(row: Record<string, unknown>) {
  if (!canManageClients.value || acting.value) return
  const estId = Number(row.establishment_id)
  acting.value = estId
  try {
    await api.officeAutXml.enroll(estId)
    toast.add({ title: 'Enrollment PENDING criado — inclua o CNPJ no ERP do emitente', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao enrollar.'), color: 'error' })
  } finally {
    acting.value = null
  }
}

async function confirm(row: Record<string, unknown>) {
  if (!canManageClients.value || acting.value || !streamReady.value) return
  const id = Number(row.id)
  if (!id) return
  acting.value = id
  try {
    await api.officeAutXml.confirm(id)
    toast.add({ title: 'Enrollment confirmado operacionalmente', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Confirmação bloqueada.'), color: 'error' })
  } finally {
    acting.value = null
  }
}

async function inactivate(row: Record<string, unknown>) {
  if (!canManageClients.value || acting.value) return
  const id = Number(row.id)
  if (!id) return
  acting.value = id
  try {
    await api.officeAutXml.inactivate(id)
    toast.add({ title: 'Enrollment inativado', color: 'neutral' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao inativar.'), color: 'error' })
  } finally {
    acting.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-4" data-testid="autxml-onboarding">
    <UAlert
      color="warning"
      icon="i-lucide-info"
      title="autXML não é retroativo · cobertura NF-e 55"
      description="Inclua o CNPJ completo do escritório em autXML no ERP do emitente antes da autorização. Novo usuário distNSU não recebe NSU retroativo. NFC-e 65 e histórico/lacunas: import XML/ZIP."
    />

    <UPageCard variant="subtle">
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <div>
          <p class="text-muted">
            CNPJ do escritório (copiar para o ERP)
          </p>
          <code class="text-highlighted">{{ officeCnpj || '— cadastre a identidade fiscal —' }}</code>
        </div>
        <UButton
          size="sm"
          color="neutral"
          variant="subtle"
          icon="i-lucide-copy"
          label="Copiar CNPJ"
          :disabled="!officeCnpj"
          aria-label="Copiar CNPJ completo do escritório para colar no ERP"
          @click="copyOfficeCnpj"
        />
        <UBadge color="primary" variant="subtle">
          {{ coverage?.label || 'NF-e modelo 55' }}
        </UBadge>
        <UBadge
          :color="streamReady ? 'success' : 'warning'"
          variant="subtle"
        >
          Stream: {{ streamReady ? 'apto a confirmar' : 'aguardando ativação/quiet' }}
        </UBadge>
      </div>
    </UPageCard>

    <UAlert
      v-if="streamHint"
      color="warning"
      icon="i-lucide-clock"
      title="Confirmação operacional bloqueada"
      :description="streamHint"
    />

    <UAlert
      v-if="error"
      color="error"
      icon="i-lucide-wifi-off"
      title="Onboarding indisponível"
      :description="error"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
    />

    <div
      v-if="loading"
      class="space-y-2"
      role="status"
      aria-live="polite"
    >
      <USkeleton class="h-10 w-full" />
      <USkeleton class="h-10 w-full" />
    </div>

    <UTable
      v-else-if="rows.length"
      data-testid="autxml-enrollment-table"
      :data="rows"
      :columns="columns"
      class="shrink-0"
      :ui="DASHBOARD_TABLE_UI"
    >
      <template #establishment_cnpj-cell="{ row }">
        <div class="min-w-0">
          <p class="truncate font-medium text-highlighted">
            {{ row.original.client_name || row.original.establishment_name || '—' }}
          </p>
          <p class="truncate font-mono text-xs text-muted">
            {{ row.original.establishment_cnpj }}
          </p>
        </div>
      </template>
      <template #status-cell="{ row }">
        <div class="flex flex-wrap items-center gap-1">
          <UBadge :color="statusColor(String(row.original.status))" variant="subtle">
            {{ statusLabel(String(row.original.status)) }}
          </UBadge>
          <UBadge
            v-if="row.original.observed"
            color="success"
            variant="outline"
            size="sm"
          >
            XML observado
          </UBadge>
        </div>
      </template>
      <template #first_seen_at-cell="{ row }">
        <span class="text-sm text-muted">
          {{ row.original.first_seen_at ? formatDateTime(String(row.original.first_seen_at)) : '—' }}
        </span>
      </template>
      <template #last_seen_at-cell="{ row }">
        <span class="text-sm text-muted">
          {{ row.original.last_seen_at ? formatDateTime(String(row.original.last_seen_at)) : '—' }}
        </span>
      </template>
      <template #actions-cell="{ row }">
        <div class="flex flex-wrap justify-end gap-1">
          <UButton
            v-if="canManageClients && (row.original.status === 'NONE' || row.original.status === 'INACTIVE')"
            size="xs"
            color="primary"
            variant="subtle"
            label="Iniciar"
            :loading="acting === Number(row.original.establishment_id)"
            :aria-label="`Iniciar onboarding autXML para ${row.original.establishment_cnpj}`"
            @click="enroll(row.original)"
          />
          <UButton
            v-if="canManageClients && row.original.status === 'PENDING' && row.original.id"
            size="xs"
            color="success"
            variant="subtle"
            label="Confirmar"
            :disabled="!streamReady"
            :loading="acting === Number(row.original.id)"
            :aria-label="`Confirmar enrollment de ${row.original.establishment_cnpj}`"
            @click="confirm(row.original)"
          />
          <UButton
            v-if="canManageClients && (row.original.status === 'PENDING' || row.original.status === 'CONFIRMED') && row.original.id"
            size="xs"
            color="neutral"
            variant="ghost"
            label="Inativar"
            :loading="acting === Number(row.original.id)"
            :aria-label="`Inativar enrollment de ${row.original.establishment_cnpj}`"
            @click="inactivate(row.original)"
          />
        </div>
      </template>
    </UTable>

    <UEmpty
      v-if="!loading && !error && !rows.length"
      icon="i-lucide-building-2"
      title="Nenhum estabelecimento ativo"
      description="Cadastre clientes/estabelecimentos para montar o checklist autXML."
    />
  </div>
</template>
