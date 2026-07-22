<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import type {
  CommunicationComposerPayload,
  CommunicationConversation,
  CommunicationConversationStatus,
  CommunicationMessage
} from '~/types/communication'
import { apiErrorMessage } from '~/utils/api-error'
import { COMMUNICATION_REALTIME_META } from '~/utils/communication'
import {
  COMMUNICATION_INDEX_PATH,
  communicationConversationPath,
  parseCommunicationConversationId
} from '~/utils/communication-routes'

const workspace = useCommunicationWorkspace()
const api = useApi()
const toast = useToast()
const download = useAuthenticatedDownload()
const route = useRoute()
const router = useRouter()
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')
const administrationOpen = ref(false)
const contextOpen = ref(false)
const purgeOpen = ref(false)
const purgeContactId = ref<number | null>(null)
const purging = ref(false)
const routeApplyEpoch = ref(0)

const routeConversationId = computed(() => parseCommunicationConversationId(route.params.id))

const mobileConversationOpen = computed({
  get: () => isMobile.value && workspace.selectedConversation.value !== null,
  set: (value: boolean) => {
    if (!value) void clearConversationSelection()
  }
})

const inboxItems = computed(() => [
  { label: 'Todas as inboxes', value: 0 },
  ...workspace.inboxes.value.map(inbox => ({ label: inbox.name, value: inbox.id }))
])
const statusItems = [
  { label: 'Todos os status', value: 'ALL' },
  { label: 'Abertas', value: 'OPEN' },
  { label: 'Pendentes', value: 'PENDING' },
  { label: 'Adiadas', value: 'SNOOZED' },
  { label: 'Resolvidas', value: 'RESOLVED' }
]
const statusSelection = computed({
  get: () => workspace.statusFilter.value || 'ALL',
  set: value => workspace.statusFilter.value = value === 'ALL'
    ? null
    : value as CommunicationConversationStatus
})
const inboxSelection = computed({
  get: () => workspace.inboxFilter.value || 0,
  set: (value: number) => workspace.inboxFilter.value = value || null
})

function openAdministration() {
  administrationOpen.value = true
}

function closePurge() {
  purgeOpen.value = false
}

async function syncRouteToSelection(id: number | null): Promise<void> {
  const target = id === null ? COMMUNICATION_INDEX_PATH : communicationConversationPath(id)
  if (route.path === target) return
  await router.push(target)
}

async function openConversation(id: number): Promise<void> {
  contextOpen.value = false
  const epoch = ++routeApplyEpoch.value
  const ok = await workspace.selectConversation(id)
  if (epoch !== routeApplyEpoch.value) return
  if (!ok) {
    await syncRouteToSelection(null)
    return
  }
  await syncRouteToSelection(id)
}

async function clearConversationSelection(): Promise<void> {
  contextOpen.value = false
  const epoch = ++routeApplyEpoch.value
  await workspace.selectConversation(null)
  if (epoch !== routeApplyEpoch.value) return
  await syncRouteToSelection(null)
}

async function selectConversation(conversation: CommunicationConversation) {
  await openConversation(conversation.id)
}

async function applyRouteConversation(id: number | null): Promise<void> {
  if (!workspace.canView.value || !workspace.initialized.value) return
  const epoch = ++routeApplyEpoch.value
  if (id === null) {
    if (workspace.selectedConversationId.value !== null) {
      await workspace.selectConversation(null)
    }
    return
  }
  if (workspace.selectedConversation.value?.id === id) return
  const ok = await workspace.selectConversation(id)
  if (epoch !== routeApplyEpoch.value) return
  if (!ok) await syncRouteToSelection(null)
}

function toggleContext() {
  contextOpen.value = !contextOpen.value
}

async function send(
  payload: CommunicationComposerPayload,
  acknowledge?: (ok: boolean) => void
) {
  const ok = await workspace.sendMessage(payload)
  acknowledge?.(ok)
}

async function editMessage(
  message: CommunicationMessage,
  text: string,
  acknowledge?: (ok: boolean) => void
) {
  const ok = await workspace.editMessage(message.id, text)
  acknowledge?.(ok)
}

function revokeMessage(message: CommunicationMessage) {
  void workspace.revokeMessage(message.id)
}

function reactMessage(message: CommunicationMessage, emoji: string | null) {
  void workspace.reactMessage(message.id, emoji)
}

function votePoll(message: CommunicationMessage, optionNames: string[]) {
  void workspace.votePoll(message.id, optionNames)
}

function sendReceipt(message: CommunicationMessage, receipt: 'READ' | 'PLAYED') {
  void workspace.sendReceipt(message.id, receipt)
}

function recoverMessage(
  message: CommunicationMessage,
  operation: 'UNAVAILABLE' | 'MEDIA_RETRY'
) {
  void workspace.recoverMessage(message.id, operation)
}

function updateConversation(patch: Record<string, unknown>) {
  void workspace.updateConversation(patch as Parameters<typeof workspace.updateConversation>[0])
}

async function downloadAttachment(
  _message: CommunicationMessage,
  attachmentId: number,
  filename: string
) {
  await download.download(
    api.communication.attachments.downloadUrl(attachmentId),
    filename
  )
}

async function exportContact(contactId: number) {
  await download.download(
    api.communication.contacts.exportUrl(contactId),
    `contato-${contactId}.json`
  )
}

function requestPurge(contactId: number) {
  purgeContactId.value = contactId
  purgeOpen.value = true
}

async function confirmPurge() {
  if (!purgeContactId.value) return
  purging.value = true
  try {
    await api.communication.contacts.purge(purgeContactId.value)
    toast.add({
      title: 'Dados de comunicação expurgados',
      description: 'Corpos e anexos foram removidos; o tombstone auditável foi preservado.',
      color: 'success'
    })
    purgeOpen.value = false
    contextOpen.value = false
    await clearConversationSelection()
    await workspace.initialize()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao expurgar os dados.'), color: 'error' })
  } finally {
    purging.value = false
  }
}

function selectRelative(delta: number) {
  const items = workspace.conversations.value
  if (!items.length) return
  const current = items.findIndex(item => item.id === workspace.selectedConversationId.value)
  const index = current < 0
    ? (delta > 0 ? 0 : items.length - 1)
    : Math.max(0, Math.min(items.length - 1, current + delta))
  const target = items[index]
  if (target) void openConversation(target.id)
}

defineShortcuts({
  arrowdown: () => selectRelative(1),
  arrowup: () => selectRelative(-1),
  escape: () => {
    if (contextOpen.value) contextOpen.value = false
    else if (isMobile.value) void clearConversationSelection()
  }
})

watch(
  () => [workspace.initialized.value, workspace.canView.value, routeConversationId.value] as const,
  ([initialized, canView, id]) => {
    if (!initialized || !canView) return
    void applyRouteConversation(id)
  },
  { immediate: true }
)

onMounted(() => void workspace.initialize())
onBeforeUnmount(() => workspace.dispose())
</script>

<template>
  <template v-if="workspace.canView.value">
    <UDashboardPanel
      id="communication-list"
      data-testid="communication-list-panel"
      :default-size="27"
      :min-size="22"
      :max-size="36"
      resizable
    >
      <UDashboardNavbar title="Atendimento">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #trailing>
          <UBadge
            :label="String(workspace.conversations.value.length)"
            variant="subtle"
          />
        </template>
        <template #right>
          <UTooltip :text="COMMUNICATION_REALTIME_META[workspace.realtimeState.value].label">
            <UButton
              :icon="COMMUNICATION_REALTIME_META[workspace.realtimeState.value].icon"
              :color="COMMUNICATION_REALTIME_META[workspace.realtimeState.value].color"
              variant="ghost"
              aria-label="Estado da atualização em tempo real"
              :loading="workspace.syncing.value"
              @click="workspace.synchronize"
            />
          </UTooltip>
          <UButton
            v-if="workspace.canManage.value"
            icon="i-lucide-settings-2"
            color="neutral"
            variant="ghost"
            aria-label="Administrar comunicação"
            @click="openAdministration"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar class="border-b border-default">
        <div class="flex w-full flex-col gap-2 p-2">
          <UInput
            v-model="workspace.search.value"
            icon="i-lucide-search"
            placeholder="Buscar contato, telefone ou mensagem"
            class="w-full"
          />
          <div class="grid grid-cols-2 gap-2">
            <USelectMenu
              v-model="inboxSelection"
              :items="inboxItems"
              value-key="value"
              placeholder="Inbox"
              class="w-full"
            />
            <USelectMenu
              v-model="statusSelection"
              :items="statusItems"
              value-key="value"
              class="w-full"
            />
          </div>
          <UCheckbox
            v-model="workspace.unassignedOnly.value"
            label="Somente sem responsável"
          />
        </div>
      </UDashboardToolbar>

      <UAlert
        v-if="workspace.error.value"
        :title="workspace.error.value"
        color="error"
        variant="subtle"
        class="m-3"
      />
      <UAlert
        v-else-if="workspace.syncError.value"
        :title="workspace.syncError.value"
        description="A lista permanece disponível; use o botão de sincronização para tentar novamente."
        color="warning"
        variant="subtle"
        class="m-3"
      />

      <CommunicationConversationList
        :conversations="workspace.conversations.value"
        :inboxes="workspace.inboxes.value"
        :selected-id="workspace.selectedConversationId.value"
        :loading="workspace.loading.value || workspace.conversationsLoading.value"
        @select="selectConversation"
      />
    </UDashboardPanel>

    <CommunicationTimelinePanel
      v-if="workspace.selectedConversation.value"
      class="hidden lg:flex"
      :conversation="workspace.selectedConversation.value"
      :inbox="workspace.selectedInbox.value"
      :signals="workspace.selectedSignals.value"
      :canned-responses="workspace.cannedResponses.value"
      :can-reply="workspace.canReply.value"
      :operational="workspace.communicationOperational.value"
      :outbound-operational="workspace.outboundOperational.value"
      :loading="workspace.detailLoading.value"
      :sending="workspace.sending.value"
      :action-loading-id="workspace.messageActionLoadingId.value"
      :context-open="contextOpen"
      @send="send"
      @update="updateConversation"
      @toggle-context="toggleContext"
      @download="downloadAttachment"
      @edit="editMessage"
      @revoke="revokeMessage"
      @react="reactMessage"
      @vote="votePoll"
      @receipt="sendReceipt"
      @recover="recoverMessage"
      @presence="workspace.setChatPresence"
    />

    <CommunicationContextPanel
      v-if="workspace.selectedConversation.value && contextOpen"
      class="hidden lg:flex"
      :conversation="workspace.selectedConversation.value"
      :inbox="workspace.selectedInbox.value"
      :labels="workspace.labels.value"
      :departments="workspace.departments.value"
      :can-reply="workspace.canReply.value"
      :can-manage="workspace.canManage.value"
      :outbound-operational="workspace.outboundOperational.value"
      :signals="workspace.selectedSignals.value"
      @close="contextOpen = false"
      @update="updateConversation"
      @toggle-label="workspace.toggleLabel"
      @export-contact="exportContact"
      @purge-contact="requestPurge"
      @set-disappearing="workspace.setDisappearingTimer"
    />

    <div
      v-if="!workspace.selectedConversation.value"
      class="hidden min-w-0 flex-1 flex-col items-center justify-center gap-4 lg:flex"
      data-testid="communication-empty-detail"
    >
      <UIcon
        name="i-lucide-message-square-dashed"
        class="size-24 text-dimmed"
      />
      <div class="text-center">
        <p class="font-medium text-highlighted">
          Selecione uma conversa
        </p>
        <p class="mt-1 text-sm text-muted">
          Use ↑ e ↓ para navegar pela fila.
        </p>
      </div>
    </div>

    <ClientOnly>
      <USlideover
        v-if="isMobile"
        v-model:open="mobileConversationOpen"
        data-testid="communication-mobile-timeline"
        :ui="{ content: 'w-screen max-w-none' }"
      >
        <template #content>
          <CommunicationTimelinePanel
            v-if="workspace.selectedConversation.value"
            mobile
            :conversation="workspace.selectedConversation.value"
            :inbox="workspace.selectedInbox.value"
            :signals="workspace.selectedSignals.value"
            :canned-responses="workspace.cannedResponses.value"
            :can-reply="workspace.canReply.value"
            :operational="workspace.communicationOperational.value"
            :outbound-operational="workspace.outboundOperational.value"
            :loading="workspace.detailLoading.value"
            :sending="workspace.sending.value"
            :action-loading-id="workspace.messageActionLoadingId.value"
            :context-open="contextOpen"
            @close="clearConversationSelection"
            @send="send"
            @update="updateConversation"
            @toggle-context="toggleContext"
            @download="downloadAttachment"
            @edit="editMessage"
            @revoke="revokeMessage"
            @react="reactMessage"
            @vote="votePoll"
            @receipt="sendReceipt"
            @recover="recoverMessage"
            @presence="workspace.setChatPresence"
          />
        </template>
      </USlideover>

      <USlideover
        v-if="isMobile"
        v-model:open="contextOpen"
        data-testid="communication-mobile-context"
        :ui="{ content: 'w-screen max-w-md' }"
      >
        <template #content>
          <CommunicationContextPanel
            v-if="workspace.selectedConversation.value"
            mobile
            :conversation="workspace.selectedConversation.value"
            :inbox="workspace.selectedInbox.value"
            :labels="workspace.labels.value"
            :departments="workspace.departments.value"
            :can-reply="workspace.canReply.value"
            :can-manage="workspace.canManage.value"
            :outbound-operational="workspace.outboundOperational.value"
            :signals="workspace.selectedSignals.value"
            @close="contextOpen = false"
            @update="updateConversation"
            @toggle-label="workspace.toggleLabel"
            @export-contact="exportContact"
            @purge-contact="requestPurge"
            @set-disappearing="workspace.setDisappearingTimer"
          />
        </template>
      </USlideover>
    </ClientOnly>

    <CommunicationAdministrationSlideover
      v-if="workspace.canManage.value"
      v-model:open="administrationOpen"
    />

    <UModal
      v-model:open="purgeOpen"
      title="Expurgar dados deste contato?"
      description="Esta ação remove definitivamente mensagens e anexos recuperáveis."
    >
      <template #body>
        <UAlert
          title="Apenas o tombstone sanitizado e o ledger de auditoria serão preservados."
          color="error"
          icon="i-lucide-triangle-alert"
          variant="subtle"
        />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="ghost"
            @click="closePurge"
          />
          <UButton
            label="Expurgar definitivamente"
            color="error"
            :loading="purging"
            @click="confirmPurge"
          />
        </div>
      </template>
    </UModal>
  </template>

  <UDashboardPanel v-else id="communication-forbidden">
    <UDashboardNavbar title="Atendimento">
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
    </UDashboardNavbar>
    <div class="flex flex-1 items-center justify-center p-6">
      <UAlert
        title="Acesso ao atendimento não autorizado"
        description="Solicite a permissão communication.view a um administrador do escritório."
        color="warning"
        icon="i-lucide-shield-x"
        variant="subtle"
        class="max-w-lg"
      />
    </div>
  </UDashboardPanel>
</template>
