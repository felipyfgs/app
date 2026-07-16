<script setup lang="ts">
/**
 * Confirmação informada antes de refresh com snapshot recente (consome franquia).
 */
import { recentRefreshConfirmDescription } from '~/utils/monitor-commercial'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  lastAt?: string | null
  remaining?: number | null
  loading?: boolean
}>()

const emit = defineEmits<{
  confirm: []
}>()

const description = computed(() => recentRefreshConfirmDescription({
  lastAt: props.lastAt,
  remaining: props.remaining
}))
</script>

<template>
  <UModal
    v-model:open="open"
    title="Confirmar nova consulta"
    :description="description"
    data-testid="recent-refresh-confirm-modal"
  >
    <template #body>
      <UAlert
        color="warning"
        icon="i-lucide-coins"
        title="Consome 1 unidade da franquia"
        description="O servidor ainda pode bloquear se o intervalo mínimo oficial não tiver passado."
      />
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          color="neutral"
          variant="ghost"
          label="Cancelar"
          @click="() => { open = false }"
        />
        <UButton
          color="primary"
          label="Confirmar consulta"
          icon="i-lucide-cloud-download"
          :loading="loading"
          data-testid="recent-refresh-confirm"
          @click="emit('confirm')"
        />
      </div>
    </template>
  </UModal>
</template>
