<script setup lang="ts">
// Compat: /notes → /docs
await navigateTo({ path: '/docs', query: useRoute().query }, { redirectCode: 301, replace: true })
</script>

<template>
  <div />
</template>
