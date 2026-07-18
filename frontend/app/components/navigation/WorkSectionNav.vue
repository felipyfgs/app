<script setup lang="ts">
import SectionNavigation from '~/components/navigation/SectionNavigation.vue'
import { workNavigationItems } from '~/utils/work-navigation'

const route = useRoute()
const { me } = useDashboard()

const items = computed(() => workNavigationItems(me.value))
</script>

<template>
  <SectionNavigation
    :items="items"
    :path="route.fullPath"
    aria-label="Navegação de Trabalho"
    subtabs-aria-label="Subnavegação de Trabalho"
    test-id="work-section-navigation"
  />
</template>
