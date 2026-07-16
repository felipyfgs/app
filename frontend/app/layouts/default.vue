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
 * Shell autenticado — UDashboardSidebar + UNavigationMenu (padrão Nuxt UI).
 *
 * type="single": um grupo aberto por vez.
 * Fechar tudo → reabre a seção da rota ativa (tela atual).
 * Navegação de rota → abre o grupo da rota.
 *
 * @see https://ui.nuxt.com/docs/components/navigation-menu#orientation
 * @see https://ui.nuxt.com/docs/components/dashboard-sidebar
 */
const route = useRoute()
const open = ref(false)
const { me, openClientCreate, openExportCreate } = useDashboard()

const closeSidebar = () => {
  open.value = false
}

/** value do trigger aberto (ids em navigation.ts). */
const openSection = ref<string | undefined>()

/** Seção que corresponde à rota atual (ex.: /monitoring/* → 'monitoring'). */
function sectionForPath(path: string): string | undefined {
  return mainDestinations(me.value, { path })
    .find(d => d.type === 'trigger' && d.defaultOpen)
    ?.id
}

function syncOpenToRoute() {
  openSection.value = sectionForPath(route.path)
}

watch(() => route.path, syncOpenToRoute, { immediate: true })

/**
 * Clique no accordion:
 * - abriu outra seção → troca (single)
 * - fechou tudo (collapsible) → volta para a seção da tela ativa
 */
function onOpenSectionChange(value: string | undefined) {
  if (value === undefined || value === null || value === '') {
    const active = sectionForPath(route.path)
    openSection.value = active
    return
  }
  openSection.value = value
}

const links = computed(() => {
  const navOptions = { path: route.path }
  // Sem defaultOpen: v-model é a única fonte de verdade (evita estado misto).
  const primary = toNavigationItems(
    mainDestinations(me.value, navOptions),
    closeSidebar
  ).map((item) => {
    if (item.type !== 'trigger') {
      return item
    }
    const next = { ...item } as NavigationMenuItem & {
      defaultOpen?: boolean
      open?: boolean
    }
    delete next.defaultOpen
    delete next.open
    return next
  })

  const secondary = toNavigationItems(
    secondaryDestinations(),
    closeSidebar
  )
  return [primary, secondary] as NavigationMenuItem[][]
})

const groups = computed(() => {
  const destinations = [
    ...flattenDestinations(mainDestinations(me.value, {
      path: route.path
    })),
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
      to: action.to,
      onSelect: action.id === 'new-client'
        ? openClientCreate
        : action.id === 'new-export'
          ? openExportCreate
          : undefined
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
        <UDashboardSearchButton :collapsed="collapsed" class="bg-transparent ring-default" />

        <UNavigationMenu
          :model-value="openSection"
          type="single"
          collapsible
          :collapsed="collapsed"
          :items="links[0]"
          orientation="vertical"
          tooltip
          popover
          data-testid="shell-sidebar-primary"
          @update:model-value="onOpenSectionChange"
        />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="links[1]"
          orientation="vertical"
          tooltip
          class="mt-auto"
          data-testid="shell-sidebar-secondary"
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <UDashboardSearch :groups="groups" />

    <div class="flex min-h-0 min-w-0 flex-1 flex-col">
      <slot />
    </div>

    <NotificationsSlideover />
  </UDashboardGroup>
</template>
