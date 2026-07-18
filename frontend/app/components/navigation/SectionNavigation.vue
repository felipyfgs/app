<script setup lang="ts">
/**
 * Navegação de seção responsiva (arquétipo Settings).
 * Recebe catálogo já filtrado — não decide autorização nem tenancy.
 *
 * Desktop (lg+): UNavigationMenu com highlight.
 * Mobile: seletor rotulado com alvos ≥44px (sem scroll de descoberta).
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { NavLayerItem, NavLeafDestination } from '~/utils/navigation-hierarchy'
import {
  groupEntryTo,
  isNavLeaf,
  isNavTabGroup,
  normalizeNavPath,
  pathMatchesLeaf,
  resolveNavSelection
} from '~/utils/navigation-hierarchy'

const props = withDefaults(defineProps<{
  items: NavLayerItem[]
  /** Localização atual, incluindo query quando ela diferencia seções (default: route.fullPath). */
  path?: string
  ariaLabel?: string
  subtabsAriaLabel?: string
  testId?: string
  /** Quando false, emite `navigate` em vez de chamar o router. */
  navigateWithRouter?: boolean
}>(), {
  ariaLabel: 'Navegação da seção',
  subtabsAriaLabel: 'Subnavegação da seção',
  testId: 'section-navigation',
  navigateWithRouter: true
})

const emit = defineEmits<{
  navigate: [leaf: NavLeafDestination]
}>()

const route = useRoute()
const router = useRouter()

// `fullPath` preserva tabs que compartilham a rota e variam por query
// (ex.: `?section=`). A prop continua permitindo uso controlado em modais.
const currentLocation = computed(() => props.path ?? route.fullPath ?? route.path)
const selection = computed(() => resolveNavSelection(props.items, currentLocation.value))

function leafActive(leaf: NavLeafDestination): boolean {
  if (selection.value.leaf?.id === leaf.id) return true
  if (selection.value.leaf) return false
  return pathMatchesLeaf(currentLocation.value, leaf)
}

/** Destino de clique do grupo: sempre o 1º filho (nunca a folha ativa de outro contexto). */
function groupTargetLeaf(group: Extract<NavLayerItem, { children: NavLeafDestination[] }>) {
  return group.children[0]
}

function sameRouteDestination(to: string | undefined): boolean {
  if (!to) return true
  if (normalizeNavPath(to) !== normalizeNavPath(currentLocation.value)) return false

  const [, targetQuery = ''] = to.split('?')
  if (!targetQuery) return !currentLocation.value.includes('?')

  const [, currentQuery = ''] = currentLocation.value.split('?')
  const target = new URLSearchParams(targetQuery)
  const current = new URLSearchParams(currentQuery)
  if (target.size !== current.size) return false

  return [...target.entries()].every(([key, value]) => current.get(key) === value)
}

function tabMenuItems(): NavigationMenuItem[] {
  return selection.value.tabs.map((item) => {
    if (isNavLeaf(item)) {
      const menuItem: NavigationMenuItem = {
        label: item.label,
        icon: item.icon,
        exact: item.exact === true,
        active: leafActive(item)
      }
      if (props.navigateWithRouter) {
        menuItem.to = item.to
      } else {
        menuItem.onSelect = () => emit('navigate', item)
      }
      return menuItem
    }

    const targetLeaf = groupTargetLeaf(item)
    const entry = groupEntryTo(item)
    const active = selection.value.group?.id === item.id
    const menuItem: NavigationMenuItem = {
      label: item.label,
      icon: item.icon,
      active
    }
    if (props.navigateWithRouter) {
      menuItem.to = entry
    } else if (targetLeaf) {
      menuItem.onSelect = () => emit('navigate', targetLeaf)
    }
    return menuItem
  })
}

function subtabMenuItems(): NavigationMenuItem[] {
  return selection.value.subtabs.map((leaf) => {
    const menuItem: NavigationMenuItem = {
      label: leaf.label,
      icon: leaf.icon,
      exact: leaf.exact === true,
      active: leafActive(leaf)
    }
    if (props.navigateWithRouter) {
      menuItem.to = leaf.to
    } else {
      menuItem.onSelect = () => emit('navigate', leaf)
    }
    return menuItem
  })
}

const desktopTabItems = computed(() => [tabMenuItems()])
const desktopSubtabItems = computed(() => [subtabMenuItems()])

type SelectOption = { label: string, id: string, to: string, icon?: string, leaf?: NavLeafDestination }

const tabSelectItems = computed<SelectOption[]>(() =>
  selection.value.tabs.map((item) => {
    if (isNavLeaf(item)) {
      return {
        label: item.label,
        id: item.id,
        icon: item.icon,
        to: item.to,
        leaf: item
      }
    }
    const targetLeaf = groupTargetLeaf(item)
    return {
      label: item.label,
      id: item.id,
      icon: item.icon,
      to: groupEntryTo(item),
      leaf: targetLeaf
    }
  })
)

const subtabSelectItems = computed<SelectOption[]>(() =>
  selection.value.subtabs.map(leaf => ({
    label: leaf.label,
    id: leaf.id,
    to: leaf.to,
    icon: leaf.icon,
    leaf
  }))
)

const selectedTabId = computed(() => {
  if (selection.value.group) return selection.value.group.id
  return selection.value.leaf?.id ?? tabSelectItems.value[0]?.id
})

const selectedSubtabId = computed(
  () => selection.value.leaf?.id ?? subtabSelectItems.value[0]?.id
)

async function navigateToOption(option: SelectOption | undefined) {
  if (!option) return
  if (!props.navigateWithRouter) {
    if (option.leaf) emit('navigate', option.leaf)
    return
  }
  if (!option.to || sameRouteDestination(option.to)) return
  await router.push(option.to)
}

function onTabSelect(value: unknown) {
  const id = String(value ?? '')
  void navigateToOption(tabSelectItems.value.find(item => item.id === id))
}

function onSubtabSelect(value: unknown) {
  const id = String(value ?? '')
  void navigateToOption(subtabSelectItems.value.find(item => item.id === id))
}

const showSubtabs = computed(
  () => !selection.value.hideSubtabs && selection.value.subtabs.length > 0
)

const showTabsLayer = computed(() => {
  if (selection.value.tabs.length === 1 && isNavLeaf(selection.value.tabs[0]!)) {
    return false
  }
  if (
    selection.value.tabs.length === 1
    && isNavTabGroup(selection.value.tabs[0]!)
    && selection.value.tabs[0]!.children.length <= 1
  ) {
    return false
  }
  return selection.value.tabs.length > 0
})

const selectedTabLabel = computed(
  () => tabSelectItems.value.find(i => i.id === selectedTabId.value)?.label || 'Seção'
)
const selectedSubtabLabel = computed(
  () => subtabSelectItems.value.find(i => i.id === selectedSubtabId.value)?.label || 'Subseção'
)
</script>

<template>
  <div
    :data-testid="testId"
    class="flex min-w-0 flex-1 flex-col gap-2"
  >
    <div
      v-if="showTabsLayer"
      class="min-w-0"
      :data-testid="`${testId}-tabs`"
    >
      <div
        class="hidden min-w-0 lg:block"
        data-testid="section-nav-tabs-desktop"
      >
        <UNavigationMenu
          :items="desktopTabItems"
          highlight
          class="-mx-1 flex-1"
          :aria-label="ariaLabel"
        />
      </div>

      <div
        class="lg:hidden"
        data-testid="section-nav-tabs-mobile"
      >
        <label
          class="sr-only"
          :for="`${testId}-tab-select`"
        >{{ ariaLabel }}</label>
        <USelectMenu
          :id="`${testId}-tab-select`"
          :model-value="selectedTabId"
          :items="tabSelectItems"
          value-key="id"
          :search-input="false"
          color="neutral"
          variant="outline"
          class="w-full"
          :ui="{
            base: 'min-h-11 w-full justify-between',
            trailing: 'min-h-11'
          }"
          :aria-label="ariaLabel"
          @update:model-value="onTabSelect"
        >
          <template #default>
            <span class="truncate text-left">{{ selectedTabLabel }}</span>
          </template>
        </USelectMenu>
      </div>
    </div>

    <div
      v-if="showSubtabs"
      class="min-w-0"
      :data-testid="`${testId}-subtabs`"
    >
      <div
        class="hidden min-w-0 lg:block"
        data-testid="section-nav-subtabs-desktop"
      >
        <UNavigationMenu
          :items="desktopSubtabItems"
          highlight
          class="-mx-1 flex-1"
          :aria-label="subtabsAriaLabel"
          :ui="{
            linkLabel: 'whitespace-nowrap'
          }"
        />
      </div>

      <div
        class="lg:hidden"
        data-testid="section-nav-subtabs-mobile"
      >
        <label
          class="sr-only"
          :for="`${testId}-subtab-select`"
        >{{ subtabsAriaLabel }}</label>
        <USelectMenu
          :id="`${testId}-subtab-select`"
          :model-value="selectedSubtabId"
          :items="subtabSelectItems"
          value-key="id"
          :search-input="false"
          color="neutral"
          variant="outline"
          class="w-full"
          :ui="{
            base: 'min-h-11 w-full justify-between',
            trailing: 'min-h-11'
          }"
          :aria-label="subtabsAriaLabel"
          @update:model-value="onSubtabSelect"
        >
          <template #default>
            <span class="truncate text-left">{{ selectedSubtabLabel }}</span>
          </template>
        </USelectMenu>
      </div>
    </div>
  </div>
</template>
