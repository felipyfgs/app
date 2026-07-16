<script setup lang="ts">
/**
 * Settings onboarding Integra Contador (15.3).
 * Arquétipo: `.reference/nuxt-dashboard-template/app/pages/settings.vue`
 *   Navbar + Toolbar com UNavigationMenu highlight + body max-w-2xl + NuxtPage
 * CT-e não entra aqui — destino canônico é `/docs/catalog?kind=CTE`.
 */
import type { NavigationMenuItem } from '@nuxt/ui'

const { canAccessAdministration } = useDashboard()

/**
 * Mesmo contrato do template: NavigationMenuItem[][] (grupo principal + secundário).
 * Labels/rotas adaptados ao produto; sem Documentation externa do demo.
 */
const links = [[{
  label: 'Integra Contador',
  icon: 'i-lucide-key-round',
  to: '/settings',
  exact: true
}, {
  label: 'Procurações',
  icon: 'i-lucide-file-key',
  to: '/settings/proxies'
}, {
  label: 'Consumo',
  icon: 'i-lucide-chart-pie',
  to: '/settings/usage'
}, {
  label: 'Assinatura',
  icon: 'i-lucide-badge-check',
  to: '/settings/subscription'
}], [{
  label: 'Administração',
  icon: 'i-lucide-shield',
  to: '/admin'
}]] satisfies NavigationMenuItem[][]
</script>

<template>
  <UDashboardPanel id="settings" data-testid="settings-panel" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar title="Configurações" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="settings-section-tabs">
        <!-- NOTE: The `-mx-1` class is used to align with the `DashboardSidebarCollapse` button here. -->
        <UNavigationMenu :items="links" highlight class="-mx-1 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="flex flex-col gap-4 sm:gap-6 lg:gap-12 w-full lg:max-w-2xl mx-auto">
        <UAlert
          v-if="!canAccessAdministration"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito"
        />
        <NuxtPage v-if="canAccessAdministration" />
      </div>
    </template>
  </UDashboardPanel>
</template>
