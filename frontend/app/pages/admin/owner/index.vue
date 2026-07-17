<script setup lang="ts">
/**
 * Proprietário da instalação — superfície singular (sem tabela / sem criar admin).
 */
import type { PlatformOwner, PlatformOfficeSummary } from '~/types/api'
import { apiErrorMessage } from '~/utils/api-error'

const api = useApi()
const toast = useToast()
const { sessionEpoch, canAccessPlatformAdmin } = useDashboard()
const {
  offices,
  loadOffices
} = usePlatformOfficeSelect()

const loading = ref(false)
const saving = ref(false)
const loadError = ref<string | null>(null)
const owner = ref<PlatformOwner | null>(null)

const form = reactive({
  name: '',
  email: '',
  default_office_id: null as number | null
})
const reconfirmPassword = ref('')

const officeItems = computed(() => {
  const list: Array<{ label: string, value: number | null }> = [
    { label: 'Nenhum', value: null }
  ]
  for (const o of offices.value as PlatformOfficeSummary[]) {
    list.push({ label: `${o.name} (#${o.id})`, value: o.id })
  }
  if (
    owner.value?.default_office
    && !list.some(i => i.value === owner.value!.default_office!.id)
  ) {
    const d = owner.value.default_office
    list.push({ label: `${d.name} (#${d.id})`, value: d.id })
  }
  return list
})

let loadSeq = 0

function applyOwner(data: PlatformOwner) {
  owner.value = data
  form.name = data.name || ''
  form.email = data.email || ''
  form.default_office_id = data.default_office_id ?? null
}

async function load() {
  if (!canAccessPlatformAdmin.value) return
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const [ownerRes] = await Promise.all([
      api.platform.owner.show(),
      loadOffices().catch(() => undefined)
    ])
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    applyOwner(ownerRes.data)
  } catch (e) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    owner.value = null
    loadError.value = apiErrorMessage(e, 'Falha ao carregar o Proprietário.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function save() {
  if (!form.name.trim() || !form.email.trim()) {
    toast.add({ title: 'Informe nome e e-mail.', color: 'warning' })
    return
  }
  if (!reconfirmPassword.value) {
    toast.add({ title: 'Confirme sua senha.', color: 'warning' })
    return
  }
  saving.value = true
  try {
    await api.confirmPassword(reconfirmPassword.value)
    const res = await api.platform.owner.update({
      name: form.name.trim(),
      email: form.email.trim(),
      default_office_id: form.default_office_id
    })
    applyOwner(res.data)
    reconfirmPassword.value = ''
    toast.add({ title: 'Proprietário atualizado.', color: 'success' })
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao atualizar.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

watch(sessionEpoch, () => {
  owner.value = null
  void load()
})
onMounted(load)
</script>

<template>
  <UDashboardPanel
    id="admin-owner"
    data-testid="admin-owner-panel"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        title="Proprietário"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            color="neutral"
            variant="soft"
            icon="i-lucide-refresh-cw"
            label="Atualizar"
            :loading="loading"
            data-testid="admin-owner-refresh"
            @click="() => { void load() }"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <DashboardContent width="comfortable" class="gap-4">
        <UAlert
          v-if="!canAccessPlatformAdmin"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito"
          description="Somente o Proprietário da instalação acessa esta página."
          data-testid="admin-owner-denied"
        />

        <template v-else>
          <UAlert
            v-if="loadError"
            color="error"
            icon="i-lucide-circle-x"
            :title="loadError"
            :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
            data-testid="admin-owner-error"
          />

          <div
            v-else-if="loading && !owner"
            class="space-y-3"
          >
            <USkeleton class="h-10 w-full" />
            <USkeleton class="h-10 w-full" />
            <USkeleton class="h-10 w-2/3" />
          </div>

          <UPageCard
            v-else-if="owner"
            title="Identidade do Proprietário"
            description="Há um único proprietário global por instalação. Recuperação de acesso é operação de host."
            variant="subtle"
            data-testid="admin-owner-card"
          >
            <div class="space-y-4">
              <div class="flex flex-wrap items-center gap-2">
                <UBadge
                  size="sm"
                  variant="subtle"
                  :color="owner.is_active ? 'success' : 'warning'"
                  :label="owner.is_active ? 'Ativo' : 'Inativo'"
                  data-testid="admin-owner-status"
                />
                <span class="text-sm text-muted">
                  ID {{ owner.user_id }}
                </span>
              </div>

              <UFormField
                label="Nome"
                required
              >
                <UInput
                  v-model="form.name"
                  class="w-full"
                  autocomplete="name"
                  data-testid="admin-owner-name"
                />
              </UFormField>

              <UFormField
                label="E-mail"
                required
              >
                <UInput
                  v-model="form.email"
                  type="email"
                  class="w-full"
                  autocomplete="email"
                  data-testid="admin-owner-email"
                />
              </UFormField>

              <UFormField label="Office padrão">
                <USelect
                  v-model="form.default_office_id"
                  :items="officeItems"
                  value-key="value"
                  label-key="label"
                  class="w-full"
                  data-testid="admin-owner-default-office"
                />
              </UFormField>

              <UFormField
                label="Sua senha"
                required
                hint="Reconfirmação exigida para alterações sensíveis."
              >
                <UInput
                  v-model="reconfirmPassword"
                  type="password"
                  autocomplete="current-password"
                  class="w-full"
                  data-testid="admin-owner-reconfirm"
                />
              </UFormField>

              <div class="flex justify-end">
                <UButton
                  label="Salvar"
                  color="primary"
                  :loading="saving"
                  data-testid="admin-owner-save"
                  @click="() => { void save() }"
                />
              </div>
            </div>
          </UPageCard>
        </template>
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
