<script setup lang="ts">
/**
 * Navegação horizontal do Monitoramento — canônico (UNavigationMenu highlight
 * em UDashboardToolbar). Mobile: scroll-x sem overflow do documento.
 *
 * Resolve auto-import Nuxt: `MonitoringModuleNav` (pasta monitoring/).
 * Props: `active` opcional força o módulo destacado (páginas legadas).
 */
import type { FiscalModuleKey } from '~/types/fiscal-modules'
import { monitoringNavMenuItems } from '~/utils/monitoring-nav'

const props = defineProps<{
  /** Módulo forçado como ativo (senão deriva de `route.path`). */
  active?: FiscalModuleKey | string
}>()

const route = useRoute()

const items = computed(() => [
  monitoringNavMenuItems(route.path, props.active)
])
</script>

<template>
  <div
    class="min-w-0 flex-1 overflow-x-auto overscroll-x-contain [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    data-testid="monitoring-module-nav"
  >
    <UNavigationMenu
      :items="items"
      highlight
      class="-mx-1 min-w-max"
    />
  </div>
</template>
