<script setup lang="ts">
/**
 * Navegação horizontal do Monitoramento — canônico (UNavigationMenu highlight
 * diretamente em UDashboardToolbar, como settings.vue do template).
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
  <UNavigationMenu
    :items="items"
    highlight
    class="-mx-1 flex-1"
    data-testid="monitoring-module-nav"
  />
</template>
