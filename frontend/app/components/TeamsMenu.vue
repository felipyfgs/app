<script setup lang="ts">
defineProps<{
  collapsed?: boolean
}>()

const { me } = useDashboard()

const label = computed(() => me.value?.office?.name || 'NFS-e ADN')
</script>

<template>
  <UButton
    color="neutral"
    variant="ghost"
    block
    :square="collapsed"
    class="data-[state=open]:bg-elevated"
    :ui="{ trailingIcon: 'text-dimmed' }"
  >
    <UIcon name="i-lucide-receipt" class="size-5 text-primary shrink-0" />
    <span v-if="!collapsed" class="truncate font-semibold text-sm">
      {{ label }}
    </span>
  </UButton>
</template>
