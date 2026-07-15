<script setup lang="ts">
/**
 * Alias fiscal/ → canônico em `components/monitoring/MonitoringModuleNav.vue`.
 *
 * Mantido só para auto-import `FiscalMonitoringModuleNav` e imports relativos
 * legados sob `components/fiscal/*`. Não duplicar markup/DOM.
 */
import type { FiscalModuleKey } from '~/types/fiscal-modules'
import CanonicalMonitoringModuleNav from '../monitoring/MonitoringModuleNav.vue'

const props = defineProps<{
  active?: FiscalModuleKey | string
}>()
</script>

<template>
  <CanonicalMonitoringModuleNav :active="props.active" />
</template>
