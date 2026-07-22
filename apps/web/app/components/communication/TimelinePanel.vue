<script setup lang="ts">
import type {
  CommunicationCannedResponse,
  CommunicationComposerPayload,
  CommunicationConversation,
  CommunicationConversationSignals,
  CommunicationInbox,
  CommunicationMessage
} from '~/types/communication'
import {
  COMMUNICATION_CONVERSATION_STATUS,
  COMMUNICATION_MESSAGE_STATUS,
  communicationContactLabel,
  communicationDisplayName,
  communicationMessageSummary,
  formatCommunicationDate
} from '~/utils/communication'
import { COMMUNICATION_REACTION_EMOJIS } from '~/utils/communication-composer'

const props = defineProps<{
  conversation: CommunicationConversation
  inbox?: CommunicationInbox | null
  signals?: CommunicationConversationSignals
  cannedResponses: CommunicationCannedResponse[]
  canReply: boolean
  operational: boolean
  outboundOperational: boolean
  loading?: boolean
  sending?: boolean
  actionLoadingId?: number | null
  mobile?: boolean
  contextOpen?: boolean
}>()

const emit = defineEmits<{
  close: []
  toggleContext: []
  send: [
    payload: CommunicationComposerPayload,
    acknowledge: (ok: boolean) => void
  ]
  update: [patch: Record<string, unknown>]
  download: [message: CommunicationMessage, attachmentId: number, filename: string]
  edit: [message: CommunicationMessage, text: string, acknowledge: (ok: boolean) => void]
  revoke: [message: CommunicationMessage]
  react: [message: CommunicationMessage, emoji: string | null]
  vote: [message: CommunicationMessage, optionNames: string[]]
  receipt: [message: CommunicationMessage, receipt: 'READ' | 'PLAYED']
  recover: [message: CommunicationMessage, operation: 'UNAVAILABLE' | 'MEDIA_RETRY']
  presence: [presence: 'COMPOSING' | 'PAUSED' | 'RECORDING']
}>()

const messagesContainer = ref<HTMLElement | null>(null)
const replyTo = ref<CommunicationMessage | null>(null)
const editTarget = ref<CommunicationMessage | null>(null)
const editDraft = ref('')
const revokeTarget = ref<CommunicationMessage | null>(null)
const highlightedMessageId = ref<number | null>(null)
let highlightTimer: ReturnType<typeof setTimeout> | null = null

const statusItems = Object.entries(COMMUNICATION_CONVERSATION_STATUS).map(([value, meta]) => ({
  label: meta.label,
  value,
  icon: meta.icon
}))

const chatPresenceLabel = computed(() => {
  const signal = props.signals?.chat
  if (!signal) return null
  return signal.presence === 'RECORDING' || signal.media === 'AUDIO'
    ? 'gravando áudio…'
    : 'digitando…'
})

function setStatus(value: string | number | undefined): void {
  if (typeof value !== 'string' || value === props.conversation.status) return
  if (value === 'SNOOZED') {
    emit('update', {
      status: value,
      snoozed_until: new Date(Date.now() + 60 * 60 * 1000).toISOString()
    })
    return
  }
  emit('update', { status: value, snoozed_until: null })
}

function quotedMessage(message: CommunicationMessage): CommunicationMessage | undefined {
  return props.conversation.messages?.find(item => item.id === message.reply_to_message_id)
}

function openEdit(message: CommunicationMessage): void {
  editTarget.value = message
  editDraft.value = message.body || ''
}

function submitEdit(): void {
  const target = editTarget.value
  const text = editDraft.value.trim()
  if (!target || !text || text === target.body?.trim()) return
  emit('edit', target, text, (ok) => {
    if (ok) editTarget.value = null
  })
}

interface MessageActionItem {
  label: string
  icon: string
  disabled?: boolean
  color?: 'error'
  onSelect: () => void
}

function closeEdit(): void {
  editTarget.value = null
}

function closeRevoke(): void {
  revokeTarget.value = null
}

function confirmRevoke(): void {
  if (!revokeTarget.value) return
  emit('revoke', revokeTarget.value)
  closeRevoke()
}

function messageActionItems(message: CommunicationMessage): MessageActionItem[][] {
  const remote = message.direction !== 'INTERNAL' && !message.metadata?.revoked
  const groups: MessageActionItem[][] = [[{
    label: 'Citar mensagem',
    icon: 'i-lucide-reply',
    disabled: !remote,
    onSelect: () => { replyTo.value = message }
  }]]
  if (remote) {
    groups.push([{
      label: 'Remover minha reação',
      icon: 'i-lucide-eraser',
      onSelect: () => emit('react', message, null)
    }])
  }
  if (message.direction === 'OUTBOUND' && message.body && !message.metadata?.revoked) {
    groups.push([
      {
        label: 'Editar mensagem',
        icon: 'i-lucide-pencil',
        onSelect: () => openEdit(message)
      },
      {
        label: 'Apagar para todos',
        icon: 'i-lucide-trash-2',
        color: 'error' as const,
        onSelect: () => { revokeTarget.value = message }
      }
    ])
  }
  if (message.direction === 'INBOUND') {
    groups.push([{
      label: 'Marcar como lida',
      icon: 'i-lucide-check-check',
      onSelect: () => emit('receipt', message, 'READ')
    }])
  }
  return groups
}

function isRemoteMessage(message: CommunicationMessage): boolean {
  return message.direction !== 'INTERNAL' && !message.metadata?.revoked
}

function scrollToMessage(messageId: number): void {
  const target = messagesContainer.value?.querySelector<HTMLElement>(`[data-message-id="${messageId}"]`)
  if (!target) return
  target.scrollIntoView({ behavior: 'smooth', block: 'center' })
  highlightedMessageId.value = messageId
  if (highlightTimer) clearTimeout(highlightTimer)
  highlightTimer = setTimeout(() => {
    highlightedMessageId.value = null
    highlightTimer = null
  }, 1_800)
}

function scrollToLatest(): void {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

watch(
  () => [props.conversation.id, props.conversation.messages?.length] as const,
  scrollToLatest,
  { immediate: true }
)

onBeforeUnmount(() => {
  if (highlightTimer) clearTimeout(highlightTimer)
})
</script>

<template>
  <UDashboardPanel
    :id="`communication-timeline-${conversation.id}`"
    data-testid="communication-timeline-panel"
    class="min-w-0"
  >
    <UDashboardNavbar
      :title="communicationDisplayName(conversation)"
      :toggle="false"
    >
      <template #leading>
        <UButton
          v-if="mobile"
          icon="i-lucide-arrow-left"
          color="neutral"
          variant="ghost"
          aria-label="Voltar à lista"
          @click="emit('close')"
        />
        <UAvatar
          v-else
          :alt="communicationDisplayName(conversation)"
          size="sm"
        />
      </template>

      <template #trailing>
        <UBadge
          :label="COMMUNICATION_CONVERSATION_STATUS[conversation.status].label"
          :color="COMMUNICATION_CONVERSATION_STATUS[conversation.status].color"
          variant="subtle"
        />
      </template>

      <template #right>
        <USelectMenu
          :model-value="conversation.status"
          :items="statusItems"
          value-key="value"
          class="hidden w-36 sm:block"
          size="sm"
          :disabled="!canReply"
          aria-label="Status da conversa"
          @update:model-value="setStatus"
        />
        <UTooltip text="Adiar por uma hora">
          <UButton
            icon="i-lucide-alarm-clock"
            color="neutral"
            variant="ghost"
            :disabled="!canReply"
            aria-label="Adiar conversa"
            @click="setStatus('SNOOZED')"
          />
        </UTooltip>
        <UTooltip :text="contextOpen ? 'Fechar contexto do contato' : 'Abrir contexto do contato'">
          <UButton
            icon="i-lucide-user"
            :color="contextOpen ? 'primary' : 'neutral'"
            :variant="contextOpen ? 'soft' : 'ghost'"
            :aria-label="contextOpen ? 'Fechar contexto do contato' : 'Abrir contexto do contato'"
            :aria-pressed="contextOpen"
            data-testid="communication-context-toggle"
            @click="emit('toggleContext')"
          />
        </UTooltip>
      </template>
    </UDashboardNavbar>

    <div class="flex min-w-0 items-center justify-between gap-3 border-b border-default px-4 py-2 text-xs text-muted sm:px-6">
      <div class="flex min-w-0 items-center gap-1.5">
        <span v-if="communicationContactLabel(conversation)" class="truncate">
          {{ communicationContactLabel(conversation) }}
        </span>
        <span v-if="communicationContactLabel(conversation)" aria-hidden="true">·</span>
        <span class="shrink-0">{{ inbox?.name || `Inbox #${conversation.inbox_id}` }}</span>
      </div>
      <span v-if="chatPresenceLabel" class="flex items-center gap-1.5 font-medium text-primary" data-testid="communication-chat-presence">
        <span class="size-1.5 animate-pulse rounded-full bg-primary" />
        {{ chatPresenceLabel }}
      </span>
      <span v-else-if="signals?.contact?.available" class="flex items-center gap-1.5 text-success" data-testid="communication-contact-online">
        <span class="size-1.5 rounded-full bg-success" />
        online
      </span>
      <span v-else-if="signals?.contact?.last_seen">
        visto por último em {{ formatCommunicationDate(signals.contact.last_seen) }}
      </span>
      <span v-else-if="conversation.snoozed_until">
        Adiada até {{ formatCommunicationDate(conversation.snoozed_until) }}
      </span>
      <span v-else-if="conversation.contact?.address_masked">
        {{ conversation.contact.address_masked }}
      </span>
    </div>

    <div
      ref="messagesContainer"
      class="min-h-0 flex-1 overflow-y-auto bg-elevated/20 p-4 sm:p-6"
    >
      <div v-if="loading && !conversation.messages?.length" class="space-y-4">
        <USkeleton class="h-20 w-2/3" />
        <USkeleton class="ml-auto h-24 w-3/4" />
        <USkeleton class="h-16 w-1/2" />
      </div>

      <div
        v-else-if="!conversation.messages?.length"
        class="flex h-full min-h-56 flex-col items-center justify-center gap-3 text-center"
      >
        <UIcon name="i-lucide-messages-square" class="size-12 text-dimmed" />
        <p class="text-sm text-muted">
          A timeline ainda não possui mensagens.
        </p>
      </div>

      <div v-else class="space-y-3.5 sm:space-y-4">
        <article
          v-for="message in conversation.messages"
          :key="message.id"
          class="group/message flex scroll-m-8 transition"
          :class="message.direction === 'OUTBOUND' ? 'justify-end' : 'justify-start'"
          :data-message-id="message.id"
        >
          <div class="min-w-0 w-fit max-w-[92%] sm:max-w-[78%] lg:max-w-[72%]">
            <div
              data-testid="communication-message-bubble"
              class="relative isolate inline-block w-fit max-w-full rounded-2xl px-3 py-2 shadow-xs ring-1 ring-inset transition sm:px-3.5 sm:py-2.5"
              :class="[
                message.direction === 'OUTBOUND'
                  ? 'rounded-br-md bg-primary text-inverted ring-primary/30'
                  : message.direction === 'INTERNAL'
                    ? 'rounded-bl-md bg-warning/10 text-highlighted ring-warning/40'
                    : 'rounded-bl-md bg-default text-highlighted ring-default',
                highlightedMessageId === message.id ? 'ring-2 ring-primary ring-offset-2 ring-offset-default' : ''
              ]"
            >
              <span
                aria-hidden="true"
                class="pointer-events-none absolute bottom-px size-2.5 rotate-45 border-b border-l"
                :class="message.direction === 'OUTBOUND'
                  ? '-right-1 border-primary/30 bg-primary'
                  : message.direction === 'INTERNAL'
                    ? '-left-1 border-warning/40 bg-warning/10'
                    : '-left-1 border-default bg-default'"
              />

              <div class="relative mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold leading-none opacity-80">
                <UIcon
                  v-if="message.direction === 'INTERNAL'"
                  name="i-lucide-sticky-note"
                  class="size-3.5"
                />
                <UIcon
                  v-else-if="message.source === 'FISCAL_AUTOMATION'"
                  name="i-lucide-bot"
                  class="size-3.5"
                />
                <UIcon
                  v-else
                  :name="message.direction === 'OUTBOUND' ? 'i-lucide-send' : 'i-lucide-message-circle-reply'"
                  class="size-3.5"
                />
                <span>
                  {{ message.direction === 'INTERNAL'
                    ? 'Nota interna'
                    : message.source === 'FISCAL_AUTOMATION'
                      ? 'Automação fiscal'
                      : message.direction === 'OUTBOUND' ? 'Enviada · WhatsApp' : 'Recebida · WhatsApp' }}
                </span>
              </div>

              <button
                v-if="quotedMessage(message)"
                type="button"
                class="relative mb-2 block w-full rounded-lg border-l-2 border-current bg-elevated/30 px-2.5 py-2 text-left text-xs opacity-80 transition-opacity hover:opacity-100 focus-visible:opacity-100"
                title="Ir para a mensagem citada"
                @click="scrollToMessage(quotedMessage(message)!.id)"
              >
                <p class="line-clamp-2">
                  {{ communicationMessageSummary(quotedMessage(message)) }}
                </p>
              </button>

              <CommunicationMessageContent
                class="relative"
                :message="message"
                :can-reply="canReply && outboundOperational"
                :action-loading="actionLoadingId === message.id"
                @download="(target, attachmentId, filename) => emit('download', target, attachmentId, filename)"
                @vote="(target, options) => emit('vote', target, options)"
                @receipt="(target, receipt) => emit('receipt', target, receipt)"
                @recover="(target, operation) => emit('recover', target, operation)"
              />

              <div v-if="message.metadata?.reactions?.length" class="relative mt-2 flex flex-wrap gap-1">
                <UButton
                  v-for="(emoji, index) in message.metadata.reactions"
                  :key="`${emoji}-${index}`"
                  :label="emoji"
                  color="neutral"
                  variant="soft"
                  size="xs"
                  :disabled="!canReply || !outboundOperational"
                  aria-label="Reagir com o mesmo emoji"
                  @click="emit('react', message, emoji)"
                />
              </div>

              <div
                data-testid="communication-message-meta"
                class="relative mt-1.5 flex min-h-6 items-end justify-end gap-1 text-[10px] leading-none opacity-75"
                aria-label="Metadados da mensagem"
              >
                <div
                  v-if="canReply && outboundOperational"
                  class="mr-auto flex items-center gap-0.5 opacity-100 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover/message:opacity-100 group-focus-within/message:opacity-100"
                  data-testid="communication-message-actions"
                >
                  <UPopover v-if="isRemoteMessage(message)">
                    <UButton
                      icon="i-lucide-smile-plus"
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      :class="message.direction === 'OUTBOUND' ? 'text-inverted hover:bg-default/20' : ''"
                      :disabled="actionLoadingId === message.id"
                      aria-label="Reagir à mensagem"
                    />
                    <template #content>
                      <div class="grid w-64 grid-cols-8 gap-1 p-2" aria-label="Escolher reação">
                        <UButton
                          v-for="emoji in COMMUNICATION_REACTION_EMOJIS"
                          :key="emoji"
                          :label="emoji"
                          color="neutral"
                          variant="ghost"
                          size="sm"
                          :aria-label="`Reagir com ${emoji}`"
                          @click="emit('react', message, emoji)"
                        />
                      </div>
                    </template>
                  </UPopover>
                  <UDropdownMenu :items="messageActionItems(message)">
                    <UButton
                      icon="i-lucide-ellipsis"
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      :class="message.direction === 'OUTBOUND' ? 'text-inverted hover:bg-default/20' : ''"
                      :loading="actionLoadingId === message.id"
                      aria-label="Ações da mensagem"
                    />
                  </UDropdownMenu>
                </div>
                <span v-if="message.metadata?.edited_at && !message.metadata.revoked">editada ·</span>
                <time :datetime="message.occurred_at || undefined">
                  {{ formatCommunicationDate(message.occurred_at) }}
                </time>
                <template v-if="message.direction === 'OUTBOUND'">
                  <UIcon :name="COMMUNICATION_MESSAGE_STATUS[message.status].icon" class="size-3.5" />
                  <span class="sr-only">{{ COMMUNICATION_MESSAGE_STATUS[message.status].label }}</span>
                </template>
              </div>
            </div>
          </div>
        </article>
      </div>
    </div>

    <CommunicationComposer
      :can-reply="canReply"
      :operational="operational"
      :outbound-operational="outboundOperational"
      :sending="sending"
      :canned-responses="cannedResponses"
      :reply-to="replyTo"
      @send="(payload, acknowledge) => emit('send', payload, acknowledge)"
      @presence="presence => emit('presence', presence)"
      @cancel-reply="replyTo = null"
    />

    <UModal
      :open="editTarget !== null"
      title="Editar mensagem"
      description="A alteração será enviada ao WhatsApp e aplicada após a confirmação do gateway."
      @update:open="value => { if (!value) editTarget = null }"
    >
      <template #body>
        <UFormField label="Novo texto" required>
          <UTextarea
            v-model="editDraft"
            :rows="5"
            autoresize
            class="w-full"
          />
        </UFormField>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="ghost"
            @click="closeEdit"
          />
          <UButton
            label="Enviar edição"
            icon="i-lucide-pencil"
            :loading="actionLoadingId === editTarget?.id"
            :disabled="!editDraft.trim() || editDraft.trim() === editTarget?.body?.trim()"
            @click="submitEdit"
          />
        </div>
      </template>
    </UModal>

    <UModal
      :open="revokeTarget !== null"
      title="Apagar mensagem para todos?"
      description="A revogação depende da janela e das regras do WhatsApp. O histórico auditável local será preservado."
      @update:open="value => { if (!value) revokeTarget = null }"
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="ghost"
            @click="closeRevoke"
          />
          <UButton
            label="Apagar para todos"
            icon="i-lucide-trash-2"
            color="error"
            :loading="actionLoadingId === revokeTarget?.id"
            @click="confirmRevoke"
          />
        </div>
      </template>
    </UModal>
  </UDashboardPanel>
</template>
