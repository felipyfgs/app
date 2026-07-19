<script setup lang="ts">
/**
 * Casca canônica de página autenticada — UDashboardPanel + slots.
 * Uso: `#header`, `#toolbar`, `#body` (ou default).
 */
const props = withDefaults(defineProps<{
  id: string
  /** Classes extras no `#body` via ui do painel. */
  bodyClass?: string
  testId?: string
}>(), {
  bodyClass: undefined,
  testId: undefined
})

const resolvedUi = computed(() =>
  props.bodyClass ? { body: props.bodyClass } : undefined
)
</script>

<template>
  <UDashboardPanel
    :id="id"
    :data-testid="testId"
    :ui="resolvedUi"
  >
    <template
      v-if="$slots.header"
      #header
    >
      <slot name="header" />
    </template>
    <template #body>
      <slot name="body">
        <slot />
      </slot>
    </template>
  </UDashboardPanel>
</template>
