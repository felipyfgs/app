<script setup lang="ts">
/**
 * TOTP descontinuado — redireciona para login / home.
 */
definePageMeta({ layout: 'auth' })

const { isAuthenticated } = useSanctumAuth()
await navigateTo(isAuthenticated.value ? '/' : '/login', { replace: true })
</script>

<template>
  <div class="p-6 text-sm text-muted">
    Redirecionando…
  </div>
</template>
