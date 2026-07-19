<script setup lang="ts">
/**
 * Shell do detalhe do cliente — arquétipo settings (Conta).
 * Fonte: `.local/reference/nuxt-dashboard-template/app/pages/settings.vue`
 * 4 destinos de toolbar; Fiscal/Integrações são hubs.
 */
import type { Client, ClientCredential, Establishment } from '~/types/api'
import { clientDetailKey, clientSectionPath } from '~/composables/useClientDetail'
import { clientNavigationMenu } from '~/utils/client-detail-navigation'
import type { ClientDetailPanel, ClientDetailTab } from '~/utils/client-detail-tabs'
import {
  clientDetailHref,
  legacySectionToHref,
  queryToClientDetailHref
} from '~/utils/client-detail-tabs'

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

const navbarTitle = computed(() =>
  item.value?.display_name
  || item.value?.legal_name
  || item.value?.name
  || 'Cliente'
)

const links = computed(() =>
  clientNavigationMenu(clientId.value, route.path)
)

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

function goToTab(tab: ClientDetailTab, panel?: ClientDetailPanel) {
  void navigateTo(clientDetailHref(clientId.value, tab, panel))
}

function goEditCadastro() {
  if (!item.value || !canManageClients.value) return
  registrationEditRequested.value = true
  goToTab('cadastro')
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
  sectionPath,
  goToTab
})

/** `/clients/:id` → `/clients/:id/cadastro` */
async function ensureCanonicalChild() {
  const path = route.path.replace(/\/+$/, '')
  if (!/^\/clients\/\d+$/.test(path)) return

  if (typeof route.query.tab === 'string' || typeof route.query.section === 'string') {
    const fromSection = typeof route.query.section === 'string'
      ? legacySectionToHref(clientId.value, route.query.section)
      : null
    const href = fromSection || queryToClientDetailHref(clientId.value, route.query as Record<string, unknown>)
    await router.replace(href)
    return
  }

  await router.replace(clientDetailHref(clientId.value, 'cadastro'))
}

/** Query legada em qualquer filho → path. */
async function migrateLegacyQuery() {
  if (typeof route.query.tab !== 'string' && typeof route.query.section !== 'string') return
  const fromSection = typeof route.query.section === 'string'
    ? legacySectionToHref(clientId.value, route.query.section)
    : null
  const href = fromSection || queryToClientDetailHref(clientId.value, route.query as Record<string, unknown>)
  await router.replace(href)
}

watch(clientId, () => {
  triggeredIds.value = []
  load()
})

watch(
  () => route.fullPath,
  async () => {
    await ensureCanonicalChild()
    await migrateLegacyQuery()
  }
)

onMounted(async () => {
  await ensureCanonicalChild()
  await migrateLegacyQuery()
  await load()
})
</script>

<template>
  <ShellSettingsShell
    id="client-detail"
    :title="navbarTitle"
    width="comfortable"
    test-id="client-detail-panel"
    toolbar-test-id="client-section-tabs"
  >
    <template #navbar-leading>
      <ShellNavbarBack
        to="/clients"
        label="Voltar aos clientes"
        aria-label="Voltar aos clientes"
        test-id="client-detail-back"
      />
    </template>

    <template
      v-if="item && canManageClients"
      #navbar-right
    >
      <UButton
        color="primary"
        variant="soft"
        icon="i-lucide-pencil"
        label="Editar cliente"
        class="hidden sm:inline-flex"
        data-testid="client-page-edit"
        @click="goEditCadastro"
      />
      <UButton
        color="primary"
        variant="soft"
        icon="i-lucide-pencil"
        square
        class="sm:hidden"
        aria-label="Editar cliente"
        data-testid="client-page-edit-mobile"
        @click="goEditCadastro"
      />
    </template>

    <template
      v-if="item"
      #toolbar
    >
      <UNavigationMenu
        :items="links"
        highlight
        class="-mx-1 flex-1"
        data-testid="client-section-navigation"
        aria-label="Navegação do cliente"
      />
    </template>

    <div
      v-if="loading && !item"
      class="space-y-4"
      role="status"
      aria-label="Carregando cliente"
    >
      <USkeleton class="h-10 w-48 rounded-lg" />
      <USkeleton class="h-96 w-full rounded-lg" />
    </div>

    <UEmpty
      v-else-if="!item"
      icon="i-lucide-building-2"
      title="Cliente não encontrado"
      description="O registro não existe ou pertence a outro escritório."
    >
      <UButton
        to="/clients"
        label="Voltar para clientes"
      />
    </UEmpty>

    <NuxtPage v-else />
  </ShellSettingsShell>
</template>
