<script setup lang="ts">
import type { RegimeOptionPayload } from '~/types/fiscal-modules'

const props = defineProps<{
  open: boolean
  clientId: number | null
  clientName?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { fetchHistory } = useRegimeOptionMonitoring()
const loading = ref(false)
const error = ref<string | null>(null)
const history = ref<RegimeOptionPayload | null>(null)
let generation = 0

function regimeLabel(value: string): string {
  return value === 'CAIXA' ? 'Caixa' : 'Competência'
}

async function load() {
  if (!props.clientId) return
  const requestGeneration = ++generation
  loading.value = true
  error.value = null
  try {
    const response = await fetchHistory(props.clientId)
    if (requestGeneration === generation) history.value = response
  } catch (caught) {
    if (requestGeneration !== generation) return
    history.value = null
    error.value = apiErrorMessage(caught, 'Não foi possível carregar as opções locais de regime.')
  } finally {
    if (requestGeneration === generation) loading.value = false
  }
}

watch(
  () => [props.open, props.clientId] as const,
  ([open]) => {
    if (open) {
      void load()
      return
    }
    generation += 1
    loading.value = false
    error.value = null
    history.value = null
  },
  { immediate: true }
)
</script>

<template>
  <UModal
    :open="open"
    title="Opções anuais de regime"
    description="Dados já armazenados localmente; abrir este modal não consulta a SERPRO."
    :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-xl' }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <div class="space-y-4" data-testid="regime-option-modal">
        <p class="font-medium text-highlighted">
          {{ clientName || `Cliente #${clientId || '—'}` }}
        </p>
        <UAlert v-if="error" color="error" :title="error">
          <template #actions>
            <UButton
              size="xs"
              color="neutral"
              variant="outline"
              label="Tentar novamente"
              @click="load"
            />
          </template>
        </UAlert>
        <div v-else-if="loading" class="space-y-2" aria-label="Carregando opções locais de regime">
          <USkeleton class="h-10 w-full" />
          <USkeleton class="h-10 w-full" />
        </div>
        <template v-else>
          <div v-if="history?.data.length" class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="text-xs text-muted">
                <tr>
                  <th class="pb-2 pr-3 font-medium">
                    Ano-calendário
                  </th><th class="pb-2 font-medium">
                    Opção
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in history.data" :key="item.calendar_year" class="border-t border-default">
                  <td class="py-2 pr-3 tabular-nums">
                    {{ item.calendar_year }}
                  </td>
                  <td class="py-2">
                    <UBadge color="primary" variant="subtle" :label="regimeLabel(item.regime_apuracao)" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-sm text-muted">
            Nenhuma opção anual armazenada para este cliente.
          </p>
          <p class="text-xs text-muted">
            Origem: {{ history?.provenance?.source || 'projeção local' }}.
          </p>
        </template>
      </div>
    </template>
  </UModal>
</template>
