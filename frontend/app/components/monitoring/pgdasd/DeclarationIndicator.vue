<script setup lang="ts">
const props = defineProps<{
  period?: string | null
  state?: string | null
  reason?: string | null
}>()

const meta = computed(() => pgdasdDeclarationMeta(props.state))
const tooltip = computed(() => [
  meta.value.label,
  props.reason?.trim() || meta.value.description
].join(': '))
</script>

<template>
  <UTooltip :text="tooltip">
    <UBadge
      :label="period || '—'"
      :icon="meta.icon"
      :color="meta.color"
      variant="subtle"
      class="min-w-24 justify-center tabular-nums"
      :aria-label="`${period || 'Período não informado'}: ${meta.label}`"
      data-testid="pgdasd-declaration-indicator"
    />
  </UTooltip>
</template>
