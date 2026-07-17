<script setup lang="ts">
/**
 * Shell do console global SERPRO (PLATFORM_ADMIN).
 * Arquétipo: settings.vue do template (navbar + toolbar NavigationMenu + NuxtPage).
 * Fonte: .reference/nuxt-dashboard-template/app/pages/settings.vue
 * Largura: comfortable (max-w-5xl, centralizado) — mesma linha de /conta e do shell settings oficial.
 */
import type { NavigationMenuItem } from '@nuxt/ui'

const { canAccessPlatformSerpro } = useDashboard()

const links = [[{
  label: 'Operação',
  icon: 'i-lucide-gauge',
  to: '/admin/serpro',
  exact: true
}, {
  label: 'Integração',
  icon: 'i-lucide-settings-2',
  to: '/admin/serpro/configuration'
}, {
  label: 'Canário DTE',
  icon: 'i-lucide-flask-conical',
  to: '/admin/serpro/dte-canary'
}]] satisfies NavigationMenuItem[][]
</script>

<template>
  <UDashboardPanel
    id="admin-serpro"
    data-testid="admin-serpro-panel"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        title="Integração SERPRO"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="admin-serpro-tabs">
        <UNavigationMenu
          :items="links"
          highlight
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <DashboardContent width="comfortable" class="gap-4 sm:gap-6 lg:gap-12">
        <UAlert
          v-if="!canAccessPlatformSerpro"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito à plataforma"
          data-testid="admin-serpro-denied"
        />
        <NuxtPage v-else />
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
