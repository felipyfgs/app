<script setup lang="ts">
/**
 * Shell de catálogo de clientes — arquétipo Settings do template.
 * Fonte: .reference/nuxt-dashboard-template/app/pages/settings.vue
 *   UDashboardNavbar + UDashboardToolbar + UNavigationMenu (to/exact) + NuxtPage
 *
 * Detalhe /clients/:id tem painel próprio e não usa este chrome.
 */
import type { NavigationMenuItem } from '@nuxt/ui'

const route = useRoute()

/** Lista e Dashboard compartilham o shell; detalhe do cliente não. */
const isCatalog = computed(() => {
  const path = route.path.replace(/\/$/, '') || '/'
  return path === '/clients' || path === '/clients/dashboard'
})

const links = [[{
  label: 'Lista',
  icon: 'i-lucide-list',
  to: '/clients',
  exact: true
}, {
  label: 'Dashboard',
  icon: 'i-lucide-layout-dashboard',
  to: '/clients/dashboard'
}]] satisfies NavigationMenuItem[][]
</script>

<template>
  <UDashboardPanel
    v-if="isCatalog"
    id="clients"
  >
    <template #header>
      <!--
        Hierarquia: navbar com título (sem ação primária duplicada no shell);
        "Novo cliente" fica na toolbar do corpo da lista (customers.vue).
        Tabs Lista/Dashboard = toolbar secundária (settings.vue).
      -->
      <UDashboardNavbar
        data-testid="page-navbar"
        title="Clientes"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="clients-section-tabs">
        <!-- NOTE: The `-mx-1` class is used to align with the DashboardSidebarCollapse (template settings). -->
        <UNavigationMenu
          :items="links"
          highlight
          class="-mx-1 flex-1"
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
