<script setup lang="ts">
/**
 * Detalhe de Office (pendente: regenerar / corrigir 1º ADMIN).
 */
import type { ActivationMethod, CredentialDeliveryPayload, PlatformOfficeAdminDetail } from '~/types/api'
import { apiErrorMessage } from '~/utils/api-error'

const route = useRoute()
const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const officeId = computed(() => Number(route.params.id))
const office = ref<PlatformOfficeAdminDetail | null>(null)
const loading = ref(false)
const loadError = ref<string | null>(null)
const acting = ref(false)
const secret = ref<CredentialDeliveryPayload | null>(null)

const reconfirmPassword = ref('')
const method = ref<ActivationMethod>('MANUAL_LINK')
const correctOpen = ref(false)
const correctName = ref('')
const correctEmail = ref('')

const methodItems = [
  { label: 'Link manual', value: 'MANUAL_LINK' as ActivationMethod },
  { label: 'Senha provisória', value: 'TEMPORARY_PASSWORD' as ActivationMethod }
]

const isPending = computed(() => office.value?.lifecycle_status === 'PENDING_ACTIVATION')

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.offices.show(officeId.value)
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    office.value = res.data
    if (office.value?.first_admin) {
      correctName.value = office.value.first_admin.name || ''
      correctEmail.value = office.value.first_admin.email || ''
    }
  } catch (e) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    office.value = null
    loadError.value = apiErrorMessage(e, 'Falha ao carregar o escritório.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

function showSecret(payload: CredentialDeliveryPayload) {
  if (payload.credential_delivery === 'delivered'
    && (payload.activation_url || payload.temporary_password)) {
    secret.value = payload
  } else {
    secret.value = null
    if (payload.credential_delivery === 'regeneration_required') {
      toast.add({
        title: 'Segredo não recuperável. Use Regenerar acesso.',
        color: 'warning'
      })
    }
  }
}

function clearSecret() {
  secret.value = null
}

async function ensurePassword(): Promise<boolean> {
  if (!reconfirmPassword.value) {
    toast.add({ title: 'Informe sua senha para continuar.', color: 'warning' })
    return false
  }
  try {
    await api.confirmPassword(reconfirmPassword.value)
    return true
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Senha inválida.'), color: 'error' })
    return false
  }
}

async function regenerate() {
  if (!(await ensurePassword())) return
  acting.value = true
  try {
    const res = await api.platform.offices.regenerateActivation(officeId.value, {
      method: method.value
    })
    showSecret(res.data)
    toast.add({ title: 'Acesso regenerado.', color: 'success' })
    reconfirmPassword.value = ''
    await load()
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao regenerar.'), color: 'error' })
  } finally {
    acting.value = false
  }
}

async function correctFirstAdmin() {
  if (!(await ensurePassword())) return
  acting.value = true
  try {
    const res = await api.platform.offices.updateFirstAdmin(officeId.value, {
      name: correctName.value.trim(),
      email: correctEmail.value.trim(),
      method: method.value
    })
    showSecret(res.data)
    toast.add({ title: 'Primeiro administrador corrigido.', color: 'success' })
    correctOpen.value = false
    reconfirmPassword.value = ''
    await load()
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao corrigir.'), color: 'error' })
  } finally {
    acting.value = false
  }
}

watch(sessionEpoch, () => {
  clearSecret()
  void load()
})
onMounted(load)
onBeforeUnmount(clearSecret)
</script>

<template>
  <UDashboardPanel
    id="admin-office-detail"
    data-testid="admin-office-detail"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        :title="office?.name || 'Escritório'"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            to="/admin/offices"
            color="neutral"
            variant="ghost"
            icon="i-lucide-arrow-left"
            label="Lista"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <DashboardContent width="comfortable" class="gap-4 sm:gap-6">
        <div
          v-if="loading && !office"
          class="space-y-3"
        >
          <USkeleton class="h-8 w-1/2" />
          <USkeleton class="h-24 w-full" />
        </div>

        <UAlert
          v-else-if="loadError"
          color="error"
          icon="i-lucide-circle-x"
          :title="loadError"
          :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
        />

        <template v-else-if="office">
          <ActivationOneTimeSecret
            v-if="secret"
            :activation-url="secret.activation_url"
            :temporary-password="secret.temporary_password"
            :expires-at="secret.expires_at"
            :method="secret.method"
          />

          <UPageCard
            variant="subtle"
            data-testid="admin-office-summary"
          >
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  Estado
                </dt>
                <dd>
                  <UBadge
                    size="sm"
                    variant="subtle"
                    :color="isPending ? 'warning' : 'success'"
                    :label="isPending ? 'Pendente' : 'Ativo'"
                  />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Slug
                </dt>
                <dd class="text-highlighted">
                  {{ office.slug }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Plano
                </dt>
                <dd class="text-highlighted">
                  {{ office.subscription?.plan || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Ativação
                </dt>
                <dd class="text-highlighted">
                  <template v-if="office.activation">
                    {{ office.activation.method }} · {{ office.activation.status }}
                    <span
                      v-if="office.activation.expires_at"
                      class="text-muted"
                    >
                      · expira {{ formatDateTime(office.activation.expires_at) }}
                    </span>
                  </template>
                  <template v-else>
                    —
                  </template>
                </dd>
              </div>
              <div v-if="office.profile">
                <dt class="text-muted">
                  CNPJ
                </dt>
                <dd class="text-highlighted">
                  {{ office.profile.cnpj }}
                </dd>
              </div>
              <div v-if="office.profile">
                <dt class="text-muted">
                  Razão social
                </dt>
                <dd class="text-highlighted">
                  {{ office.profile.legal_name }}
                </dd>
              </div>
              <div v-if="office.first_admin">
                <dt class="text-muted">
                  1º administrador
                </dt>
                <dd class="text-highlighted">
                  {{ office.first_admin.name }} · {{ office.first_admin.email }}
                </dd>
              </div>
            </dl>
          </UPageCard>

          <UPageCard
            v-if="isPending"
            title="Ações de ativação"
            variant="subtle"
            data-testid="admin-office-pending-actions"
          >
            <div class="space-y-4">
              <UFormField label="Método de entrega">
                <USelect
                  v-model="method"
                  :items="methodItems"
                  value-key="value"
                  label-key="label"
                  class="w-full sm:max-w-xs"
                />
              </UFormField>
              <UFormField
                label="Sua senha"
                required
              >
                <UInput
                  v-model="reconfirmPassword"
                  type="password"
                  autocomplete="current-password"
                  class="w-full sm:max-w-xs"
                  data-testid="admin-office-reconfirm"
                />
              </UFormField>
              <div class="flex flex-wrap gap-2">
                <UButton
                  label="Regenerar acesso"
                  icon="i-lucide-refresh-cw"
                  :loading="acting"
                  data-testid="admin-office-regenerate"
                  @click="() => { void regenerate() }"
                />
                <UButton
                  label="Corrigir 1º administrador"
                  color="neutral"
                  variant="outline"
                  icon="i-lucide-user-pen"
                  data-testid="admin-office-correct-open"
                  @click="() => { correctOpen = true }"
                />
              </div>
              <p class="text-xs text-muted">
                Segredos já exibidos não são recuperáveis.
              </p>
            </div>
          </UPageCard>
        </template>

        <UModal
          v-model:open="correctOpen"
          title="Corrigir primeiro administrador"
          description="Revoga acessos anteriores e cria nova credencial."
        >
          <template #body>
            <div class="space-y-4">
              <UFormField
                label="Nome"
                required
              >
                <UInput
                  v-model="correctName"
                  class="w-full"
                  data-testid="admin-office-correct-name"
                />
              </UFormField>
              <UFormField
                label="E-mail"
                required
              >
                <UInput
                  v-model="correctEmail"
                  type="email"
                  class="w-full"
                  data-testid="admin-office-correct-email"
                />
              </UFormField>
              <div class="flex justify-end gap-2">
                <UButton
                  label="Cancelar"
                  color="neutral"
                  variant="subtle"
                  @click="() => { correctOpen = false }"
                />
                <UButton
                  label="Corrigir"
                  color="primary"
                  :loading="acting"
                  data-testid="admin-office-correct-submit"
                  @click="() => { void correctFirstAdmin() }"
                />
              </div>
            </div>
          </template>
        </UModal>
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
