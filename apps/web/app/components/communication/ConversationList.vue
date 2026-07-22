<script setup lang="ts">
import type { CommunicationConversation, CommunicationInbox } from '~/types/communication'
import {
  COMMUNICATION_CONVERSATION_STATUS,
  communicationContactLabel,
  communicationDisplayName,
  formatCommunicationDate
} from '~/utils/communication'

const props = defineProps<{
  conversations: CommunicationConversation[]
  inboxes: CommunicationInbox[]
  selectedId?: number | null
  loading?: boolean
}>()

const emit = defineEmits<{
  select: [conversation: CommunicationConversation]
}>()

function inboxName(id: number): string {
  return props.inboxes.find(inbox => inbox.id === id)?.name || `Inbox #${id}`
}
</script>

<template>
  <div
    data-testid="communication-conversation-list"
    class="min-h-0 flex-1 overflow-y-auto divide-y divide-default"
  >
    <div
      v-if="loading && !conversations.length"
      class="space-y-3 p-4"
    >
      <USkeleton
        v-for="item in 6"
        :key="item"
        class="h-24 w-full"
      />
    </div>

    <button
      v-for="conversation in conversations"
      :key="conversation.id"
      type="button"
      class="flex w-full gap-3 border-l-2 p-3 text-left text-sm transition-colors sm:px-4 sm:py-4"
      :class="selectedId === conversation.id
        ? 'border-primary bg-primary/10'
        : 'border-transparent hover:border-primary hover:bg-primary/5'"
      :aria-current="selectedId === conversation.id ? 'true' : undefined"
      @click="emit('select', conversation)"
    >
      <UAvatar
        :alt="communicationDisplayName(conversation)"
        size="md"
        class="mt-0.5 shrink-0"
      />
      <div class="min-w-0 flex-1">
        <div class="flex min-w-0 items-center justify-between gap-3">
          <span class="truncate font-semibold text-highlighted">
            {{ communicationDisplayName(conversation) }}
          </span>
          <span class="shrink-0 text-[11px] text-muted">
            {{ formatCommunicationDate(conversation.last_message_at) }}
          </span>
        </div>

        <p v-if="communicationContactLabel(conversation)" class="mt-0.5 truncate text-xs text-muted">
          {{ communicationContactLabel(conversation) }}
        </p>

        <div class="mt-1 flex min-w-0 items-center justify-between gap-2">
          <span class="truncate text-[11px] text-dimmed">
            {{ inboxName(conversation.inbox_id) }}
          </span>
          <UBadge
            :label="COMMUNICATION_CONVERSATION_STATUS[conversation.status].label"
            :color="COMMUNICATION_CONVERSATION_STATUS[conversation.status].color"
            variant="subtle"
            size="sm"
          />
        </div>

        <div class="mt-2 flex min-w-0 flex-wrap items-center gap-1.5">
          <UBadge
            v-if="conversation.assignee_membership_id == null"
            label="Sem responsável"
            color="warning"
            variant="soft"
            size="sm"
          />
          <UBadge
            v-if="conversation.priority > 0"
            :label="`Prioridade ${conversation.priority}`"
            color="error"
            variant="soft"
            size="sm"
          />
          <UBadge
            v-for="label in conversation.labels?.slice(0, 2)"
            :key="label.id"
            :label="label.name"
            color="neutral"
            variant="outline"
            size="sm"
            class="max-w-28 truncate"
          />
        </div>
      </div>
    </button>

    <div
      v-if="!loading && !conversations.length"
      class="flex h-full min-h-64 flex-col items-center justify-center gap-3 p-8 text-center"
    >
      <UIcon
        name="i-lucide-message-circle-dashed"
        class="size-12 text-dimmed"
      />
      <div>
        <p class="font-medium text-highlighted">
          Nenhuma conversa encontrada
        </p>
        <p class="mt-1 text-sm text-muted">
          Ajuste os filtros ou aguarde uma nova mensagem.
        </p>
      </div>
    </div>
  </div>
</template>
