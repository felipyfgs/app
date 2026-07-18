<script setup lang="ts">
import type { MitListaApuracoes317Payload } from '~/types/fiscal-modules'
import { useMitListaApuracoes } from '~/composables/useMitListaApuracoes'
import { apiErrorMessage } from '~/utils/api-error'
import { formatCurrency, formatDate } from '~/utils/format'
import { formatDctfwebPeriod } from '~/utils/dctfweb'

const props = defineProps<{
  open: boolean
  clientId: number | null
  clientName?: string | null
  cnpjMasked?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { fetchLocalList } = useMitListaApuracoes()
const loading = ref(false)
const error = ref<string | null>(null)
const payload = ref<MitListaApuracoes317Payload | null>(null)
let requestGeneration = 0

const apuracoes = computed(() => payload.value?.data || [])
const serproCalled = computed(() => payload.value?.provenance?.serpro_called === true)

async function loadLocalList() {
  const clientId = props.clientId
  if (!props.open || !clientId) return
  const generation = ++requestGeneration
  loading.value = true
  error.value = null
  try {
    const response = await fetchLocalList(clientId)
    if (generation === requestGeneration) payload.value = response
  } catch (caught) {
    if (generation !== requestGeneration) return
    error.value = apiErrorMessage(caught, 'Não foi possível carregar as apurações MIT locais.')
    payload.value = null
  } finally {
    if (generation === requestGeneration) loading.value = false
  }
}

watch(
  () => [props.open, props.clientId] as const,
  ([open]) => {
    if (open) {
      void loadLocalList()
      return
    }
    requestGeneration += 1
    loading.value = false
    error.value = null
    payload.value = null
  },
  { immediate: true }
)
</script>

<template>
  <UModal
    :open="open"
    :ui="{ content: 'sm:max-w-3xl' }"
    data-testid="mit-lista-apuracoes-modal"
    @update:open="emit('update:open', $event)"
  >
    <template #content>
      <UCard>
        <template #header>
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-highlighted font-semibold">
                Apurações MIT 317
              </h3>
              <p class="text-muted text-sm mt-0.5">
                {{ clientName || 'Cliente' }}
                <span v-if="cnpjMasked"> · {{ cnpjMasked }}</span>
              </p>
              <p class="text-muted text-xs mt-1">
                Projeção local · serpro_called: {{ serproCalled ? 'true' : 'false' }}
              </p>
            </div>
            <UButton
              icon="i-lucide-x"
              color="neutral"
              variant="ghost"
              size="sm"
              aria-label="Fechar apurações MIT"
              @click="emit('update:open', false)"
            />
          </div>
        </template>

        <div v-if="loading" class="flex justify-center py-10">
          <UIcon name="i-lucide-loader-circle" class="size-6 animate-spin text-muted" />
        </div>

        <UAlert
          v-else-if="error"
          color="error"
          icon="i-lucide-triangle-alert"
          :title="error"
        />

        <div v-else class="space-y-3">
          <p class="text-muted text-sm">
            {{ apuracoes.length }} apuração(ões) local(is) retornada(s) pela consulta 317.
          </p>
          <div
            v-if="apuracoes.length === 0"
            class="text-muted text-sm py-6 text-center"
            data-testid="mit-lista-apuracoes-empty"
          >
            Nenhuma apuração MIT 317 local para este cliente.
          </div>
          <div
            v-for="apuracao in apuracoes"
            :key="apuracao.id"
            class="border border-default rounded-lg p-3 space-y-2"
            data-testid="mit-lista-apuracoes-item"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <span class="font-medium">
                {{ formatDctfwebPeriod(apuracao.period_key) }}
              </span>
              <UBadge
                :label="apuracao.situation || 'Não verificado'"
                color="neutral"
                variant="subtle"
                class="rounded-sm"
              />
            </div>
            <div class="grid gap-1 text-sm text-muted sm:grid-cols-2">
              <span>Encerramento: {{ formatDate(apuracao.lista_apuracoes_317?.data_encerramento) }}</span>
              <span>Valor apurado: {{ formatCurrency(apuracao.lista_apuracoes_317?.valor_total_apurado) }}</span>
              <span>Evento especial: {{ apuracao.lista_apuracoes_317?.evento_especial ? 'Sim' : 'Não' }}</span>
            </div>
          </div>
        </div>
      </UCard>
    </template>
  </UModal>
</template>
