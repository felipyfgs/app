<script setup lang="ts">
import type { NuxtError } from '#app'

defineProps<{
  error: NuxtError
}>()

useSeoMeta({
  title: 'Página não encontrada',
  description: 'A página solicitada não existe ou foi removida.'
})

useHead({
  htmlAttrs: {
    lang: 'pt-BR'
  }
})
</script>

<template>
  <UApp>
    <UError :error="error" />
  </UApp>
</template>
