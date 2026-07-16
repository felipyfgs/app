<script setup lang="ts">
/**
 * Faixa utilitária de filtros no body — mesmo papel da div de customers.vue:
 * `flex flex-wrap items-center justify-between gap-1.5`
 * Sem Teleport / sticky (não existem no template).
 */
defineOptions({ inheritAttrs: false })
</script>

<template>
  <div
    v-bind="$attrs"
    data-dashboard-table-filters
    class="flex flex-wrap items-center justify-between gap-1.5"
  >
    <slot />
  </div>
</template>
