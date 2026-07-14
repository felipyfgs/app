<script setup lang="ts">
/**
 * Lista mestre de notas — padrão InboxList do template
 * (.reference/nuxt-dashboard-template/app/components/inbox/InboxList.vue).
 */
import type { NfseNote } from '~/types/api'

const props = defineProps<{
  notes: NfseNote[]
  loading?: boolean
  error?: string | null
  selectedAccessKey?: string | null
  nextCursor?: string | null
}>()

const emit = defineEmits<{
  select: [note: NfseNote]
  loadMore: []
  retry: []
}>()

const notesRefs = ref<Record<string, Element | null>>({})

function isSelected(note: NfseNote) {
  return props.selectedAccessKey === note.access_key
}

function shortKey(accessKey: string) {
  if (accessKey.length <= 18) return accessKey
  return `${accessKey.slice(0, 8)}…${accessKey.slice(-6)}`
}

watch(
  () => props.selectedAccessKey,
  (key) => {
    if (!key) return
    const el = notesRefs.value[key]
    el?.scrollIntoView({ block: 'nearest' })
  }
)

defineShortcuts({
  arrowdown: () => {
    if (!props.notes.length) return
    const index = props.notes.findIndex(n => n.access_key === props.selectedAccessKey)
    if (index === -1) {
      emit('select', props.notes[0]!)
    } else if (index < props.notes.length - 1) {
      emit('select', props.notes[index + 1]!)
    }
  },
  arrowup: () => {
    if (!props.notes.length) return
    const index = props.notes.findIndex(n => n.access_key === props.selectedAccessKey)
    if (index === -1) {
      emit('select', props.notes[props.notes.length - 1]!)
    } else if (index > 0) {
      emit('select', props.notes[index - 1]!)
    }
  }
})
</script>

<template>
  <div data-testid="data-table" class="flex min-h-0 flex-1 flex-col">
    <UAlert
      v-if="error"
      :color="notes.length ? 'warning' : 'error'"
      icon="i-lucide-wifi-off"
      :title="notes.length ? 'Falha ao atualizar notas' : 'Não foi possível carregar notas'"
      :description="error"
      class="mx-4 mb-2 shrink-0 sm:mx-6"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => emit('retry') }]"
    />

    <div
      v-if="loading && !notes.length"
      class="space-y-0 divide-y divide-default overflow-y-auto"
    >
      <div v-for="i in 8" :key="i" class="px-4 py-3 sm:px-6">
        <USkeleton class="mb-2 h-4 w-2/3" />
        <USkeleton class="h-3 w-1/2" />
      </div>
    </div>

    <div
      v-else-if="notes.length"
      class="min-h-0 flex-1 overflow-y-auto divide-y divide-default"
    >
      <div
        v-for="note in notes"
        :key="note.access_key"
        :ref="(el) => { notesRefs[note.access_key] = el as Element | null }"
      >
        <button
          type="button"
          class="w-full border-l-2 p-3 text-left text-sm transition-colors sm:px-4 sm:py-3"
          :class="[
            isSelected(note)
              ? 'border-primary bg-primary/10 text-highlighted'
              : 'border-transparent text-toned hover:border-primary hover:bg-primary/5'
          ]"
          :aria-label="note.access_key"
          :aria-current="isSelected(note) ? 'true' : undefined"
          @click="emit('select', note)"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="truncate font-mono text-xs font-medium text-highlighted">
                {{ shortKey(note.access_key) }}
              </p>
              <p class="mt-0.5 truncate text-xs text-muted">
                {{ statusLabel(note.fiscal_role) }}
                <template v-if="note.issuer_cnpj">
                  · {{ note.issuer_cnpj }}
                </template>
              </p>
            </div>
            <div class="shrink-0 text-right">
              <AppStatusBadge :status="note.status" />
              <p v-if="note.competence" class="mt-1 text-xs text-muted">
                {{ note.competence }}
              </p>
            </div>
          </div>
          <div class="mt-1 flex items-center justify-between gap-2">
            <p class="truncate text-xs text-dimmed">
              Tomador {{ note.taker_cnpj || '—' }}
            </p>
            <p class="shrink-0 text-xs font-medium tabular-nums text-highlighted">
              {{ formatCurrency(note.service_amount) }}
            </p>
          </div>
        </button>
      </div>

      <div v-if="nextCursor" class="flex justify-center border-t border-default p-3">
        <UButton
          :loading="loading"
          color="neutral"
          variant="subtle"
          size="sm"
          label="Carregar mais"
          @click="emit('loadMore')"
        />
      </div>
    </div>

    <div v-else class="flex flex-1 items-center justify-center p-6">
      <UEmpty
        icon="i-lucide-file-search"
        title="Nenhuma nota encontrada"
        description="Revise os filtros ou aguarde a próxima sincronização do ADN."
      />
    </div>
  </div>
</template>
