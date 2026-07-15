<script setup lang="ts">
/**
 * Detalhe mestre–cliente fiscal (7.11).
 * Arquétipo Settings (nav horizontal) com seções LAZY: só a aba ativa é carregada.
 * Falha parcial com retry — nunca lista vazia silenciosa.
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { Client, FiscalFinding, FiscalMonitoringRun, FiscalPendingItem, FiscalSnapshot } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')

type SectionKey
  = | 'overview'
    | 'runs'
    | 'findings'
    | 'pending'
    | 'installments'
    | 'declarations'
    | 'guides'
    | 'fgts'
    | 'sitfis'

const SECTION_KEYS: SectionKey[] = [
  'overview',
  'runs',
  'findings',
  'pending',
  'installments',
  'declarations',
  'guides',
  'fgts',
  'sitfis'
]

function isSectionKey(value: string): value is SectionKey {
  return (SECTION_KEYS as string[]).includes(value)
}

const api = useApi()
const route = useRoute()
const router = useRouter()
const { sessionEpoch } = useDashboard()

const clientId = computed(() => Number(route.params.clientId))
const tab = computed({
  get: (): SectionKey => {
    const raw = String(route.query.tab || 'overview')
    return isSectionKey(raw) ? raw : 'overview'
  },
  set: (value: SectionKey) => {
    void router.replace({
      query: {
        ...route.query,
        tab: value === 'overview' ? undefined : value
      }
    })
  }
})

const client = ref<Client | null>(null)
const clientLoading = ref(false)
const clientError = ref<string | null>(null)

const snapshots = ref<FiscalSnapshot[]>([])
const runs = ref<FiscalMonitoringRun[]>([])
const findings = ref<FiscalFinding[]>([])
const pending = ref<FiscalPendingItem[]>([])
const installments = ref<Record<string, unknown>[]>([])
const declarations = ref<Record<string, unknown>[]>([])
const guides = ref<Record<string, unknown>[]>([])
const sitfis = ref<Record<string, unknown> | null>(null)
const fgtsCompetences = ref<Record<string, unknown>[]>([])

interface SectionState {
  loading: boolean
  error: string | null
  /** epoch+clientId em que a seção foi carregada com sucesso */
  loadedKey: string | null
}

function emptySection(): SectionState {
  return { loading: false, error: null, loadedKey: null }
}

const sections = reactive<Record<SectionKey, SectionState>>({
  overview: emptySection(),
  runs: emptySection(),
  findings: emptySection(),
  pending: emptySection(),
  installments: emptySection(),
  declarations: emptySection(),
  guides: emptySection(),
  fgts: emptySection(),
  sitfis: emptySection()
})

function cacheKey(): string {
  return `${clientId.value}@${sessionEpoch.value}`
}

const links = computed<NavigationMenuItem[][]>(() => {
  const active = tab.value
  const setTab = (value: SectionKey) => {
    tab.value = value
  }
  return [[
    {
      label: 'Visão geral',
      value: 'overview',
      active: active === 'overview',
      onSelect: () => setTab('overview')
    },
    {
      label: 'Execuções',
      value: 'runs',
      active: active === 'runs',
      onSelect: () => setTab('runs')
    },
    {
      label: 'Findings',
      value: 'findings',
      active: active === 'findings',
      onSelect: () => setTab('findings')
    },
    {
      label: 'Pendências',
      value: 'pending',
      active: active === 'pending',
      onSelect: () => setTab('pending')
    },
    {
      label: 'Parcelamentos',
      value: 'installments',
      active: active === 'installments',
      onSelect: () => setTab('installments')
    },
    {
      label: 'Declarações',
      value: 'declarations',
      active: active === 'declarations',
      onSelect: () => setTab('declarations')
    },
    {
      label: 'Guias',
      value: 'guides',
      active: active === 'guides',
      onSelect: () => setTab('guides')
    },
    {
      label: 'FGTS',
      value: 'fgts',
      active: active === 'fgts',
      onSelect: () => setTab('fgts')
    },
    {
      label: 'SITFIS',
      value: 'sitfis',
      active: active === 'sitfis',
      onSelect: () => setTab('sitfis')
    }
  ]]
})

const snapshotColumns = [
  { accessorKey: 'id', header: 'ID' },
  {
    id: 'situation',
    header: 'Situação',
    cell: ({ row }: { row: { original: FiscalSnapshot } }) =>
      h(FiscalStatusBadge, { status: row.original.situation })
  },
  {
    id: 'service',
    header: 'Serviço',
    cell: ({ row }: { row: { original: FiscalSnapshot } }) =>
      [row.original.system_code, row.original.service_code].filter(Boolean).join(' / ')
  },
  {
    accessorKey: 'observed_at',
    header: 'Observado',
    cell: ({ row }: { row: { original: FiscalSnapshot } }) =>
      formatDateTime(row.original.observed_at)
  },
  {
    id: 'evidence',
    header: 'Evidência',
    cell: ({ row }: { row: { original: FiscalSnapshot } }) =>
      row.original.evidence_artifact_id
        ? h('a', {
            href: api.fiscal.evidenceDownloadUrl(row.original.evidence_artifact_id),
            class: 'text-primary text-sm',
            target: '_blank',
            rel: 'noopener'
          }, 'Download')
        : '—'
  }
]

function clearSectionData(key: SectionKey) {
  switch (key) {
    case 'overview':
      snapshots.value = []
      break
    case 'runs':
      runs.value = []
      break
    case 'findings':
      findings.value = []
      break
    case 'pending':
      pending.value = []
      break
    case 'installments':
      installments.value = []
      break
    case 'declarations':
      declarations.value = []
      break
    case 'guides':
      guides.value = []
      break
    case 'fgts':
      fgtsCompetences.value = []
      break
    case 'sitfis':
      sitfis.value = null
      break
  }
}

function invalidateAllSections() {
  for (const key of SECTION_KEYS) {
    sections[key] = emptySection()
    clearSectionData(key)
  }
}

async function loadClient(force = false) {
  if (!Number.isFinite(clientId.value) || clientId.value < 1) {
    clientError.value = 'Cliente inválido.'
    client.value = null
    return
  }

  if (!force && client.value?.id === clientId.value && !clientError.value) {
    return
  }

  const keyNow = cacheKey()
  clientLoading.value = true
  clientError.value = null
  try {
    const data = (await api.clients.get(clientId.value)).data
    // Descarte se o office/epoch mudou durante o request (mesmo clientId em outro tenant).
    if (cacheKey() !== keyNow) return
    client.value = data
  } catch (caught) {
    if (cacheKey() !== keyNow) return
    client.value = null
    clientError.value = apiErrorMessage(caught, 'Cliente não encontrado neste escritório.')
  } finally {
    if (cacheKey() === keyNow) {
      clientLoading.value = false
    }
  }
}

async function loadSection(key: SectionKey, force = false) {
  if (!Number.isFinite(clientId.value) || clientId.value < 1) return

  const keyNow = cacheKey()
  const state = sections[key]
  if (!force && state.loadedKey === keyNow && !state.error) {
    return
  }

  state.loading = true
  state.error = null

  try {
    switch (key) {
      case 'overview': {
        const res = await api.fiscal.snapshots.list({
          client_id: clientId.value,
          per_page: 20,
          current_only: true
        })
        if (cacheKey() !== keyNow) return
        snapshots.value = res.data || []
        break
      }
      case 'runs': {
        const res = await api.fiscal.runs.list({
          client_id: clientId.value,
          per_page: 20
        })
        if (cacheKey() !== keyNow) return
        runs.value = res.data || []
        break
      }
      case 'findings': {
        const res = await api.fiscal.findings({
          client_id: clientId.value,
          per_page: 20,
          active_only: true
        })
        if (cacheKey() !== keyNow) return
        findings.value = res.data || []
        break
      }
      case 'pending': {
        const res = await api.fiscal.pending({
          client_id: clientId.value,
          per_page: 20,
          status: 'OPEN'
        })
        if (cacheKey() !== keyNow) return
        pending.value = res.data || []
        break
      }
      case 'installments': {
        const res = await api.fiscal.installments.orders({
          client_id: clientId.value,
          per_page: 20
        })
        if (cacheKey() !== keyNow) return
        installments.value = (res.data as Record<string, unknown>[]) || []
        break
      }
      case 'declarations': {
        const res = await api.fiscal.declarations.list({
          client_id: clientId.value,
          per_page: 20
        })
        if (cacheKey() !== keyNow) return
        declarations.value = (res.data as Record<string, unknown>[]) || []
        break
      }
      case 'guides': {
        const res = await api.fiscal.guides.list({
          client_id: clientId.value,
          per_page: 20
        })
        if (cacheKey() !== keyNow) return
        guides.value = (res.data as Record<string, unknown>[]) || []
        break
      }
      case 'fgts': {
        const res = await api.fiscal.fgts.competences({
          client_id: clientId.value,
          per_page: 20
        })
        if (cacheKey() !== keyNow) return
        fgtsCompetences.value = (res.data as Record<string, unknown>[]) || []
        break
      }
      case 'sitfis': {
        const res = await api.fiscal.sitfis.show(clientId.value)
        if (cacheKey() !== keyNow) return
        sitfis.value = (res.data as Record<string, unknown>) || null
        break
      }
    }
    if (cacheKey() === keyNow) {
      state.loadedKey = keyNow
      state.error = null
    }
  } catch (caught) {
    if (cacheKey() !== keyNow) return
    // Erro honesto: limpa dados da seção e registra mensagem (não finge lista vazia).
    clearSectionData(key)
    state.loadedKey = null
    state.error = apiErrorMessage(caught, `Falha ao carregar ${key}.`)
  } finally {
    if (cacheKey() === keyNow) {
      state.loading = false
    }
  }
}

async function bootstrap(force = false) {
  await loadClient(force)
  if (clientError.value) return
  await loadSection(tab.value, force)
}

function retrySection(key: SectionKey) {
  void loadSection(key, true)
}

watch(sessionEpoch, () => {
  invalidateAllSections()
  void bootstrap(true)
})

watch(clientId, () => {
  client.value = null
  invalidateAllSections()
  void bootstrap(true)
})

watch(tab, (next) => {
  void loadSection(next)
})

onMounted(() => {
  void bootstrap()
})
</script>

<template>
  <UDashboardPanel
    id="monitoring-client-detail"
    data-testid="settings-panel"
    :ui="{ body: 'lg:py-8' }"
  >
    <template #header>
      <UDashboardNavbar
        :title="client?.name || client?.legal_name || `Cliente #${clientId}`"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            to="/clients"
            color="neutral"
            variant="ghost"
            icon="i-lucide-users"
            label="Cadastro"
          />
          <UButton
            to="/monitoring"
            color="neutral"
            variant="ghost"
            icon="i-lucide-arrow-left"
            label="Dashboard"
          />
        </template>
      </UDashboardNavbar>
      <UDashboardToolbar>
        <UNavigationMenu
          :items="links"
          highlight
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-4xl">
        <UAlert
          v-if="clientError"
          color="error"
          icon="i-lucide-circle-x"
          :title="clientError"
        >
          <template #actions>
            <UButton
              size="xs"
              color="neutral"
              variant="outline"
              label="Tentar de novo"
              @click="bootstrap(true)"
            />
          </template>
        </UAlert>

        <div
          v-if="clientLoading && !client"
          class="py-12 text-center text-sm text-muted"
        >
          Carregando cliente…
        </div>

        <template v-else-if="client">
          <UPageCard
            variant="subtle"
            :title="client.legal_name || client.name"
            :description="client.cnpj ? formatCnpj(client.cnpj) : client.root_cnpj"
          >
            <div class="flex flex-wrap gap-2 text-sm">
              <UBadge
                :color="client.is_active ? 'success' : 'neutral'"
                variant="subtle"
              >
                {{ client.is_active ? 'Ativo' : 'Inativo' }}
              </UBadge>
              <span class="text-muted">ID {{ client.id }}</span>
            </div>
          </UPageCard>

          <!-- Estado de carregamento da seção ativa -->
          <div
            v-if="sections[tab].loading && !sections[tab].loadedKey"
            class="py-8 text-center text-sm text-muted"
            data-testid="section-loading"
          >
            Carregando seção…
          </div>

          <!-- Falha da seção (não vira lista vazia) -->
          <UAlert
            v-else-if="sections[tab].error"
            color="error"
            icon="i-lucide-circle-x"
            :title="sections[tab].error || 'Falha ao carregar seção'"
            data-testid="section-error"
          >
            <template #actions>
              <UButton
                size="xs"
                color="neutral"
                variant="outline"
                label="Tentar de novo"
                @click="retrySection(tab)"
              />
            </template>
          </UAlert>

          <template v-else>
            <!-- overview -->
            <UPageCard
              v-if="tab === 'overview'"
              title="Snapshots atuais"
              description="Competência e cobertura por serviço (carregado sob demanda)."
              variant="subtle"
            >
              <div
                v-if="!snapshots.length"
                class="text-sm text-muted"
              >
                Nenhum snapshot atual retornado pela API.
              </div>
              <UTable
                v-else
                :data="snapshots"
                :columns="snapshotColumns"
                :ui="DASHBOARD_TABLE_UI"
              />
            </UPageCard>

            <!-- runs -->
            <UPageCard
              v-else-if="tab === 'runs'"
              title="Execuções"
              variant="subtle"
            >
              <div
                v-if="!runs.length"
                class="text-sm text-muted"
              >
                Nenhuma execução retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="run in runs"
                  :key="run.id"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div>
                    <p class="font-medium">
                      #{{ run.id }} · {{ run.system_code }}/{{ run.service_code }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ formatDateTime(run.started_at || run.created_at) }}
                      · {{ run.error_message || run.skip_reason || run.result || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="run.situation || run.status" />
                </li>
              </ul>
            </UPageCard>

            <!-- findings -->
            <UPageCard
              v-else-if="tab === 'findings'"
              title="Findings"
              variant="subtle"
            >
              <div
                v-if="!findings.length"
                class="text-sm text-muted"
              >
                Nenhum finding ativo.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="f in findings"
                  :key="f.id"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div>
                    <p class="font-medium">
                      {{ f.title || f.code }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ f.detail || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="f.situation || f.severity" />
                </li>
              </ul>
            </UPageCard>

            <!-- pending -->
            <UPageCard
              v-else-if="tab === 'pending'"
              title="Pendências"
              variant="subtle"
            >
              <div
                v-if="!pending.length"
                class="text-sm text-muted"
              >
                Nenhuma pendência aberta.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="p in pending"
                  :key="p.id"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div>
                    <p class="font-medium">
                      {{ p.title || p.code }}
                    </p>
                    <p class="text-xs text-muted">
                      Venc.: {{ formatDateTime(p.due_at) }} · {{ p.detail || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="p.situation || p.status" />
                </li>
              </ul>
            </UPageCard>

            <!-- installments -->
            <UPageCard
              v-else-if="tab === 'installments'"
              title="Parcelamentos"
              description="Pedidos do cliente — lazy load desta aba."
              variant="subtle"
            >
              <div
                v-if="!installments.length"
                class="text-sm text-muted"
              >
                Nenhum pedido de parcelamento retornado.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="order in installments"
                  :key="String(order.id)"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div>
                    <p class="font-medium">
                      Pedido #{{ order.id }}
                      <span v-if="order.external_order_id">
                        · {{ order.external_order_id }}
                      </span>
                    </p>
                    <p class="text-xs text-muted">
                      {{ order.modality || order.modality_code || '—' }}
                      · {{ formatAmountCents(order.total_amount_cents as number | null) }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="String(order.situation || order.status || '')" />
                </li>
              </ul>
              <div class="mt-3">
                <UButton
                  size="sm"
                  color="neutral"
                  variant="soft"
                  :to="`/monitoring/installments?client_id=${clientId}`"
                  label="Abrir carteira de parcelamentos"
                />
              </div>
            </UPageCard>

            <!-- declarations -->
            <UPageCard
              v-else-if="tab === 'declarations'"
              title="Declarações"
              description="Projeções do cliente — lazy load desta aba."
              variant="subtle"
            >
              <div
                v-if="!declarations.length"
                class="text-sm text-muted"
              >
                Nenhuma declaração retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="d in declarations"
                  :key="String(d.id)"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div>
                    <p class="font-medium">
                      {{ d.obligation_name || d.obligation_code || `Decl. #${d.id}` }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ d.period_key || d.competence_period_key || '—' }}
                      · venc. {{ formatDateTime(String(d.due_at || '') || null) }}
                    </p>
                  </div>
                  <FiscalStatusBadge
                    :status="String(d.delivery_status || d.situation || d.status || '')"
                  />
                </li>
              </ul>
              <div class="mt-3">
                <UButton
                  size="sm"
                  color="neutral"
                  variant="soft"
                  :to="`/monitoring/declarations?client_id=${clientId}`"
                  label="Abrir central de declarações"
                />
              </div>
            </UPageCard>

            <!-- guides -->
            <UPageCard
              v-else-if="tab === 'guides'"
              title="Guias"
              description="amount_cents e payment_status independentes da emissão."
              variant="subtle"
            >
              <div
                v-if="!guides.length"
                class="text-sm text-muted"
              >
                Nenhuma guia retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="g in guides"
                  :key="String(g.id)"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div>
                    <p class="font-medium">
                      Guia #{{ g.id }} · {{ g.competence_period_key || g.period_key || '—' }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ formatAmountCents(g.amount_cents as number | null) }}
                      · emissão
                      {{
                        (g.current_version as Record<string, unknown> | undefined)?.emission_status
                          || g.emission_status
                          || '—'
                      }}
                    </p>
                  </div>
                  <div class="flex flex-col items-end gap-1">
                    <FiscalStatusBadge :status="String(g.payment_status || 'UNKNOWN')" />
                  </div>
                </li>
              </ul>
              <div class="mt-3">
                <UButton
                  size="sm"
                  color="neutral"
                  variant="soft"
                  :to="`/monitoring/guides?client_id=${clientId}`"
                  label="Abrir central de guias"
                />
              </div>
            </UPageCard>

            <!-- fgts (lazy + link para carteira) -->
            <UPageCard
              v-else-if="tab === 'fgts'"
              title="FGTS / eSocial"
              description="Cobertura parcial — competências do cliente (lazy)."
              variant="subtle"
            >
              <UAlert
                color="warning"
                icon="i-lucide-info"
                title="Cobertura parcial"
                description="Guia e pagamento FGTS Digital permanecem UNSUPPORTED sem fonte M2M oficial."
                class="mb-3"
              />
              <div
                v-if="!fgtsCompetences.length"
                class="text-sm text-muted"
              >
                Nenhuma competência FGTS retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="c in fgtsCompetences"
                  :key="String(c.id)"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div>
                    <p class="font-medium">
                      {{ c.competence_period_key || `Competência #${c.id}` }}
                    </p>
                    <p class="text-xs text-muted">
                      Fechamento: {{ c.closure_status || '—' }}
                      · Totalização: {{ c.totalization_status || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="String(c.situation || c.closure_status || '')" />
                </li>
              </ul>
              <div class="mt-3">
                <UButton
                  size="sm"
                  color="neutral"
                  variant="soft"
                  :to="`/monitoring/fgts?client_id=${clientId}`"
                  label="Abrir carteira FGTS"
                />
              </div>
            </UPageCard>

            <!-- sitfis (lazy + link) -->
            <UPageCard
              v-else-if="tab === 'sitfis'"
              title="Situação fiscal (SITFIS)"
              description="Snapshot do cliente — lazy load desta aba."
              variant="subtle"
            >
              <div
                v-if="!sitfis"
                class="text-sm text-muted"
              >
                Nenhum snapshot SITFIS retornado.
              </div>
              <dl
                v-else
                class="grid gap-2 text-sm sm:grid-cols-2"
              >
                <div>
                  <dt class="text-muted">
                    Situação
                  </dt>
                  <dd>
                    <FiscalStatusBadge :status="String(sitfis.situation || '')" />
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Observado
                  </dt>
                  <dd>{{ formatDateTime(String(sitfis.observed_at || sitfis.as_of || '') || null) }}</dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Protocolo
                  </dt>
                  <dd>{{ sitfis.protocol || sitfis.protocol_number || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Cobertura
                  </dt>
                  <dd>{{ sitfis.coverage || '—' }}</dd>
                </div>
              </dl>
              <div class="mt-3">
                <UButton
                  size="sm"
                  color="neutral"
                  variant="soft"
                  :to="`/monitoring/sitfis?client_id=${clientId}`"
                  label="Abrir carteira SITFIS"
                />
              </div>
            </UPageCard>
          </template>

          <div class="flex flex-wrap gap-2">
            <UButton
              size="sm"
              color="neutral"
              variant="soft"
              :to="`/monitoring/mailbox?client_id=${clientId}`"
              label="Caixa postal"
            />
            <UButton
              size="sm"
              color="neutral"
              variant="soft"
              :to="`/monitoring/dctfweb?client_id=${clientId}`"
              label="DCTFWeb"
            />
            <UButton
              size="sm"
              color="neutral"
              variant="soft"
              :to="`/monitoring/simples-mei?client_id=${clientId}`"
              label="Simples/MEI"
            />
            <UButton
              size="sm"
              color="neutral"
              variant="soft"
              :to="`/clients/${clientId}`"
              label="Cadastro completo"
            />
          </div>
        </template>
      </div>
    </template>
  </UDashboardPanel>
</template>
