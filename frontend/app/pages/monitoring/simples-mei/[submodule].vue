<script setup lang="ts">
/**
 * Legacy: /monitoring/simples-mei/:submodule → /monitoring/simples-mei
 * Tabs (PGDASD, …) não vão na URL — só o item da sidebar.
 */
definePageMeta({
  middleware: 'monitoring-submodule-legacy'
})
</script>

<template>
  <div />
</template>
