<script setup lang="ts">
const props = defineProps<{
  status?: string | null
  label?: string | null
}>()

const color = computed(() => {
  const s = (props.status || '').toUpperCase()
  // NFS-e operacional: Autorizada (success) / Cancelada (error) / Em revisão (warning)
  if (['ACTIVE', 'SUBSTITUTE', 'JUDICIAL', 'AUTHORIZED'].includes(s)) {
    return 'success' as const
  }
  if (['CANCELLED', 'SUPERSEDED', 'REPLACED'].includes(s)) {
    return 'error' as const
  }
  if (['UNKNOWN', 'REVIEW'].includes(s)) {
    return 'warning' as const
  }
  // Demais módulos do painel
  if (['FAILED', 'ERROR', 'EXPIRED', 'BLOCKED'].includes(s)) {
    return 'error' as const
  }
  if (['PENDING', 'PROCESSING', 'RUNNING', 'WAITING'].includes(s)) {
    return 'warning' as const
  }
  if (['READY', 'COMPLETED', 'IDLE'].includes(s)) {
    return 'success' as const
  }
  return 'neutral' as const
})
</script>

<template>
  <UBadge :color="color" variant="subtle">
    {{ label || statusLabel(status) }}
  </UBadge>
</template>
