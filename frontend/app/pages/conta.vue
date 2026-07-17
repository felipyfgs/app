<script setup lang="ts">
/**
 * Shell unificado de perfil pessoal e configurações do escritório.
 * Arquétipo: `.reference/nuxt-dashboard-template/app/pages/settings.vue`.
 */
import { accountNavigationItems } from '~/utils/account-navigation'

const { me } = useDashboard()
const links = computed(() => [accountNavigationItems(me.value)])
</script>

<template>
  <UDashboardPanel
    id="account"
    data-testid="account-panel"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar title="Conta" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="account-section-tabs">
        <UNavigationMenu
          :items="links"
          highlight
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <DashboardContent width="comfortable" class="gap-4 sm:gap-6 lg:gap-12">
        <NuxtPage />
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
