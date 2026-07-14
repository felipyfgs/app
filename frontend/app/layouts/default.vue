<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const open = ref(false)
const { canAccessAdministration, canCreateExport, canManageClients } = useDashboard()

const links = computed(() => {
  const main: NavigationMenuItem[] = [{
    label: 'Dashboard',
    icon: 'i-lucide-house',
    to: '/',
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Clientes',
    icon: 'i-lucide-building-2',
    to: '/clients',
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Notas fiscais',
    icon: 'i-lucide-file-text',
    to: '/notes',
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Exportações',
    icon: 'i-lucide-package',
    to: '/exports',
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Sincronizações',
    icon: 'i-lucide-refresh-cw',
    to: '/syncs',
    onSelect: () => {
      open.value = false
    }
  }]

  if (canAccessAdministration.value) {
    main.push({
      label: 'Administração',
      icon: 'i-lucide-shield',
      to: '/admin',
      onSelect: () => {
        open.value = false
      }
    })
  }

  const secondary: NavigationMenuItem[] = [{
    label: 'Documentação ADN',
    icon: 'i-lucide-book-open',
    to: 'https://www.gov.br/nfse',
    target: '_blank'
  }]

  return [main, secondary] satisfies NavigationMenuItem[][]
})

const groups = computed(() => {
  const actions = []
  if (canManageClients.value) {
    actions.push({
      id: 'new-client',
      label: 'Novo cliente',
      icon: 'i-lucide-user-plus',
      to: '/clients?new=1'
    })
  }
  if (canCreateExport.value) {
    actions.push({
      id: 'new-export',
      label: 'Nova exportação',
      icon: 'i-lucide-download',
      to: '/exports?new=1'
    })
  }

  return [{
    id: 'links',
    label: 'Ir para',
    items: links.value.flat().map(item => ({
      id: String(item.to || item.label),
      label: item.label || '',
      icon: item.icon,
      to: item.to,
      target: item.target
    }))
  }, {
    id: 'actions',
    label: 'Ações',
    items: actions
  }]
})
</script>

<template>
  <UDashboardGroup unit="rem">
    <UDashboardSidebar
      id="default"
      v-model:open="open"
      collapsible
      resizable
      class="bg-elevated/25"
      :ui="{ footer: 'lg:border-t lg:border-default' }"
    >
      <template #header="{ collapsed }">
        <TeamsMenu :collapsed="collapsed" />
      </template>

      <template #default="{ collapsed }">
        <UDashboardSearchButton :collapsed="collapsed" class="bg-transparent ring-default" />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="links[0]"
          orientation="vertical"
          tooltip
          popover
        />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="links[1]"
          orientation="vertical"
          tooltip
          class="mt-auto"
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <UDashboardSearch :groups="groups" />

    <slot />

    <NotificationsSlideover />
  </UDashboardGroup>
</template>
