<script setup lang="ts">
/**
 * Canário DTE controlado (PLATFORM_ADMIN / Proprietário).
 * Sem payload fiscal — apenas status, correlação e consumo sanitizados.
 */
const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const acting = ref(false)
const loadError = ref<string | null>(null)
const summary = ref<Record<string, unknown> | null>(null)

const officeId = ref<number | null>(null)
const clientId = ref<number | null>(null)
const reconcileRef = ref('')
const reconcileSummary = ref('')
const promoteReason = ref('')
const disableReason = ref('')

const control = computed(() => (summary.value?.control ?? null) as Record<string, unknown> | null)
const request = computed(() => (summary.value?.request ?? null) as Record<string, unknown> | null)
const coordinates = computed(() => (summary.value?.coordinates ?? null) as Record<string, unknown> | null)
const gate = computed(() => (summary.value?.gate ?? null) as Record<string, unknown> | null)

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.serpro.dteCanary.summary()
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    summary.value = res.data
  } catch (e) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(e, 'Falha ao carregar canário DTE.')
    summary.value = null
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) loading.value = false
  }
}

async function run(action: () => Promise<void>) {
  acting.value = true
  try {
    await action()
    await load()
  } catch (e) {
    toast.add({
      title: 'Ação DTE falhou',
      description: apiErrorMessage(e, 'Verifique senha recente e gates.'),
      color: 'error'
    })
  } finally {
    acting.value = false
  }
}

function createRequest() {
  return run(async () => {
    await api.platform.serpro.dteCanary.create()
    toast.add({ title: 'Pedido de canário criado', color: 'success' })
  })
}

function selectTarget() {
  const id = Number(request.value?.id)
  if (!id || !officeId.value || !clientId.value) {
    toast.add({ title: 'Informe Office e cliente', color: 'warning' })
    return Promise.resolve()
  }
  return run(async () => {
    await api.platform.serpro.dteCanary.selectTarget(id, {
      office_id: officeId.value!,
      client_id: clientId.value!
    })
    toast.add({ title: 'Alvo selecionado (server-side)', color: 'success' })
  })
}

function approveOwner() {
  const id = Number(request.value?.id)
  if (!id) return Promise.resolve()
  return run(async () => {
    await api.platform.serpro.dteCanary.approveOwner(id)
    toast.add({ title: 'Aprovação do Proprietário registrada', color: 'success' })
  })
}

function execute() {
  const id = Number(request.value?.id)
  if (!id) return Promise.resolve()
  return run(async () => {
    const res = await api.platform.serpro.dteCanary.execute(id)
    toast.add({
      title: res.replay ? 'Replay (sem novo transporte)' : 'Tentativa DTE registrada',
      description: `Status: ${String(res.data.result_status ?? res.data.status ?? '—')}`,
      color: 'success'
    })
  })
}

function reconcile() {
  const id = Number(request.value?.id)
  if (!id) return Promise.resolve()
  return run(async () => {
    await api.platform.serpro.dteCanary.reconcile(id, {
      reference: reconcileRef.value,
      summary: reconcileSummary.value
    })
    toast.add({ title: 'Reconciliação registrada', color: 'success' })
  })
}

function promoteLimited() {
  const id = Number(request.value?.id)
  if (!id) return Promise.resolve()
  return run(async () => {
    await api.platform.serpro.dteCanary.promoteLimited(id, {
      confirmation_phrase: 'CONFIRMO-DTE-LIMITED',
      reason: promoteReason.value || 'Promoção LIMITED pós-canário',
      max_quantity: 10
    })
    toast.add({ title: 'DTE promovido a LIMITED (teto 10)', color: 'success' })
  })
}

function disable() {
  return run(async () => {
    await api.platform.serpro.dteCanary.disable({
      confirmation_phrase: 'CONFIRMO-DTE-DISABLE',
      reason: disableReason.value || 'Desativação imediata'
    })
    toast.add({ title: 'DTE desativado', color: 'warning' })
  })
}

watch(sessionEpoch, () => {
  summary.value = null
  void load()
})
onMounted(load)
</script>

<template>
  <div data-testid="admin-serpro-dte-canary">
    <UPageCard
      title="Canário DTE (produção controlada)"
      description="Uma consulta dte.consultar no Office piloto. Resultado fiscal só no tenant; aqui só status e consumo."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        class="lg:ms-auto"
        color="neutral"
        variant="ghost"
        icon="i-lucide-refresh-cw"
        label="Atualizar"
        :loading="loading"
        data-testid="dte-canary-refresh"
        @click="load"
      />
    </UPageCard>

    <UAlert
      v-if="loadError"
      color="error"
      icon="i-lucide-circle-x"
      :title="loadError"
      class="mb-4"
    />

    <UAlert
      color="warning"
      icon="i-lucide-shield-alert"
      title="Sem payload fiscal neste console"
      description="O Proprietário vê apenas status, correlação, timestamps e quantidade. Detalhe fiscal exige membership no Office piloto (/settings)."
      class="mb-4"
      data-testid="dte-canary-no-fiscal-banner"
    />

    <div class="flex flex-col gap-4 sm:gap-6">
      <UPageCard
        variant="subtle"
        title="Controle e coordenadas"
      >
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-muted">
              Modo
            </dt>
            <dd class="font-medium" data-testid="dte-control-mode">
              {{ control?.mode || 'DISABLED' }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Operação
            </dt>
            <dd class="font-medium">
              {{ coordinates?.operation_key || 'dte.consultar' }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Office piloto
            </dt>
            <dd class="font-medium">
              {{ control?.pilot_office_id ?? request?.office_id ?? '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Consumo LIMITED
            </dt>
            <dd class="font-medium">
              {{ control?.limited_used_quantity ?? 0 }} / {{ control?.limited_max_quantity ?? '—' }}
            </dd>
          </div>
        </dl>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Pedido atual"
        description="Alvo server-side; execução rejeita office_id/coordenadas livres."
      >
        <dl
          v-if="request"
          class="mb-4 grid gap-3 text-sm sm:grid-cols-2"
          data-testid="dte-canary-request"
        >
          <div>
            <dt class="text-muted">
              ID / status
            </dt>
            <dd class="font-medium">
              #{{ request.id }} · {{ request.status }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Resultado
            </dt>
            <dd class="font-medium">
              {{ request.result_status || '—' }}
              <span
                v-if="request.consumption_quantity"
                class="text-muted"
              >· qty {{ request.consumption_quantity }}</span>
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Aprovações
            </dt>
            <dd class="font-medium">
              Proprietário: {{ request.owner_approved ? 'sim' : 'não' }}
              · Office ADMIN: {{ request.office_admin_approved ? 'sim' : 'não' }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Correlação
            </dt>
            <dd class="font-mono text-xs">
              {{ request.correlation_id || '—' }}
            </dd>
          </div>
        </dl>
        <p
          v-else
          class="mb-4 text-sm text-muted"
        >
          Nenhum pedido ativo.
        </p>

        <div
          v-if="gate && Array.isArray(gate.blockers) && gate.blockers.length"
          class="mb-4"
        >
          <p class="mb-1 text-sm font-medium">
            Gate pré-transporte
          </p>
          <div class="flex flex-wrap gap-1">
            <UBadge
              v-for="b in (gate.blockers as string[])"
              :key="b"
              color="warning"
              variant="subtle"
              size="sm"
            >
              {{ b }}
            </UBadge>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <UButton
            label="Criar pedido"
            icon="i-lucide-plus"
            :loading="acting"
            data-testid="dte-canary-create"
            @click="createRequest"
          />
          <UButton
            label="Aprovar (Proprietário)"
            color="neutral"
            variant="soft"
            :disabled="!request?.id"
            :loading="acting"
            data-testid="dte-canary-approve-owner"
            @click="approveOwner"
          />
          <UButton
            label="Executar (1x)"
            color="primary"
            :disabled="!request?.id"
            :loading="acting"
            data-testid="dte-canary-execute"
            @click="execute"
          />
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
          <UFormField label="Office ID">
            <UInput
              v-model.number="officeId"
              type="number"
              placeholder="Office piloto"
              data-testid="dte-canary-office-id"
            />
          </UFormField>
          <UFormField label="Cliente ID">
            <UInput
              v-model.number="clientId"
              type="number"
              placeholder="Cliente do Office"
              data-testid="dte-canary-client-id"
            />
          </UFormField>
          <div class="flex items-end">
            <UButton
              label="Definir alvo"
              color="neutral"
              variant="outline"
              block
              :disabled="!request?.id"
              :loading="acting"
              data-testid="dte-canary-select-target"
              @click="selectTarget"
            />
          </div>
        </div>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Reconciliação e promoção LIMITED"
      >
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="Referência Área do Cliente">
            <UInput
              v-model="reconcileRef"
              data-testid="dte-canary-reconcile-ref"
            />
          </UFormField>
          <UFormField label="Resumo">
            <UInput
              v-model="reconcileSummary"
              data-testid="dte-canary-reconcile-summary"
            />
          </UFormField>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <UButton
            label="Reconciliar"
            color="neutral"
            variant="soft"
            :disabled="!request?.id"
            :loading="acting"
            data-testid="dte-canary-reconcile"
            @click="reconcile"
          />
          <UFormField
            label="Motivo promoção"
            class="min-w-48 flex-1"
          >
            <UInput
              v-model="promoteReason"
              data-testid="dte-canary-promote-reason"
            />
          </UFormField>
          <UButton
            label="Promover LIMITED (10)"
            color="primary"
            variant="soft"
            :disabled="!request?.id"
            :loading="acting"
            data-testid="dte-canary-promote"
            @click="promoteLimited"
          />
        </div>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Desativação imediata"
      >
        <div class="flex flex-wrap items-end gap-2">
          <UFormField
            label="Motivo"
            class="min-w-48 flex-1"
          >
            <UInput
              v-model="disableReason"
              data-testid="dte-canary-disable-reason"
            />
          </UFormField>
          <UButton
            label="Desativar DTE"
            color="error"
            variant="soft"
            :loading="acting"
            data-testid="dte-canary-disable"
            @click="disable"
          />
        </div>
        <p class="mt-2 text-xs text-muted">
          Frase: CONFIRMO-DTE-DISABLE · kill switch externo (env) continua prevalecendo.
        </p>
      </UPageCard>

      <div class="flex flex-wrap gap-2">
        <UButton
          to="/settings"
          color="neutral"
          variant="ghost"
          label="Settings do Office (confirmação ADMIN)"
          icon="i-lucide-building-2"
        />
        <UButton
          to="/admin/serpro/rollout"
          color="neutral"
          variant="ghost"
          label="Rollout / smoke"
          icon="i-lucide-rocket"
        />
      </div>
    </div>
  </div>
</template>
