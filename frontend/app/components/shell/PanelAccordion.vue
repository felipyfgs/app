<script setup lang="ts">
/**
 * Acordeão de painéis secundários empilhados (doc Nuxt UI Accordion).
 * Use para blocos densos abaixo dos KPIs — não para a faixa de KPI.
 */
import type { AccordionItem } from '@nuxt/ui'

withDefaults(defineProps<{
  items: AccordionItem[]
  /** Valores abertos por padrão (value dos items). */
  defaultValue?: string | string[]
  type?: 'single' | 'multiple'
  testId?: string
}>(), {
  type: 'multiple',
  defaultValue: undefined,
  testId: 'panel-accordion'
})
</script>

<template>
  <UAccordion
    :items="items"
    :type="type"
    :default-value="defaultValue"
    :unmount-on-hide="false"
    :ui="{
      root: 'min-w-0 w-full flex flex-col gap-2',
      item: 'rounded-lg border border-default bg-elevated/50 overflow-hidden',
      header: 'px-3 sm:px-4',
      trigger: 'py-3 text-sm font-medium text-highlighted',
      body: 'px-3 pb-4 sm:px-4 text-sm',
      label: 'min-w-0 truncate'
    }"
    :data-testid="testId"
  >
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        :name="name"
        v-bind="slotData || {}"
      />
    </template>
  </UAccordion>
</template>
