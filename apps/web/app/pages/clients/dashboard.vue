<script setup lang="ts">
/**
 * Subpágina Dashboard de clientes (submenu Settings-style).
 * Conteúdo: arquétipo Home (stats + chart Unovis + tabela).
 */
import type { Client, ClientListStats } from '~/types/api'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const clients = ref<Client[]>([])
function emptyStats(): ClientListStats {
  return {
    total: 0,
    active: 0,
    with_credential: 0,
    credential_ok: 0,
    without_credential: 0,
    credential_expiring_30d: 0,
    credential_expired: 0,
    capture_problem: 0,
    client_growth_12m: []
  }
}

const stats = ref<ClientListStats>(emptyStats())
const loading = ref(false)
let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  try {
    const first = await api.clients.list({
      page: 1,
      per_page: 8,
      sort: 'created_at',
      direction: 'desc',
      dashboard: true
    })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    clients.value = first.data
    stats.value = first.meta.stats || emptyStats()
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    toast.add({
      title: apiErrorMessage(caught, 'Erro ao carregar dashboard de clientes.'),
      color: 'error'
    })
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) loading.value = false
  }
}

onMounted(load)

watch(sessionEpoch, () => {
  loadSeq += 1
  clients.value = []
  stats.value = emptyStats()
  loading.value = false
  void load()
})
</script>

<template>
  <div class="flex w-full flex-col gap-4 sm:gap-5">
    <div class="flex flex-wrap items-center justify-end gap-1.5">
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        square
        aria-label="Atualizar dashboard"
        :loading="loading"
        @click="load"
      />
    </div>

    <ClientsClientListDashboard
      :clients="clients"
      :stats="stats"
      :loading="loading"
    />
  </div>
</template>
