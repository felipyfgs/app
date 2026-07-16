<script setup lang="ts">
/**
 * Shell de Configurações do escritório.
 * Arquétipo: `.reference/nuxt-dashboard-template/app/pages/settings.vue`
 * Navbar + Toolbar UNavigationMenu + body max-w-2xl + NuxtPage
 *
 * OpenSpec 6.1: superfície tenant = perfil, consentimento, A1, agendas.
 * Sem campos técnicos SERPRO (autor/Termo/token/OAuth).
 */
import type { NavigationMenuItem } from '@nuxt/ui'

const { canAccessAdministration } = useDashboard()

const links = [[{
  label: 'Escritório',
  icon: 'i-lucide-building-2',
  to: '/settings',
  exact: true
}, {
  label: 'Consumo',
  icon: 'i-lucide-chart-pie',
  to: '/settings/usage'
}, {
  label: 'Assinatura',
  icon: 'i-lucide-badge-check',
  to: '/settings/subscription'
}]] satisfies NavigationMenuItem[][]
</script>

<template>
  <UDashboardPanel
    id="settings"
    data-testid="settings-panel"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        title="Configurações"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="settings-section-tabs">
        <UNavigationMenu
          :items="links"
          highlight
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-2xl lg:gap-12">
        <UAlert
          v-if="!canAccessAdministration"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito"
          description="Somente administrador do escritório (ou contexto privilegiado da plataforma) pode alterar a configuração."
          data-testid="settings-access-denied"
        />
        <NuxtPage v-if="canAccessAdministration" />
      </div>
    </template>
  </UDashboardPanel>
</template>
