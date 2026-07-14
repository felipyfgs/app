<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Client, PageMeta } from '~/types/api'

const api = useApi()
const route = useRoute()
const router = useRouter()
const { canManageClients } = useDashboard()
const clients = ref<Client[]>([])
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')
const page = ref(Number(route.query.page) || 1)
const meta = ref<PageMeta>({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const loading = ref(false)
const creating = ref(false)
const createOpen = ref(canManageClients.value && route.query.new === '1')
const form = reactive({ name: '', cnpj: '', notes: '' })
const fieldErrors = ref<Record<string, string[]>>({})
const toast = useToast()

const columns: TableColumn<Client>[] = [
  { accessorKey: 'name', header: 'Cliente' },
  { accessorKey: 'root_cnpj', header: 'Raiz CNPJ' },
  {
    accessorKey: 'establishments_count',
    header: 'Estabelecimentos',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'is_active',
    header: 'Estado',
    meta: { class: { th: 'hidden sm:table-cell', td: 'hidden sm:table-cell' } }
  },
  { id: 'actions', header: '' }
]

async function load() {
  loading.value = true
  try {
    const response = await api.clients.list({
      q: q.value || undefined,
      page: page.value,
      per_page: meta.value.per_page
    })
    clients.value = response.data
    meta.value = response.meta
    await router.replace({ query: { ...(q.value ? { q: q.value } : {}), ...(page.value > 1 ? { page: page.value } : {}) } })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Erro ao listar clientes.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await load()
}

async function createClient() {
  if (!canManageClients.value) {
    return
  }

  fieldErrors.value = {}
  creating.value = true
  try {
    const response = await api.clients.create({ ...form })
    createOpen.value = false
    form.name = ''
    form.cnpj = ''
    form.notes = ''
    toast.add({ title: 'Cliente criado. Continue o cadastro guiado.', color: 'success' })
    await navigateTo(`/clients/${response.data.id}`)
  } catch (caught) {
    fieldErrors.value = apiFieldErrors(caught)
    toast.add({ title: apiErrorMessage(caught, 'Falha ao criar cliente.'), color: 'error' })
  } finally {
    creating.value = false
  }
}

watch(page, load)
onMounted(load)
</script>

<template>
  <UDashboardPanel id="clients">
    <template #header>
      <UDashboardNavbar title="Clientes">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            v-if="canManageClients"
            icon="i-lucide-plus"
            label="Novo cliente"
            @click="createOpen = true"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <template #left>
          <form class="flex w-full gap-2 sm:w-auto" @submit.prevent="search">
            <UInput
              v-model="q"
              icon="i-lucide-search"
              placeholder="Nome ou raiz do CNPJ"
              class="w-full sm:w-80"
            />
            <UButton
              type="submit"
              color="neutral"
              variant="subtle"
              label="Buscar"
            />
          </form>
        </template>
        <template #right>
          <span class="text-xs text-muted">{{ meta.total }} cliente(s)</span>
        </template>
      </UDashboardToolbar>
    </template>

    <template #body>
      <UTable
        :data="clients"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
      >
        <template #name-cell="{ row }">
          <div>
            <NuxtLink :to="`/clients/${row.original.id}`" class="font-medium text-primary hover:underline">
              {{ row.original.name }}
            </NuxtLink>
            <p class="text-xs text-muted md:hidden">
              {{ row.original.establishments_count || 0 }} estabelecimento(s)
            </p>
          </div>
        </template>
        <template #is_active-cell="{ row }">
          <UBadge :color="row.original.is_active ? 'success' : 'neutral'" variant="subtle">
            {{ row.original.is_active ? 'Ativo' : 'Inativo' }}
          </UBadge>
        </template>
        <template #actions-cell="{ row }">
          <UButton
            :to="`/clients/${row.original.id}`"
            color="neutral"
            variant="ghost"
            icon="i-lucide-chevron-right"
            square
            :aria-label="`Abrir ${row.original.name}`"
          />
        </template>
      </UTable>

      <UEmpty
        v-if="!loading && !clients.length"
        icon="i-lucide-building-2"
        title="Nenhum cliente encontrado"
        :description="q ? 'Revise o termo de busca.' : 'Cadastre a primeira raiz de cliente para começar.'"
      >
        <UButton v-if="canManageClients && !q" label="Cadastrar cliente" @click="createOpen = true" />
      </UEmpty>

      <div v-if="meta.last_page > 1" class="flex justify-center border-t border-default pt-4">
        <UPagination v-model:page="page" :total="meta.total" :items-per-page="meta.per_page" />
      </div>

      <UModal
        v-if="canManageClients"
        v-model:open="createOpen"
        title="Novo cliente"
        description="Cadastre a raiz do CNPJ; os estabelecimentos serão adicionados na próxima etapa."
      >
        <template #body>
          <form class="space-y-4" @submit.prevent="createClient">
            <UFormField label="Nome" required :error="fieldErrors.name?.[0]">
              <UInput
                v-model="form.name"
                required
                class="w-full"
                autofocus
              />
            </UFormField>
            <UFormField
              label="CNPJ"
              required
              help="Aceita CNPJ numérico ou alfanumérico, com ou sem máscara."
              :error="fieldErrors.cnpj?.[0]"
            >
              <UInput
                v-model="form.cnpj"
                required
                class="w-full"
                autocomplete="off"
              />
            </UFormField>
            <UFormField label="Observações" :error="fieldErrors.notes?.[0]">
              <UTextarea v-model="form.notes" class="w-full" :rows="3" />
            </UFormField>
            <div class="flex justify-end gap-2">
              <UButton
                color="neutral"
                variant="ghost"
                type="button"
                @click="createOpen = false"
              >
                Cancelar
              </UButton>
              <UButton type="submit" :loading="creating">
                Salvar e continuar
              </UButton>
            </div>
          </form>
        </template>
      </UModal>
    </template>
  </UDashboardPanel>
</template>
