<script setup lang="ts">
/**
 * Coluna / chip de Procuração (estados oficiais sincronizados).
 */
import { procuracaoLabel, procuracaoTone, procuracaoActionHint } from '~/utils/procuracao'

const props = defineProps<{
  status?: string | null
  checkedAt?: string | null
  showHint?: boolean
}>()

const label = computed(() => procuracaoLabel(props.status))
const tone = computed(() => procuracaoTone(props.status))
const hint = computed(() => props.showHint ? procuracaoActionHint(props.status) : null)
</script>

<template>
  <div
    class="min-w-0"
    data-testid="client-procuracao-badge"
  >
    <UBadge
      :color="tone"
      variant="subtle"
      size="sm"
      :aria-label="`Procuração: ${label}`"
    >
      {{ label }}
    </UBadge>
    <p
      v-if="checkedAt"
      class="mt-0.5 text-[10px] text-muted"
    >
      Verificado {{ formatDateTime(checkedAt) }}
    </p>
    <p
      v-if="hint"
      class="mt-0.5 text-[10px] text-muted"
    >
      {{ hint }}
    </p>
  </div>
</template>
