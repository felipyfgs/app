<script setup lang="ts">
import type { Client, ClientCredential, Establishment } from '~/types/api'

const route = useRoute()
const api = useApi()
const toast = useToast()
const { canManageClients, canManageCredentials, canTriggerSync } = useDashboard()
const clientId = Number(route.params.id)
const item = ref<Client | null>(null)
const credential = ref<ClientCredential | null>(null)
const loading = ref(true)
const establishmentOpen = ref(false)
const credentialOpen = ref(false)
const savingEstablishment = ref(false)
const activatingCredential = ref(false)
const triggeringId = ref<number | null>(null)
const triggeredIds = ref<number[]>([])
const establishmentForm = reactive({ cnpj: '', trade_name: '', is_matrix: false })
const establishmentErrors = ref<Record<string, string[]>>({})
const credentialPassword = ref('')
const credentialFile = ref<File | null>(null)

const establishments = computed(() => item.value?.establishments || [])
const onboardingSteps = computed(() => [{
  title: 'Cliente',
  description: 'Raiz cadastrada',
  complete: !!item.value
}, {
  title: 'Estabelecimento',
  description: establishments.value.length ? `${establishments.value.length} cadastrado(s)` : 'Adicione matriz ou filial',
  complete: establishments.value.length > 0
}, {
  title: 'Certificado A1',
  description: canManageCredentials.value
    ? (credential.value ? 'Validado e ativo' : 'Envie o PFX')
    : 'Gerenciado por ADMIN',
  complete: canManageCredentials.value ? !!credential.value : false
}, {
  title: 'Primeira sincronização',
  description: triggeredIds.value.length ? 'Solicitada' : 'Dispare após ativar o A1',
  complete: triggeredIds.value.length > 0
}])

async function load() {
  loading.value = true
  if (!Number.isInteger(clientId) || clientId <= 0) {
    loading.value = false
    return
  }

  try {
    item.value = (await api.clients.get(clientId)).data
    if (canManageCredentials.value) {
      credential.value = (await api.credentials.get(clientId)).data
    }
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível carregar o cliente.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

async function createEstablishment() {
  if (!canManageClients.value) {
    return
  }

  establishmentErrors.value = {}
  savingEstablishment.value = true
  try {
    await api.establishments.create(clientId, { ...establishmentForm })
    establishmentOpen.value = false
    establishmentForm.cnpj = ''
    establishmentForm.trade_name = ''
    establishmentForm.is_matrix = false
    toast.add({ title: 'Estabelecimento cadastrado.', color: 'success' })
    await load()
  } catch (caught) {
    establishmentErrors.value = apiFieldErrors(caught)
    toast.add({ title: apiErrorMessage(caught, 'Falha ao cadastrar estabelecimento.'), color: 'error' })
  } finally {
    savingEstablishment.value = false
  }
}

function selectCredentialFile(event: Event) {
  const input = event.target as HTMLInputElement
  credentialFile.value = input.files?.[0] || null
}

async function activateCredential() {
  if (!canManageCredentials.value) {
    return
  }

  if (!credentialFile.value) {
    toast.add({ title: 'Selecione um arquivo PFX.', color: 'warning' })
    return
  }
  activatingCredential.value = true
  try {
    credential.value = (await api.credentials.activate(clientId, credentialFile.value, credentialPassword.value)).data
    credentialOpen.value = false
    credentialPassword.value = ''
    credentialFile.value = null
    toast.add({
      title: 'Certificado validado e ativado.',
      description: 'Senha, validade, titular, fingerprint e raiz foram verificados.',
      color: 'success'
    })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível ativar o certificado.'), color: 'error' })
  } finally {
    activatingCredential.value = false
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

onMounted(load)
</script>

<template>
  <UDashboardPanel id="client-detail">
    <template #header>
      <UDashboardNavbar :title="item?.name || 'Cliente'">
        <template #leading>
          <div class="flex items-center gap-1">
            <UDashboardSidebarCollapse />
            <UButton
              to="/clients"
              color="neutral"
              variant="ghost"
              icon="i-lucide-arrow-left"
              square
              aria-label="Voltar para clientes"
            />
          </div>
        </template>
        <template #right>
          <UBadge v-if="item" :color="item.is_active ? 'success' : 'neutral'" variant="subtle">
            {{ item.is_active ? 'Ativo' : 'Inativo' }}
          </UBadge>
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div v-if="loading" class="space-y-4">
        <USkeleton class="h-28 w-full" />
        <USkeleton class="h-52 w-full" />
      </div>

      <UEmpty
        v-else-if="!item"
        icon="i-lucide-building-x"
        title="Cliente não encontrado"
        description="O registro não existe ou pertence a outro escritório."
      >
        <UButton to="/clients" label="Voltar para clientes" />
      </UEmpty>

      <template v-else>
        <UCard>
          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="(step, index) in onboardingSteps" :key="step.title" class="flex gap-3">
              <div
                class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                :class="step.complete ? 'bg-success/15 text-success' : 'bg-elevated text-muted'"
              >
                <UIcon v-if="step.complete" name="i-lucide-check" class="size-4" />
                <span v-else>{{ index + 1 }}</span>
              </div>
              <div>
                <p class="font-medium text-highlighted">
                  {{ step.title }}
                </p>
                <p class="text-xs text-muted">
                  {{ step.description }}
                </p>
              </div>
            </div>
          </div>
        </UCard>

        <div class="grid gap-4 xl:grid-cols-3">
          <UCard class="xl:col-span-2">
            <template #header>
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h2 class="font-semibold">
                    Estabelecimentos
                  </h2>
                  <p class="text-sm text-muted">
                    CNPJ completo, numérico ou alfanumérico.
                  </p>
                </div>
                <UButton
                  v-if="canManageClients"
                  icon="i-lucide-plus"
                  label="Adicionar"
                  size="sm"
                  @click="establishmentOpen = true"
                />
              </div>
            </template>

            <div v-if="establishments.length" class="divide-y divide-default">
              <div
                v-for="establishment in establishments"
                :key="establishment.id"
                class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ establishment.trade_name || establishment.cnpj }}</span>
                    <UBadge v-if="establishment.is_matrix" color="info" variant="subtle">
                      Matriz
                    </UBadge>
                    <UBadge :color="establishment.is_active ? 'success' : 'neutral'" variant="subtle">
                      {{ establishment.is_active ? 'Ativo' : 'Inativo' }}
                    </UBadge>
                  </div>
                  <p class="font-mono text-sm text-muted">
                    {{ establishment.cnpj }}
                  </p>
                </div>
                <UButton
                  v-if="canTriggerSync"
                  icon="i-lucide-refresh-cw"
                  label="Sincronizar"
                  color="neutral"
                  variant="subtle"
                  size="sm"
                  :loading="triggeringId === establishment.id"
                  :disabled="!establishment.is_active || (canManageCredentials && !credential)"
                  @click="triggerSync(establishment)"
                />
              </div>
            </div>
            <UEmpty
              v-else
              icon="i-lucide-map-pin-plus"
              title="Nenhum estabelecimento"
              description="Adicione a matriz ou uma filial para continuar."
            />
          </UCard>

          <UCard>
            <template #header>
              <div>
                <h2 class="font-semibold">
                  Certificado A1
                </h2>
                <p class="text-sm text-muted">
                  Um certificado por raiz do cliente.
                </p>
              </div>
            </template>

            <div v-if="!canManageCredentials" class="space-y-3">
              <UAlert
                color="info"
                icon="i-lucide-lock-keyhole"
                title="Acesso administrativo"
                description="Somente ADMIN pode consultar metadados ou substituir o A1."
              />
            </div>
            <div v-else-if="credential" class="space-y-3 text-sm">
              <AppStatusBadge :status="credential.status" />
              <dl class="space-y-2">
                <div>
                  <dt class="text-muted">
                    Titular
                  </dt>
                  <dd class="break-words text-highlighted">
                    {{ credential.subject_name }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    CNPJ
                  </dt>
                  <dd class="font-mono text-highlighted">
                    {{ credential.holder_cnpj }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Validade
                  </dt>
                  <dd class="text-highlighted">
                    {{ formatDateTime(credential.valid_to) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Fingerprint SHA-256
                  </dt>
                  <dd class="break-all font-mono text-xs text-highlighted">
                    {{ credential.fingerprint_sha256 }}
                  </dd>
                </div>
              </dl>
              <UAlert
                v-if="credential.expires_alert_30"
                color="warning"
                title="Certificado próximo do vencimento"
              />
              <UButton
                block
                color="neutral"
                variant="outline"
                @click="credentialOpen = true"
              >
                Substituir certificado
              </UButton>
            </div>
            <UEmpty
              v-else
              icon="i-lucide-badge-alert"
              title="A1 não configurado"
              description="O upload valida senha, titular, raiz, validade e fingerprint antes da ativação."
            >
              <UButton label="Enviar e validar A1" @click="credentialOpen = true" />
            </UEmpty>
          </UCard>
        </div>

        <UAlert
          icon="i-lucide-info"
          title="Cobertura do ADN"
          description="A primeira sincronização começa no NSU zero. Documentos não compartilhados no ADN não são obtidos por scraping ou API municipal."
        />
      </template>

      <UModal
        v-if="canManageClients"
        v-model:open="establishmentOpen"
        title="Adicionar estabelecimento"
      >
        <template #body>
          <form class="space-y-4" @submit.prevent="createEstablishment">
            <UFormField label="CNPJ completo" required :error="establishmentErrors.cnpj?.[0]">
              <UInput
                v-model="establishmentForm.cnpj"
                class="w-full"
                required
                autocomplete="off"
              />
            </UFormField>
            <UFormField label="Nome fantasia" :error="establishmentErrors.trade_name?.[0]">
              <UInput v-model="establishmentForm.trade_name" class="w-full" />
            </UFormField>
            <UCheckbox v-model="establishmentForm.is_matrix" label="Este estabelecimento é a matriz" />
            <div class="flex justify-end gap-2">
              <UButton
                color="neutral"
                variant="ghost"
                type="button"
                @click="establishmentOpen = false"
              >
                Cancelar
              </UButton>
              <UButton type="submit" :loading="savingEstablishment">
                Adicionar
              </UButton>
            </div>
          </form>
        </template>
      </UModal>

      <UModal
        v-if="canManageCredentials"
        v-model:open="credentialOpen"
        title="Enviar certificado A1"
        description="O PFX e a senha são usados somente para validação e armazenados juntos no cofre criptografado. Nunca poderão ser recuperados pela API."
      >
        <template #body>
          <form class="space-y-4" @submit.prevent="activateCredential">
            <UFormField label="Arquivo PFX" required help="Máximo de 5 MB.">
              <input
                id="credential-pfx"
                name="pfx"
                type="file"
                accept=".pfx,.p12,application/x-pkcs12"
                class="block w-full rounded-md border border-default bg-default px-3 py-2 text-sm"
                required
                @change="selectCredentialFile"
              >
            </UFormField>
            <UFormField label="Senha do certificado" required>
              <UInput
                v-model="credentialPassword"
                type="password"
                autocomplete="off"
                class="w-full"
                required
              />
            </UFormField>
            <UAlert
              color="warning"
              icon="i-lucide-shield-alert"
              title="Substituição atômica"
              description="O certificado atual só será substituído se o novo arquivo passar por todas as validações."
            />
            <div class="flex justify-end gap-2">
              <UButton
                color="neutral"
                variant="ghost"
                type="button"
                @click="credentialOpen = false"
              >
                Cancelar
              </UButton>
              <UButton type="submit" :loading="activatingCredential">
                Validar e ativar
              </UButton>
            </div>
          </form>
        </template>
      </UModal>
    </template>
  </UDashboardPanel>
</template>
