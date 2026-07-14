<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import type { Client, ClientCredential, Establishment } from '~/types/api'

type Section = 'resumo' | 'cadastro' | 'estabelecimentos' | 'certificado' | 'sincronizacao'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  clientId: number | null
  initialSection?: Section
}>()

const emit = defineEmits<{
  updated: []
}>()

const api = useApi()
const toast = useToast()
const {
  canManageClients,
  canManageCredentials,
  canTriggerSync
} = useDashboard()

const section = ref<Section>('resumo')
const item = ref<Client | null>(null)
const credential = ref<ClientCredential | null>(null)
const loading = ref(false)
const triggeringId = ref<number | null>(null)
const triggeredIds = ref<number[]>([])


const establishments = computed(() => item.value?.establishments || [])

const title = computed(() =>
  item.value?.display_name || item.value?.legal_name || item.value?.name || 'Cliente'
)

const description = computed(() => {
  if (!item.value) {
    return loading.value ? 'Carregando detalhes…' : 'Detalhes do cliente'
  }
  const cnpj = item.value.cnpj || item.value.establishments?.[0]?.cnpj || item.value.root_cnpj
  return `CNPJ ${cnpj}`
})

/**
 * Mesmo padrão do template Settings (`UDashboardToolbar` + `UNavigationMenu` highlight).
 * Sem rotas: onSelect troca a seção local do modal.
 * Sem aba Estabelecimentos: 1 cliente = 1 CNPJ.
 */
const sectionLinks = computed<NavigationMenuItem[]>(() => {
  const items: Array<{ key: Section, label: string, icon: string }> = [
    { key: 'resumo', label: 'Resumo', icon: 'i-lucide-layout-dashboard' },
    { key: 'cadastro', label: 'Cadastro', icon: 'i-lucide-clipboard-list' },
    { key: 'estabelecimentos', label: 'Estabelecimentos', icon: 'i-lucide-map-pin-house' },
    { key: 'certificado', label: 'Certificado A1', icon: 'i-lucide-badge-check' },
    { key: 'sincronizacao', label: 'Sincronização', icon: 'i-lucide-refresh-cw' }
  ]

  return items.map(item => ({
    label: item.label,
    icon: item.icon,
    active: section.value === item.key,
    onSelect: () => {
      section.value = item.key
    }
  }))
})

async function load() {
  if (!props.clientId) {
    item.value = null
    return
  }
  loading.value = true
  try {
    item.value = (await api.clients.get(props.clientId)).data
    if (canManageCredentials.value) {
      credential.value = (await api.credentials.get(props.clientId)).data
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

async function reload() {
  await load()
  emit('updated')
}

async function triggerSync(establishment: Establishment) {
  if (!canTriggerSync.value) return
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
  emit('updated')
}

const cadastroStartEditing = ref(false)

function goSection(next: Section) {
  section.value = next
  if (next !== 'cadastro') {
    cadastroStartEditing.value = false
  }
}

function openEditForm() {
  if (!item.value || !canManageClients.value) return
  section.value = 'cadastro'
  cadastroStartEditing.value = true
}

function resetState() {
  item.value = null
  credential.value = null
  section.value = 'resumo'
  triggeringId.value = null
  triggeredIds.value = []
  cadastroStartEditing.value = false
}

watch(
  () => [open.value, props.clientId, props.initialSection] as const,
  async ([isOpen, id, initial]) => {
    if (!isOpen || !id) {
      return
    }
    section.value = initial || 'resumo'
    triggeredIds.value = []
    await load()
  }
)

watch(open, (value) => {
  if (!value) {
    resetState()
  }
})
</script>

<template>
  <!--
    Template Settings: subnav = UNavigationMenu + highlight (não UTabs).
    Modal Nuxt UI: title/description/#actions + #body + #footer.
  -->
  <UModal
    v-model:open="open"
    data-testid="client-detail-modal"
    :title="title"
    :description="description"
    :ui="{
      content: 'w-[calc(100vw-1.5rem)] sm:w-[min(72rem,calc(100vw-2rem))] sm:max-w-none h-[min(90dvh,52rem)] max-h-[min(90dvh,52rem)] overflow-hidden',
      body: 'flex min-h-0 flex-1 flex-col overflow-hidden p-0 sm:p-0',
      footer: 'justify-between gap-2 shrink-0'
    }"
  >
    <template #actions>
      <UBadge
        v-if="item"
        :color="item.is_active ? 'success' : 'neutral'"
        variant="subtle"
        class="me-8"
      >
        {{ item.is_active ? 'Ativo' : 'Inativo' }}
      </UBadge>
    </template>

    <template #body>
      <!-- Equivalente ao UDashboardToolbar do settings.vue do template -->
      <div class="shrink-0 border-b border-default px-2 sm:px-3">
        <UNavigationMenu
          :items="sectionLinks"
          highlight
          class="-mx-1 flex-1 overflow-x-auto overflow-y-hidden [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
        />
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6 sm:py-5">
        <div v-if="loading && !item" class="space-y-4" role="status" aria-label="Carregando cliente">
          <USkeleton class="h-24 w-full" />
          <USkeleton class="h-40 w-full" />
          <USkeleton class="h-28 w-full" />
        </div>

        <UEmpty
          v-else-if="!item"
          icon="i-lucide-building-2"
          title="Cliente não encontrado"
          description="O registro não existe ou pertence a outro escritório."
        />

        <template v-else>
          <ClientsClientDashboard
            v-if="section === 'resumo'"
            :client="item"
            :credential="credential"
            :establishments="establishments"
            :triggered-ids="triggeredIds"
            :can-manage-credentials="canManageCredentials"
            :can-manage-clients="canManageClients"
            :can-trigger-sync="canTriggerSync"
            :in-modal="true"
            @navigate-section="goSection"
          />

          <ClientsClientRegistration
            v-else-if="section === 'cadastro'"
            :client="item"
            :can-manage-clients="canManageClients"
            :start-editing="cadastroStartEditing"
            @updated="() => { cadastroStartEditing = false; reload() }"
          />

          <ClientsClientBranchesPanel
            v-else-if="section === 'estabelecimentos'"
            :client="item"
            :establishments="establishments"
            :can-manage-clients="canManageClients"
            :can-manage-credentials="canManageCredentials"
            @updated="reload"
            @branch-created="() => reload()"
          />

          <ClientsClientCredentialPanel
            v-else-if="section === 'certificado'"
            :client-id="item.id"
            :credential="credential"
            :credential-summary="item.credential_summary"
            :can-manage-credentials="canManageCredentials"
            @activated="onCredentialActivated"
          />

          <ClientsClientSyncPanel
            v-else-if="section === 'sincronizacao'"
            :establishments="establishments"
            :credential="credential"
            :credential-summary="item.credential_summary"
            :can-trigger-sync="canTriggerSync"
            :can-manage-credentials="canManageCredentials"
            :triggering-id="triggeringId"
            :triggered-ids="triggeredIds"
            @sync="triggerSync"
          />
        </template>
      </div>
    </template>

    <template #footer="{ close }">
      <div class="flex w-full flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-muted">
          <template v-if="item">
            CNPJ {{ item.cnpj || establishments[0]?.cnpj || item.root_cnpj }}
          </template>
          <template v-if="!canManageClients && item">
            · visualização
          </template>
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <template v-if="item && canManageClients">
            <UButton
              v-if="section === 'resumo' || section === 'cadastro'"
              color="primary"
              variant="soft"
              size="sm"
              icon="i-lucide-pencil"
              label="Editar cadastro"
              data-testid="client-detail-edit"
              @click="openEditForm"
            />
          </template>
          <UButton
            v-if="item"
            color="neutral"
            variant="ghost"
            size="sm"
            icon="i-lucide-external-link"
            label="Página"
            :to="clientSectionPath(item.id)"
          />
          <UButton
            color="neutral"
            variant="subtle"
            size="sm"
            label="Fechar"
            @click="close()"
          />
        </div>
      </div>
    </template>
  </UModal>

</template>
