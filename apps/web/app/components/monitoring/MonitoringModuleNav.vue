<script setup lang="ts">
/**
 * Navegação da área Fiscal — Tabs → Subtabs via SectionNavigation.
 * URL = verdade da navegação; highlight nunca inventa outro destino.
 */
import SectionNavigation from '~/components/navigation/SectionNavigation.vue'
import { fiscalNavigationItems } from '~/utils/fiscal-navigation'

defineProps<{
  /**
   * @deprecated Preferir resolução só pela rota atual.
   * Mantido por compatibilidade em páginas legadas; ignorado na resolução.
   */
  active?: string
}>()

const route = useRoute()
const { me } = useDashboard()
const items = computed(() => fiscalNavigationItems(me.value))
const showSkeleton = computed(() => items.value.length === 0 && me.value == null)
</script>

<template>
  <div
    v-if="showSkeleton"
    class="flex min-w-0 flex-1 flex-col gap-2 py-1"
    data-testid="monitoring-module-nav-skeleton"
    aria-hidden="true"
  >
    <USkeleton class="h-8 w-full max-w-xl" />
    <USkeleton class="h-7 w-full max-w-md" />
  </div>
  <SectionNavigation
    v-else
    :items="items"
    :path="route.fullPath"
    aria-label="Navegação fiscal"
    subtabs-aria-label="Módulos fiscais"
    test-id="monitoring-module-nav"
  />
</template>
