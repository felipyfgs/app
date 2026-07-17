<script setup lang="ts">
/**
 * Cabeçalho de seção settings — naked horizontal (template settings/index).
 * Slot default = ação (Salvar); título/descrição breves.
 */
withDefaults(defineProps<{
  title: string
  description?: string
  testId?: string
}>(), {
  description: undefined,
  testId: undefined
})
</script>

<template>
  <UPageCard
    :title="title"
    :description="description"
    variant="naked"
    orientation="horizontal"
    class="mb-4 min-w-0"
    :data-testid="testId"
  >
    <div
      v-if="$slots.default"
      class="flex w-full flex-wrap items-center gap-2 lg:ms-auto lg:w-fit"
    >
      <slot />
    </div>
  </UPageCard>
</template>
