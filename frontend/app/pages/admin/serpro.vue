<script setup lang="ts">
/**
 * Shell do console global SERPRO (PLATFORM_ADMIN).
 * Arquétipo: settings.vue do template (navbar + toolbar NavigationMenu + NuxtPage).
 * Fonte: .reference/nuxt-dashboard-template/app/pages/settings.vue
 */
import type { NavigationMenuItem } from '@nuxt/ui'

const { canAccessPlatformSerpro } = useDashboard()

const links = [[{
  label: 'Readiness',
  icon: 'i-lucide-heart-pulse',
  to: '/admin/serpro',
  exact: true
}, {
  label: 'Contratos',
  icon: 'i-lucide-file-badge',
  to: '/admin/serpro/contracts'
}, {
  label: 'Cobertura',
  icon: 'i-lucide-layout-grid',
  to: '/admin/serpro/catalog'
}, {
  label: 'Orçamento',
  icon: 'i-lucide-wallet',
  to: '/admin/serpro/usage'
}, {
  label: 'Rollout',
  icon: 'i-lucide-rocket',
  to: '/admin/serpro/rollout'
}], [{
  label: 'Hub plataforma',
  icon: 'i-lucide-layout-dashboard',
  to: '/admin'
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
        title="Console SERPRO"
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
      <div class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-4xl lg:gap-12">
        <UAlert
          v-if="!canAccessPlatformSerpro"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito à plataforma"
          description="Requer PLATFORM_ADMIN. Navegação sem TOTP global; mutações sensíveis pedem reconfirmação de senha."
          data-testid="admin-serpro-denied"
        />
        <NuxtPage v-else />
      </div>
    </template>
  </UDashboardPanel>
</template>
