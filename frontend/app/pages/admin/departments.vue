<script setup lang="ts">
/**
 * Departamentos movidos para /settings/departments.
 * Redirect permanente para preservar bookmarks.
 */
await navigateTo('/settings/departments', { redirectCode: 301, replace: true })
</script>

<template>
  <div class="p-6 text-sm text-muted">
    Redirecionando para Configurações → Departamentos…
  </div>
</template>
