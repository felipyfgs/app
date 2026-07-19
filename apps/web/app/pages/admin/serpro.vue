<script setup lang="ts">
/**
 * Shell do console global SERPRO (PLATFORM_ADMIN).
 * Arquétipo: settings.vue do template; navegação global exclusiva no sidebar.
 * Fonte: .local/reference/nuxt-dashboard-template/app/pages/settings.vue
 * Largura: comfortable (max-w-5xl, centralizado) — mesma linha de /conta e do shell settings oficial.
 */
const { canAccessPlatformSerpro } = useDashboard()
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
