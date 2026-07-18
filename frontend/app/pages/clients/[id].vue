<script setup lang="ts">
/**
 * Página de detalhe do cliente — arquétipo Settings do template
 * + layout de detalhe (header identidade + main + aside), inspirado em HubStrom.
 * Fonte template: .reference/nuxt-dashboard-template/app/pages/settings.vue
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { Client, ClientCredential, Establishment } from '~/types/api'
import { clientDetailKey, clientSectionPath } from '~/composables/useClientDetail'

const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const {
  canManageClients,
  canManageCredentials,
  canTriggerSync
} = useDashboard()

const clientId = computed(() => Number(route.params.id))
const item = ref<Client | null>(null)
const credential = ref<ClientCredential | null>(null)
const loading = ref(true)
const triggeringId = ref<number | null>(null)
const triggeredIds = ref<number[]>([])
const registrationEditRequested = ref(false)

const establishments = computed(() => item.value?.establishments || [])

/**
 * Subnav horizontal no toolbar — mesmo contrato do template settings.vue
 * (`NavigationMenuItem[][]` + `UNavigationMenu` highlight).
 */
const sectionLinks = computed<NavigationMenuItem[][]>(() => {
  const id = clientId.value
  const path = route.path.replace(/\/$/, '')
  const base = `/clients/${id}`

  return [[{
    label: 'Resumo',
    icon: 'i-lucide-layout-dashboard',
    to: base,
    exact: true,
    active: path === base
  }, {
    label: 'Cadastro',
    icon: 'i-lucide-clipboard-list',
    to: `${base}/cadastro`,
    active: path.endsWith('/cadastro')
  }, {
    label: 'Estabelecimentos',
    icon: 'i-lucide-map-pin-house',
    to: `${base}/estabelecimentos`,
    active: path.endsWith('/estabelecimentos')
  }, {
    label: 'Certificado A1',
    icon: 'i-lucide-badge-check',
    to: `${base}/certificado`,
    active: path.endsWith('/certificado')
  }, {
    label: 'Sincronização',
    icon: 'i-lucide-refresh-cw',
    to: `${base}/sincronizacao`,
    active: path.endsWith('/sincronizacao')
  }, {
    label: 'CCMEI',
    icon: 'i-lucide-badge-check',
    to: `${base}/ccmei`,
    active: path.endsWith('/ccmei')
  }, {
    label: 'Receitas SICALC',
    icon: 'i-lucide-receipt-text',
    to: `${base}/sicalc`,
    active: path.endsWith('/sicalc')
  }, {
    label: 'Pagamentos',
    icon: 'i-lucide-chart-no-axes-column',
    to: `${base}/pagamentos`,
    active: path.endsWith('/pagamentos')
  }, {
    label: 'Captura de saídas',
    icon: 'i-lucide-arrow-up-from-line',
    to: `${base}/saidas`,
    active: path.endsWith('/saidas')
  }]]
})

async function load() {
  loading.value = true
  const id = clientId.value
  if (!Number.isInteger(id) || id <= 0) {
    item.value = null
    loading.value = false
    return
  }

  try {
    item.value = (await api.clients.get(id)).data
    if (canManageCredentials.value) {
      credential.value = (await api.credentials.get(id)).data
    } else {
      credential.value = null
    }
  } catch (caught) {
    item.value = null
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível carregar o cliente.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

async function triggerSync(establishment: Establishment) {
  if (!canTriggerSync.value) {
    return
  }

  triggeringId.value = establishment.id
  try {
    await api.sync.trigger(establishment.id)
    if (!triggeredIds.value.includes(establishment.id)) {
      triggeredIds.value.push(establishment.id)
    }
    toast.add({ title: `Sincronização de ${establishment.cnpj} enfileirada.`, color: 'success' })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível iniciar a sincronização.'), color: 'error' })
  } finally {
    triggeringId.value = null
  }
}

function onCredentialActivated(value: ClientCredential) {
  credential.value = value
  // Mantém summary alinhado para KPIs/aside (mesmo sem re-fetch).
  if (item.value) {
    item.value = {
      ...item.value,
      credential_summary: {
        status: value.status,
        valid_to: value.valid_to,
        expires_alert_30: value.expires_alert_30,
        expires_alert_7: value.expires_alert_7,
        expires_alert_1: value.expires_alert_1
      }
    }
  }
}

function sectionPath(section?: string) {
  return clientSectionPath(clientId.value, section)
}

/** Editar → aba Cadastro com formulário desbloqueado */
function goEditCadastro() {
  if (!item.value || !canManageClients.value) return
  registrationEditRequested.value = true
  navigateTo(clientSectionPath(item.value.id, 'cadastro'))
}

provide(clientDetailKey, {
  clientId,
  item,
  credential,
  loading,
  establishments,
  triggeringId,
  triggeredIds,
  registrationEditRequested,
  canManageClients,
  canManageCredentials,
  canTriggerSync,
  load,
  triggerSync,
  onCredentialActivated,
  sectionPath
})

/**
 * Compat: URLs antigas ?section=X → /clients/:id[/X]
 * Padrão Nuxt/template Settings: rotas aninhadas, não query.
 */
async function migrateLegacySectionQuery() {
  const raw = route.query.section
  if (typeof raw !== 'string' || raw === '') {
    return
  }

  const allowed = new Set(['resumo', 'cadastro', 'estabelecimentos', 'certificado', 'sincronizacao'])
  if (!allowed.has(raw)) {
    return
  }

  const { section: _drop, ...rest } = route.query
  await router.replace({
    path: clientSectionPath(clientId.value, raw),
    query: rest
  })
}

watch(clientId, () => {
  triggeredIds.value = []
  load()
})

onMounted(async () => {
  await migrateLegacySectionQuery()
  await load()
})
</script>

<template>
  <!--
    Arquétipo settings seções (template settings.vue):
    Navbar + Toolbar UNavigationMenu highlight + body com NuxtPage.
    Header de identidade e aside são adaptação de domínio (não do demo).
  -->
  <UDashboardPanel id="client-detail" data-testid="settings-panel" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar title="Clientes" data-testid="page-navbar">
        <template #leading>
          <div class="flex items-center gap-1">
            <UDashboardSidebarCollapse />
            <UButton
              to="/clients"
              color="neutral"
              variant="ghost"
              icon="i-lucide-arrow-left"
              label="Voltar aos clientes"
              class="hidden sm:inline-flex"
            />
            <UButton
              to="/clients"
              color="neutral"
              variant="ghost"
              icon="i-lucide-arrow-left"
              square
              class="sm:hidden"
              aria-label="Voltar aos clientes"
            />
          </div>
        </template>
        <template #right>
          <UButton
            v-if="item && canManageClients"
            color="primary"
            variant="soft"
            icon="i-lucide-pencil"
            label="Editar cliente"
            class="hidden sm:inline-flex"
            @click="goEditCadastro"
          />
          <UButton
            v-if="item && canManageClients"
            color="primary"
            variant="soft"
            icon="i-lucide-pencil"
            square
            class="sm:hidden"
            aria-label="Editar cliente"
            @click="goEditCadastro"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar v-if="item" data-testid="client-section-tabs">
        <!-- NOTE: The `-mx-1` class is used to align with the `DashboardSidebarCollapse` button here. -->
        <UNavigationMenu :items="sectionLinks" highlight class="-mx-1 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <DashboardContent width="wide" class="gap-4 sm:gap-6 lg:gap-12">
        <div
          v-if="loading && !item"
          class="space-y-4"
          role="status"
          aria-label="Carregando cliente"
        >
          <USkeleton class="h-28 w-full rounded-lg" />
          <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <USkeleton class="h-96 w-full rounded-lg" />
            <div class="space-y-4">
              <USkeleton class="h-40 w-full rounded-lg" />
              <USkeleton class="h-32 w-full rounded-lg" />
            </div>
          </div>
        </div>

        <UEmpty
          v-else-if="!item"
          icon="i-lucide-building-2"
          title="Cliente não encontrado"
          description="O registro não existe ou pertence a outro escritório."
        >
          <UButton to="/clients" label="Voltar para clientes" />
        </UEmpty>

        <template v-else>
          <ClientsClientDetailHeader
            :client="item"
            :establishments="establishments"
            :can-manage-clients="canManageClients"
            @edit="goEditCadastro"
          />

          <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem] xl:items-start">
            <div class="min-w-0">
              <NuxtPage />
            </div>

            <aside class="xl:sticky xl:top-4">
              <ClientsClientDetailAside
                :client="item"
                :credential="credential"
                :establishments="establishments"
                :can-manage-credentials="canManageCredentials"
              />
            </aside>
          </div>
        </template>
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
