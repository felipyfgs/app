<script setup lang="ts">
/**
 * Lista de mensagens da Caixa Postal (arquétipo InboxList).
 */
export interface MailboxListItem {
  id: number
  client_id?: number | null
  subject_preview?: string | null
  sender_label?: string | null
  triage_status?: string | null
  severity_hint?: string | null
  received_at_official?: string | null
  due_at?: string | null
  official_read_indicator?: string | boolean | null
  has_body?: boolean
  attachment_count?: number
}

const props = defineProps<{
  messages: MailboxListItem[]
  selectedId?: number | null
  loading?: boolean
}>()

const emit = defineEmits<{
  select: [id: number]
}>()

function isUnread(m: MailboxListItem) {
  const v = m.official_read_indicator
  if (v === false || v === 'UNREAD' || v === 'false') return true
  return m.triage_status === 'NEW'
}

/** Restaura foco no item da lista (após fechar slideover/detalhe). */
function focusMessage(id: number | null | undefined) {
  if (!id) return
  nextTick(() => {
    const el = document.querySelector<HTMLElement>(`[data-mailbox-id="${id}"]`)
    el?.focus()
  })
}

defineExpose({ focusMessage })
</script>

<template>
  <div
    class="overflow-y-auto divide-y divide-default"
    data-testid="mailbox-list"
  >
    <div
      v-if="loading && !messages.length"
      class="p-6 text-sm text-muted"
    >
      Carregando mensagens…
    </div>
    <div
      v-else-if="!messages.length"
      class="flex flex-col items-center justify-center gap-2 p-12 text-center"
      data-testid="fiscal-empty"
    >
      <UIcon
        name="i-lucide-inbox"
        class="size-8 text-dimmed"
      />
      <p class="text-sm text-muted">
        Nenhuma mensagem retornada pela API.
      </p>
    </div>
    <button
      v-for="mail in messages"
      :id="`mailbox-item-${mail.id}`"
      :key="mail.id"
      type="button"
      :data-mailbox-id="mail.id"
      class="w-full p-4 sm:px-6 text-sm text-start cursor-pointer border-l-2 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
      :class="[
        isUnread(mail) ? 'text-highlighted font-medium' : 'text-toned',
        selectedId === mail.id
          ? 'border-primary bg-primary/10'
          : 'border-transparent hover:border-primary hover:bg-primary/5'
      ]"
      :aria-current="selectedId === mail.id ? 'true' : undefined"
      @click="emit('select', mail.id)"
    >
      <div class="flex items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2">
          <span class="truncate">{{ mail.sender_label || `Cliente #${mail.client_id ?? '—'}` }}</span>
          <UChip
            v-if="isUnread(mail)"
            size="sm"
          />
        </div>
        <span class="shrink-0 text-xs text-muted">
          {{ formatDateTime(mail.received_at_official) }}
        </span>
      </div>
      <p class="mt-0.5 truncate">
        {{ mail.subject_preview || 'Sem assunto' }}
      </p>
      <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
        <UBadge
          color="neutral"
          variant="subtle"
          size="sm"
        >
          {{ mail.triage_status || '—' }}
        </UBadge>
        <span v-if="mail.due_at">Prazo: {{ formatDateTime(mail.due_at) }}</span>
        <span v-if="(mail.attachment_count || 0) > 0">
          {{ mail.attachment_count }} anexo(s)
        </span>
      </div>
    </button>
  </div>
</template>
