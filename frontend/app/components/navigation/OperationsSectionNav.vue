<script setup lang="ts">
import SectionNavigation from '~/components/navigation/SectionNavigation.vue'
import { OPERATIONS_NAV_ITEMS } from '~/utils/docs-operations-navigation'

const route = useRoute()
</script>

<template>
  <SectionNavigation
    :items="OPERATIONS_NAV_ITEMS"
    :path="route.fullPath"
    aria-label="Navegação de Operações"
    subtabs-aria-label="Subnavegação de Operações"
    test-id="operations-section-navigation"
  />
</template>
