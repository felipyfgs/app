<script setup lang="ts">
import type { WorkDepartment } from '~/types/work'
import { canManageWorkCatalog } from '~/utils/permissions'
import { apiErrorMessage } from '~/utils/api-error'

const api = useApi()
const toast = useToast()
const { me } = useDashboard()

if (!canManageWorkCatalog(me.value)) {
  await navigateTo('/')
}

const items = ref<WorkDepartment[]>([])
const loading = ref(false)
const name = ref('')
const code = ref('')
const creating = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api.work.departments.list({ per_page: 100 })
    items.value = res.data
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao listar departamentos.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

async function create() {
  creating.value = true
  try {
    await api.work.departments.create({ name: name.value.trim(), code: code.value.trim() })
    name.value = ''
    code.value = ''
    toast.add({ title: 'Departamento criado.', color: 'success' })
    await load()
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Não foi possível criar.'), color: 'error' })
  } finally {
    creating.value = false
  }
}

async function toggle(d: WorkDepartment) {
  try {
    await api.work.departments.update(d.id, { is_active: !d.is_active })
    await load()
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao atualizar.'), color: 'error' })
  }
}

onMounted(load)
</script>

<template>
  <UDashboardPanel id="admin-departments" data-testid="admin-departments-panel">
    <template #header>
      <UDashboardNavbar title="Departamentos">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <h1 data-testid="page-title" class="sr-only">
        Departamentos
      </h1>
      <div class="grid gap-6 p-4 lg:grid-cols-[20rem_1fr]">
        <UCard>
          <template #header>
            <h3 class="font-semibold">
              Novo departamento
            </h3>
          </template>
          <div class="space-y-3">
            <UFormField label="Nome">
              <UInput v-model="name" data-testid="department-name" aria-label="Nome" />
            </UFormField>
            <UFormField label="Sigla">
              <UInput v-model="code" data-testid="department-code" aria-label="Sigla" />
            </UFormField>
            <UButton data-testid="department-create" :loading="creating" block @click="create">
              Criar
            </UButton>
          </div>
        </UCard>

        <div>
          <div v-if="loading" class="space-y-2">
            <USkeleton v-for="i in 4" :key="i" class="h-10 w-full" />
          </div>
          <ul v-else class="divide-y divide-default rounded-lg border border-default">
            <li
              v-for="d in items"
              :key="d.id"
              class="flex items-center justify-between gap-3 p-3"
            >
              <div>
                <p class="font-medium">
                  {{ d.name }} <span class="text-muted">({{ d.code }})</span>
                </p>
                <p class="text-xs text-muted">
                  {{ d.is_active ? 'Ativo' : 'Inativo' }}
                </p>
              </div>
              <UButton size="sm" variant="soft" @click="toggle(d)">
                {{ d.is_active ? 'Desativar' : 'Reativar' }}
              </UButton>
            </li>
          </ul>
        </div>
      </div>
    </template>
  </UDashboardPanel>
</template>
