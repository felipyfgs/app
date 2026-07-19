<script setup lang="ts">
/**
 * Shell de catálogo de clientes — arquétipo Settings do template.
 * Fonte: .local/reference/nuxt-dashboard-template/app/pages/settings.vue
 *   UDashboardNavbar + UDashboardToolbar + UNavigationMenu (to/exact) + NuxtPage
 *
 * Detalhe /clients/:id tem painel próprio e não usa este chrome.
 */
import SectionNavigation from '~/components/navigation/SectionNavigation.vue'
import type { NavLayerItem } from '~/utils/navigation-hierarchy'

const route = useRoute()
const router = useRouter()
const { canManageClients, openClientCreate } = useDashboard()

/** Lista e Dashboard compartilham o shell; detalhe do cliente não. */
const isCatalog = computed(() => {
  const path = route.path.replace(/\/$/, '') || '/'
  return path === '/clients' || path === '/clients/dashboard'
})

const links: NavLayerItem[] = [
  {
    id: 'clients-list',
    label: 'Lista',
    icon: 'i-lucide-list',
    to: '/clients',
    exact: true
  },
  {
    id: 'clients-dashboard',
    label: 'Dashboard',
    icon: 'i-lucide-layout-dashboard',
    to: '/clients/dashboard'
  }
]

/**
 * Compatibilidade de entrada: remove queries antigas do catálogo e mantém a
 * URL canônica. `?new=1` ainda abre o modal uma vez antes de ser descartado.
 */
watch(
  () => route.fullPath,
  async () => {
    if (!isCatalog.value || !Object.keys(route.query).length) return
    const shouldOpenCreate = route.path === '/clients' && route.query.new === '1'
    await router.replace({ path: route.path })
    if (shouldOpenCreate) await openClientCreate()
  },
  { immediate: true }
)
</script>

<template>
  <!--
    Hierarquia: navbar + tabs Lista/Dashboard (settings.vue / customers).
    Casca: UDashboardPanel (inline template).
  -->
  <UDashboardPanel v-if="isCatalog" id="clients">
    <template #header>
      <UDashboardNavbar title="Clientes" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            v-if="canManageClients"
            icon="i-lucide-plus"
            label="Novo cliente"
            @click="openClientCreate"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="clients-section-tabs">
        <SectionNavigation
          :items="links"
          :path="route.fullPath"
          aria-label="Navegação de clientes"
          test-id="clients-section-navigation"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <NuxtPage />
    </template>
  </UDashboardPanel>

  <!-- /clients/:id… — painel próprio em [id].vue -->
  <NuxtPage v-else />
</template>
