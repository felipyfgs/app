<script setup lang="ts">
/**
 * Footer de lista admin — cópia de customers.vue (template 0f30c09):
 * contagem à esquerda + UPagination à direita.
 */
withDefaults(defineProps<{
  /** Linhas selecionadas (0 se sem seleção). */
  selectedCount?: number
  /** Total de linhas no escopo (filtrado / API). */
  total: number
  page: number
  itemsPerPage?: number
}>(), {
  selectedCount: 0,
  itemsPerPage: 10
})

const emit = defineEmits<{
  'update:page': [page: number]
}>()
</script>

<template>
  <div class="flex items-center justify-between gap-3 border-t border-default pt-4 mt-auto">
    <div class="text-sm text-muted">
      {{ selectedCount }} of {{ total }} row(s) selected.
    </div>

    <div class="flex items-center gap-1.5">
      <UPagination
        :page="page"
        :items-per-page="itemsPerPage"
        :total="total"
        @update:page="emit('update:page', $event)"
      />
    </div>
  </div>
</template>
