<script setup lang="ts">
/**
 * Administradores globais PLATFORM_ADMIN (lista + criação mínima).
 */
import type { TableColumn } from '@nuxt/ui'
import type {
  ActivationMethod,
  CreatePlatformAdminResult,
  PlatformAdminUser
} from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import { apiErrorMessage } from '~/utils/api-error'

const api = useApi()
const toast = useToast()
const { sessionEpoch, canAccessPlatformAdmin } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rows = ref<PlatformAdminUser[]>([])
const q = ref('')
const createOpen = ref(false)
const creating = ref(false)
const reconfirmPassword = ref('')
const secret = ref<CreatePlatformAdminResult | null>(null)
const actingId = ref<number | null>(null)
const regenerateMethod = ref<ActivationMethod>('MANUAL_LINK')

const form = reactive({
  name: '',
  email: '',
  method: 'MANUAL_LINK' as ActivationMethod
})

const methodItems = [
  { label: 'Link manual', value: 'MANUAL_LINK' as ActivationMethod },
  { label: 'Senha provisória', value: 'TEMPORARY_PASSWORD' as ActivationMethod }
]

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return rows.value
  return rows.value.filter(a =>
    (a.name || '').toLowerCase().includes(term)
    || (a.email || '').toLowerCase().includes(term)
    || String(a.user_id).includes(term)
  )
})

const columns: TableColumn<PlatformAdminUser>[] = [
  { accessorKey: 'user_id', header: 'ID', meta: { class: { th: 'w-16', td: 'w-16' } } },
  { accessorKey: 'name', header: 'Nome' },
  { accessorKey: 'email', header: 'E-mail' },
  {
    id: 'status',
    header: 'Status',
    cell: ({ row }) => row.original.is_active ? 'Ativo' : 'Pendente'
  },
  {
    id: 'activation',
    header: 'Ativação',
    cell: ({ row }) => {
      const a = row.original.activation
      if (!a) return row.original.is_active ? '—' : 'sem ativação'
      return `${a.method} · ${a.status}`
    }
  }
]

let loadSeq = 0

async function load() {
  if (!canAccessPlatformAdmin.value) return
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.admins.list()
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = res.data || []
  } catch (e) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = []
    loadError.value = apiErrorMessage(e, 'Falha ao listar administradores.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

function resetCreate() {
  form.name = ''
  form.email = ''
  form.method = 'MANUAL_LINK'
  reconfirmPassword.value = ''
}

async function createAdmin() {
  if (!form.name.trim() || !form.email.trim()) {
    toast.add({ title: 'Informe nome e e-mail.', color: 'warning' })
    return
  }
  if (!reconfirmPassword.value) {
    toast.add({ title: 'Confirme sua senha.', color: 'warning' })
    return
  }
  creating.value = true
  try {
    await api.confirmPassword(reconfirmPassword.value)
    const res = await api.platform.admins.create({
      name: form.name.trim(),
      email: form.email.trim(),
      method: form.method
    })
    secret.value = res.data
    createOpen.value = false
    resetCreate()
    toast.add({ title: 'Administrador criado.', color: 'success' })
    await load()
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao criar.'), color: 'error' })
  } finally {
    creating.value = false
  }
}

async function regenerate(admin: PlatformAdminUser) {
  if (!reconfirmPassword.value) {
    toast.add({ title: 'Informe sua senha no formulário de regeneração.', color: 'warning' })
    return
  }
  actingId.value = admin.user_id
  try {
    await api.confirmPassword(reconfirmPassword.value)
    const res = await api.platform.admins.regenerateActivation(admin.user_id, {
      method: regenerateMethod.value
    })
    secret.value = {
      admin,
      ...res.data
    }
    reconfirmPassword.value = ''
    toast.add({ title: 'Acesso regenerado.', color: 'success' })
    await load()
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao regenerar.'), color: 'error' })
  } finally {
    actingId.value = null
  }
}

function clearSecret() {
  secret.value = null
}

watch(sessionEpoch, () => {
  rows.value = []
  clearSecret()
  void load()
})
watch(createOpen, (open) => {
  if (!open) resetCreate()
})
onMounted(load)
onBeforeUnmount(clearSecret)
</script>

<template>
  <UDashboardPanel
    id="admin-admins"
    data-testid="admin-admins-panel"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        title="Administradores"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            icon="i-lucide-plus"
            label="Novo administrador"
            data-testid="admin-admins-new"
            @click="() => { createOpen = true }"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <DashboardContent width="wide" class="gap-4">
        <ActivationOneTimeSecret
          v-if="secret && (secret.activation_url || secret.temporary_password)"
          :activation-url="secret.activation_url"
          :temporary-password="secret.temporary_password"
          :expires-at="secret.expires_at"
          :method="secret.method"
        />

        <div class="flex flex-wrap items-center gap-2">
          <UInput
            v-model="q"
            icon="i-lucide-search"
            placeholder="Buscar por nome ou e-mail…"
            class="w-full sm:max-w-sm"
            data-testid="admin-admins-search"
          />
          <UButton
            color="neutral"
            variant="soft"
            icon="i-lucide-refresh-cw"
            label="Atualizar"
            :loading="loading"
            @click="() => { void load() }"
          />
        </div>

        <UAlert
          v-if="loadError"
          color="error"
          icon="i-lucide-circle-x"
          :title="loadError"
          :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
        />

        <div
          v-else-if="loading && !rows.length"
          class="space-y-2"
        >
          <USkeleton class="h-10 w-full" />
          <USkeleton class="h-10 w-full" />
        </div>

        <UEmpty
          v-else-if="!filtered.length"
          icon="i-lucide-shield-user"
          title="Nenhum administrador"
          data-testid="admin-admins-empty"
        />

        <div
          v-else
          class="overflow-x-auto"
        >
          <UTable
            :data="filtered"
            :columns="columns"
            :ui="DASHBOARD_TABLE_UI"
            class="min-w-full"
            data-testid="admin-admins-table"
          >
            <template #status-cell="{ row }">
              <UBadge
                size="sm"
                variant="subtle"
                :color="row.original.is_active ? 'success' : 'warning'"
                :label="row.original.is_active ? 'Ativo' : 'Pendente'"
              />
            </template>
          </UTable>
        </div>

        <UPageCard
          v-if="rows.some(r => !r.is_active)"
          title="Regenerar pendente"
          variant="subtle"
          class="mt-2"
        >
          <div class="flex flex-wrap items-end gap-3">
            <UFormField label="Método">
              <USelect
                v-model="regenerateMethod"
                :items="methodItems"
                value-key="value"
                label-key="label"
                class="w-44"
              />
            </UFormField>
            <UFormField label="Sua senha">
              <UInput
                v-model="reconfirmPassword"
                type="password"
                autocomplete="current-password"
                class="w-48"
                data-testid="admin-admins-reconfirm"
              />
            </UFormField>
            <div class="flex flex-wrap gap-2">
              <UButton
                v-for="admin in rows.filter(r => !r.is_active)"
                :key="admin.user_id"
                size="sm"
                color="neutral"
                variant="outline"
                :label="`Regenerar ${admin.email || admin.user_id}`"
                :loading="actingId === admin.user_id"
                :data-testid="`admin-admins-regenerate-${admin.user_id}`"
                @click="() => { void regenerate(admin) }"
              />
            </div>
          </div>
        </UPageCard>

        <UModal
          v-model:open="createOpen"
          title="Novo administrador global"
          description="Sem membership de escritório. Ativação pendente."
        >
          <template #body>
            <div class="space-y-4">
              <UFormField
                label="Nome"
                required
              >
                <UInput
                  v-model="form.name"
                  class="w-full"
                  data-testid="admin-create-name"
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
                  data-testid="admin-create-email"
                />
              </UFormField>
              <UFormField
                label="Entrega"
                required
              >
                <USelect
                  v-model="form.method"
                  :items="methodItems"
                  value-key="value"
                  label-key="label"
                  class="w-full"
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
                  class="w-full"
                  data-testid="admin-create-reconfirm"
                />
              </UFormField>
              <div class="flex justify-end gap-2">
                <UButton
                  label="Cancelar"
                  color="neutral"
                  variant="subtle"
                  @click="() => { createOpen = false }"
                />
                <UButton
                  label="Criar"
                  color="primary"
                  :loading="creating"
                  data-testid="admin-create-submit"
                  @click="() => { void createAdmin() }"
                />
              </div>
            </div>
          </template>
        </UModal>
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
