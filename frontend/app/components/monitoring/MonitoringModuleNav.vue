<script setup lang="ts">
/**
 * Navegação horizontal do Monitoramento — canônico (UNavigationMenu highlight
 * diretamente em UDashboardToolbar, como settings.vue do template).
 *
 * Mobile: scroll horizontal com overscroll touch; labels sem wrap; área de toque
 * um pouco mais densa no phone.
 *
 * Resolve auto-import Nuxt: `MonitoringModuleNav` (pasta monitoring/).
 * Props: `active` opcional força o módulo destacado (páginas legadas).
 */
import type { MonitoringModuleKey } from '~/utils/monitoring-nav'
import { monitoringNavMenuItems } from '~/utils/monitoring-nav'

const props = defineProps<{
  /** Módulo forçado como ativo (senão deriva de `route.path`). */
  active?: MonitoringModuleKey | string
}>()

const route = useRoute()

const items = computed(() => [
  monitoringNavMenuItems(route.path, props.active)
])
</script>

<template>
  <div
    class="-mx-1 min-w-0 flex-1 overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch] touch-pan-x"
    data-testid="monitoring-module-nav-scroll"
  >
    <UNavigationMenu
      :items="items"
      :ui="{
        root: 'gap-0.5',
        link: 'gap-0 px-1.5 sm:px-2',
        linkLabel: 'whitespace-nowrap text-xs sm:text-sm'
      }"
      highlight
      class="w-max min-w-full"
      data-testid="monitoring-module-nav"
    />
  </div>
</template>
