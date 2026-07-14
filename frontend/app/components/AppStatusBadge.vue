<script setup lang="ts">
const props = defineProps<{
  status?: string | null
}>()

const color = computed(() => {
  if (['FAILED', 'ERROR', 'EXPIRED', 'BLOCKED'].includes(props.status || '')) {
    return 'error' as const
  }
  if (['PENDING', 'PROCESSING', 'RUNNING', 'WAITING', 'UNKNOWN'].includes(props.status || '')) {
    return 'warning' as const
  }
  if (['READY', 'COMPLETED', 'ACTIVE', 'IDLE'].includes(props.status || '')) {
    return 'success' as const
  }
  return 'neutral' as const
})
</script>

<template>
  <UBadge :color="color" variant="subtle">
    {{ statusLabel(status) }}
  </UBadge>
</template>
