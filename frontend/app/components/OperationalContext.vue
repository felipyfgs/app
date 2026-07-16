<script setup lang="ts">
/**
 * Contexto operacional compacto para toolbar/header de superfícies.
 * Não encapsula navbar, toolbar ou página — apenas chips de leitura.
 * Escritório ativo permanece no shell (`OfficeIdentity`); aqui: cliente,
 * competência/período, ambiente e origem do dado.
 */
export interface OperationalContextItem {
  key: string
  label: string
  value: string
  icon?: string
  /** Semântica visual (origem/ambiente). */
  color?: 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'info'
}

const props = withDefaults(defineProps<{
  items?: OperationalContextItem[]
  /** Texto acessível do grupo. */
  ariaLabel?: string
  density?: 'default' | 'compact'
}>(), {
  items: () => [],
  ariaLabel: 'Contexto operacional',
  density: 'default'
})

const visible = computed(() =>
  (props.items || []).filter(item => item.value && String(item.value).trim().length > 0)
)
</script>

<template>
  <div
    v-if="visible.length"
    class="flex min-w-0 flex-wrap items-center gap-1.5"
    :class="density === 'compact' ? 'text-xs' : 'text-sm'"
    role="group"
    :aria-label="ariaLabel"
    data-testid="operational-context"
  >
    <UBadge
      v-for="item in visible"
      :key="item.key"
      :color="item.color || 'neutral'"
      variant="subtle"
      :size="density === 'compact' ? 'sm' : 'md'"
      class="max-w-full truncate"
      :aria-label="`${item.label}: ${item.value}`"
    >
      <span class="inline-flex min-w-0 items-center gap-1">
        <UIcon
          v-if="item.icon"
          :name="item.icon"
          class="size-3.5 shrink-0"
          aria-hidden="true"
        />
        <span class="shrink-0 text-muted">{{ item.label }}</span>
        <span class="truncate font-medium text-highlighted">{{ item.value }}</span>
      </span>
    </UBadge>
  </div>
</template>
