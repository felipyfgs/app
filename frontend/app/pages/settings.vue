<script setup lang="ts">
/**
 * Settings onboarding Integra Contador (15.3).
 * Arquétipo: `.reference/nuxt-dashboard-template/app/pages/settings.vue`
 * CT-e não entra aqui — destino canônico é `/docs/catalog?kind=CTE`.
 */
import type { NavigationMenuItem } from '@nuxt/ui'

const { canAccessAdministration } = useDashboard()

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
  <UDashboardPanel
    id="settings"
    data-testid="settings-panel"
    :ui="{ body: 'lg:py-8' }"
  >
    <template #header>
      <UDashboardNavbar title="Configurações">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <UNavigationMenu
          :items="links"
          highlight
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="flex w-full min-w-0 flex-col gap-4 sm:gap-6 lg:gap-8">
        <UAlert
          v-if="!canAccessAdministration"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito"
          description="Somente administradores com segundo fator confirmado podem alterar o onboarding Integra."
        />
        <NuxtPage v-if="canAccessAdministration" />
      </div>
    </template>
  </UDashboardPanel>
</template>
