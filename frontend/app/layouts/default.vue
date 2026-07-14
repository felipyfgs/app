<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import {
  flattenDestinations,
  mainDestinations,
  quickActions,
  secondaryDestinations,
  toNavigationItems
} from '~/utils/navigation'

/**
 * Shell autenticado — estrutura copiada de
 * `.reference/nuxt-dashboard-template/app/layouts/default.vue`
 * (UDashboardGroup + Sidebar header/search/nav/footer + Search + slot).
 */
const route = useRoute()
const open = ref(false)
const { me } = useDashboard()

const closeSidebar = () => {
  open.value = false
}

/** Dois menus verticais: primário + secundário (mt-auto), como no template. */
const links = computed(() => {
  const primary = toNavigationItems(
    mainDestinations(me.value, { path: route.path }),
    closeSidebar
  )
  const secondary = toNavigationItems(
    secondaryDestinations(),
    closeSidebar
  )
  return [primary, secondary] as NavigationMenuItem[][]
})

const groups = computed(() => {
  const destinations = [
    ...flattenDestinations(mainDestinations(me.value, { path: route.path })),
    ...secondaryDestinations()
  ]

  return [{
    id: 'links',
    label: 'Ir para',
    items: destinations
      .filter(item => item.to)
      .map(item => ({
        id: item.id,
        label: item.label,
        icon: item.icon,
        to: item.to,
        target: item.target
      }))
  }, {
    id: 'actions',
    label: 'Ações',
    items: quickActions(me.value).map(action => ({
      id: action.id,
      label: action.label,
      icon: action.icon,
      to: action.to
    }))
  }]
})
</script>

<template>
  <UDashboardGroup unit="rem">
    <UDashboardSidebar
      id="default"
      v-model:open="open"
      data-testid="shell-sidebar"
      collapsible
      resizable
      class="bg-elevated/25"
      :ui="{ footer: 'lg:border-t lg:border-default' }"
    >
      <template #header="{ collapsed }">
        <OfficeIdentity :collapsed="collapsed" />
      </template>

      <template #default="{ collapsed }">
        <UDashboardSearchButton
          :collapsed="collapsed"
          class="bg-transparent ring-default"
        />

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
