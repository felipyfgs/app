<script setup lang="ts">
/**
 * Shell unificado de perfil pessoal e configurações do escritório.
 * Arquétipo: `.reference/nuxt-dashboard-template/app/pages/settings.vue`.
 */
import SectionNavigation from '~/components/navigation/SectionNavigation.vue'
import { accountNavigationTree } from '~/utils/account-navigation'

const route = useRoute()
const { me } = useDashboard()
const links = computed(() => accountNavigationTree(me.value))
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
        <SectionNavigation
          :items="links"
          :path="route.fullPath"
          aria-label="Navegação da conta"
          subtabs-aria-label="Seções da conta"
          test-id="account-section-navigation"
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
