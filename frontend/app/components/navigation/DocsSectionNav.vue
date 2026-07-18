<script setup lang="ts">
import SectionNavigation from '~/components/navigation/SectionNavigation.vue'
import { DOCS_NAV_ITEMS } from '~/utils/docs-operations-navigation'

const route = useRoute()
</script>

<template>
  <SectionNavigation
    :items="DOCS_NAV_ITEMS"
    :path="route.fullPath"
    aria-label="Navegação de Documentos"
    subtabs-aria-label="Processamento de documentos"
    test-id="docs-section-navigation"
  />
</template>
