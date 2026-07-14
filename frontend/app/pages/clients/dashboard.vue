<script setup lang="ts">
/**
 * Subpágina Dashboard de clientes (submenu Settings-style).
 * Conteúdo: arquétipo Home (stats + chart Unovis + tabela).
 */
import type { Client, ClientListStats } from '~/types/api'

const api = useApi()
const toast = useToast()

const clients = ref<Client[]>([])
const stats = ref<ClientListStats>({
  total: 0,
  active: 0,
  without_credential: 0,
  credential_expiring_30d: 0,
  credential_expired: 0
})
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const perPage = 100
    const first = await api.clients.list({ page: 1, per_page: perPage })
    let all = [...first.data]
    const lastPage = first.meta.last_page || 1
    for (let p = 2; p <= lastPage; p++) {
      const pageRes = await api.clients.list({ page: p, per_page: perPage })
      all = all.concat(pageRes.data)
    }
    clients.value = all
    stats.value = first.meta.stats || {
      total: first.meta.total ?? all.length,
      active: all.filter(c => c.is_active).length,
      without_credential: all.filter(c => !c.credential_summary).length,
      credential_expiring_30d: 0,
      credential_expired: 0
    }
    if (!first.meta.stats) {
      stats.value.total = all.length
      stats.value.active = all.filter(c => c.is_active).length
      stats.value.without_credential = all.filter(c => !c.credential_summary).length
    }
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Erro ao carregar dashboard de clientes.'),
      color: 'error'
    })
  } finally {
    loading.value = false
  }
}

onMounted(load)
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
