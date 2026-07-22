import { createSharedComposable, useDebounceFn } from '@vueuse/core'
import type { OfficeMember } from '~/types/api'
import type {
  CommunicationAutomationMeta,
  CommunicationAutomationPolicy,
  CommunicationCannedResponse,
  CommunicationChatPresence,
  CommunicationChatPresenceSignal,
  CommunicationContactPresenceSignal,
  CommunicationComposerPayload,
  CommunicationConversation,
  CommunicationConversationSignals,
  CommunicationConversationStatus,
  CommunicationEvent,
  CommunicationFeatureMeta,
  CommunicationInbox,
  CommunicationLabel,
  CommunicationPairingState,
  CommunicationRecipientConfiguration,
  CommunicationRecipientMode,
  CommunicationRealtimeEvent
} from '~/types/communication'
import type { WorkDepartment } from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import {
  communicationSignalFromEvent,
  isCommunicationEphemeralEvent,
  latestCommunicationCursor,
  mergeCommunicationConversations,
  mergeCommunicationEvents,
  mergeCommunicationMessages,
  normalizeCommunicationCursor
} from '~/utils/communication'
import {
  canManageCommunication as userCanManageCommunication,
  canReplyCommunication as userCanReplyCommunication,
  canViewCommunication as userCanViewCommunication
} from '~/utils/permissions'
import type {
  CommunicationConversationFilters,
  CommunicationPolicyBody
} from './api/createCommunicationApi'

const EMPTY_FEATURE_META: CommunicationFeatureMeta = {
  global_enabled: false,
  gateway_enabled: false,
  office_enabled: false
}

const EMPTY_AUTOMATION_META: CommunicationAutomationMeta = {
  supported_scopes: [],
  inboxes: [],
  office_enabled: false,
  global_enabled: false
}

const _useCommunicationWorkspace = () => {
  const api = useApi()
  const toast = useToast()
  const { me, sessionEpoch } = useDashboard()
  const realtime = useNuxtApp().$communicationRealtime

  const inboxes = ref<CommunicationInbox[]>([])
  const featureMeta = ref<CommunicationFeatureMeta>({ ...EMPTY_FEATURE_META })
  const conversations = ref<CommunicationConversation[]>([])
  const selectedConversationId = ref<number | null>(null)
  const labels = ref<CommunicationLabel[]>([])
  const cannedResponses = ref<CommunicationCannedResponse[]>([])
  const events = ref<CommunicationEvent[]>([])
  const cursor = ref(0)
  const policies = ref<CommunicationAutomationPolicy[]>([])
  const automationMeta = ref<CommunicationAutomationMeta>({ ...EMPTY_AUTOMATION_META })
  const officeMembers = ref<OfficeMember[]>([])
  const departments = ref<WorkDepartment[]>([])
  const chatPresenceByConversation = ref<Record<number, CommunicationChatPresenceSignal>>({})
  const contactPresenceByConversation = ref<Record<number, CommunicationContactPresenceSignal>>({})

  const loading = ref(false)
  const conversationsLoading = ref(false)
  const detailLoading = ref(false)
  const sending = ref(false)
  const syncing = ref(false)
  const adminLoading = ref(false)
  const messageActionLoadingId = ref<number | null>(null)
  const error = ref<string | null>(null)
  const syncError = ref<string | null>(null)
  const initialized = ref(false)

  const search = ref('')
  const inboxFilter = ref<number | null>(null)
  const statusFilter = ref<CommunicationConversationStatus | null>('OPEN')
  const assigneeFilter = ref<number | null>(null)
  const departmentFilter = ref<number | null>(null)
  const unassignedOnly = ref(false)

  const canView = computed(() => userCanViewCommunication(me.value))
  const canReply = computed(() => userCanReplyCommunication(me.value))
  const canManage = computed(() => userCanManageCommunication(me.value))
  const selectedConversation = computed(() =>
    conversations.value.find(item => item.id === selectedConversationId.value) ?? null)
  const selectedInbox = computed(() =>
    inboxes.value.find(item => item.id === selectedConversation.value?.inbox_id) ?? null)
  const selectedSignals = computed<CommunicationConversationSignals>(() => {
    const conversationId = selectedConversationId.value
    if (conversationId === null) return {}
    return {
      chat: chatPresenceByConversation.value[conversationId] ?? null,
      contact: contactPresenceByConversation.value[conversationId] ?? null
    }
  })
  const communicationOperational = computed(() => Boolean(
    featureMeta.value.global_enabled
    && featureMeta.value.gateway_enabled
    && featureMeta.value.office_enabled
    && selectedInbox.value?.is_enabled
    && selectedInbox.value.status !== 'REVOKED'
  ))
  const outboundOperational = computed(() =>
    communicationOperational.value && selectedInbox.value?.status === 'CONNECTED')

  const subscriptions = new Map<number, () => void>()
  const signalTimers = new Map<string, ReturnType<typeof setTimeout>>()
  const presenceSubscriptions = new Set<number>()
  let officeSubscription: (() => void) | null = null
  let synchronizeAgain = false
  let lastPresenceState: CommunicationChatPresence | null = null
  let lastPresenceSentAt = 0

  function listFilters(): CommunicationConversationFilters {
    return {
      q: search.value || undefined,
      inbox_id: inboxFilter.value || undefined,
      status: statusFilter.value || undefined,
      assignee_membership_id: assigneeFilter.value || undefined,
      work_department_id: departmentFilter.value || undefined,
      unassigned: unassignedOnly.value || undefined,
      per_page: 100
    }
  }

  async function loadInboxes(): Promise<void> {
    const response = await api.communication.inboxes.list()
    inboxes.value = response.data
    featureMeta.value = response.meta
    departments.value = response.meta.departments ?? departments.value
    reconcileSubscriptions()
  }

  async function loadConversations(options?: { silent?: boolean }): Promise<void> {
    const epoch = sessionEpoch.value
    conversationsLoading.value = true
    try {
      const response = await api.communication.conversations.list(listFilters())
      if (epoch !== sessionEpoch.value) return
      const detailed = selectedConversation.value
      conversations.value = mergeCommunicationConversations(
        detailed ? [detailed] : [],
        response.data
      )
      if (selectedConversationId.value
        && !conversations.value.some(item => item.id === selectedConversationId.value)) {
        selectedConversationId.value = null
      }
    } catch (caught) {
      if (!options?.silent) {
        error.value = apiErrorMessage(caught, 'Falha ao carregar conversas.')
        toast.add({ title: error.value, color: 'error' })
      }
      throw caught
    } finally {
      if (epoch === sessionEpoch.value) conversationsLoading.value = false
    }
  }

  async function loadCatalog(): Promise<void> {
    const [labelsResponse, cannedResponse] = await Promise.all([
      api.communication.catalog.labels(),
      api.communication.catalog.cannedResponses()
    ])
    labels.value = labelsResponse.data
    cannedResponses.value = cannedResponse.data
  }

  async function refreshConversationDetail(
    id: number,
    options: { silent?: boolean } = {}
  ): Promise<boolean> {
    const cached = conversations.value.find(item => item.id === id)
    const silent = options.silent === true
      || Boolean(cached?.messages && cached.messages.length > 0)
    if (!silent) {
      detailLoading.value = true
    }
    try {
      const response = await api.communication.conversations.get(id)
      conversations.value = mergeCommunicationConversations(conversations.value, [response.data])
      void ensurePresenceSubscription(id)
      return true
    } catch (caught) {
      const message = apiErrorMessage(caught, 'Falha ao abrir a conversa.')
      toast.add({ title: message, color: 'error' })
      return false
    } finally {
      if (!silent) {
        detailLoading.value = false
      }
    }
  }

  async function selectConversation(id: number | null): Promise<boolean> {
    selectedConversationId.value = id
    if (id === null) return true
    const ok = await refreshConversationDetail(id)
    if (!ok) {
      selectedConversationId.value = null
    }
    return ok
  }

  function clearSignal(kind: 'chat' | 'contact', conversationId: number): void {
    const key = `${kind}:${conversationId}`
    const timer = signalTimers.get(key)
    if (timer) clearTimeout(timer)
    signalTimers.delete(key)
    if (kind === 'chat') {
      const { [conversationId]: _removed, ...next } = chatPresenceByConversation.value
      chatPresenceByConversation.value = next
      return
    }
    const { [conversationId]: _removed, ...next } = contactPresenceByConversation.value
    contactPresenceByConversation.value = next
  }

  function storeSignal(signal: CommunicationChatPresenceSignal | CommunicationContactPresenceSignal): void {
    const kind = signal.kind
    const conversationId = signal.conversation_id
    clearSignal(kind, conversationId)
    if (kind === 'chat') {
      chatPresenceByConversation.value = {
        ...chatPresenceByConversation.value,
        [conversationId]: signal
      }
    } else {
      contactPresenceByConversation.value = {
        ...contactPresenceByConversation.value,
        [conversationId]: signal
      }
    }
    if (!import.meta.client) return
    const delay = Math.max(0, signal.expires_at - Date.now())
    signalTimers.set(`${kind}:${conversationId}`, setTimeout(() => {
      clearSignal(kind, conversationId)
    }, delay))
  }

  function applyEphemeralSignals(incoming: CommunicationEvent[]): void {
    for (const event of incoming) {
      if (!isCommunicationEphemeralEvent(event) || event.conversation_id == null) continue
      if (event.type === 'CHAT_PRESENCE_CHANGED' && event.payload.presence === 'PAUSED') {
        clearSignal('chat', event.conversation_id)
        continue
      }
      const signal = communicationSignalFromEvent(event)
      if (signal) storeSignal(signal)
    }
  }

  async function hydrateFromEvents(incoming: CommunicationEvent[]): Promise<void> {
    applyEphemeralSignals(incoming)
    const durable = incoming.filter(event => !isCommunicationEphemeralEvent(event))
    if (!durable.length) return
    const selectedId = selectedConversationId.value
    const selected = selectedConversation.value
    const selectedInboxId = selected?.inbox_id ?? null
    const touchesSelected = selectedId !== null && durable.some(event =>
      event.conversation_id === selectedId
      || (event.conversation_id == null
        && selectedInboxId !== null
        && event.inbox_id === selectedInboxId)
    )
    // Recarrega o detalhe antes da lista para o merge preservar a seleção
    // mesmo quando o filtro (ex.: OPEN) excluiria a conversa.
    if (touchesSelected && selectedId !== null) {
      await refreshConversationDetail(selectedId, { silent: true })
    }
    await loadConversations({ silent: true }).catch(() => undefined)
    if (selectedId !== null && !conversations.value.some(item => item.id === selectedId)) {
      await refreshConversationDetail(selectedId, { silent: true })
    }
    await loadInboxes().catch(() => undefined)
  }

  async function synchronize(): Promise<void> {
    if (!canView.value) return
    if (syncing.value) {
      synchronizeAgain = true
      return
    }
    syncing.value = true
    syncError.value = null
    const received: CommunicationEvent[] = []
    try {
      do {
        synchronizeAgain = false
        let hasMore = true
        let after = cursor.value
        while (hasMore) {
          const response = await api.communication.events.sync(after)
          received.push(...response.data)
          events.value = mergeCommunicationEvents(events.value, response.data).slice(-500)
          const next = Math.max(
            response.meta.next_cursor,
            latestCommunicationCursor(response.data, after)
          )
          if (next <= after) break
          after = next
          cursor.value = Math.max(cursor.value, next)
          hasMore = response.meta.has_more
        }
        await hydrateFromEvents(received.splice(0))
      } while (synchronizeAgain)
    } catch (caught) {
      syncError.value = apiErrorMessage(caught, 'Sincronização temporariamente indisponível.')
    } finally {
      syncing.value = false
    }
  }

  function onRealtimeEvent(event: CommunicationRealtimeEvent): void {
    const nextCursor = normalizeCommunicationCursor(event.cursor)
    if (nextCursor === null || nextCursor <= cursor.value) return
    const normalized: CommunicationRealtimeEvent = { ...event, cursor: nextCursor }
    events.value = mergeCommunicationEvents(events.value, [normalized]).slice(-500)
    // Não avança o cursor aqui: o sync usa `after` exclusivo e precisa buscar o evento.
    void synchronize()
  }

  function reconcileSubscriptions(options?: { force?: boolean }): void {
    const force = options?.force === true
    const visibleIds = new Set(inboxes.value.map(inbox => inbox.id))
    for (const [inboxId, unsubscribe] of subscriptions) {
      if (!force && visibleIds.has(inboxId)) continue
      unsubscribe()
      subscriptions.delete(inboxId)
    }
    if (force && officeSubscription !== null) {
      officeSubscription()
      officeSubscription = null
    }
    if (!realtime.enabled) return
    for (const inboxId of visibleIds) {
      if (subscriptions.has(inboxId)) continue
      subscriptions.set(inboxId, realtime.subscribeInbox(inboxId, onRealtimeEvent))
    }
    const officeId = me.value?.current_office?.id ?? me.value?.office?.id
    if (canManage.value && officeId && officeSubscription === null) {
      officeSubscription = realtime.subscribeOffice(officeId, onRealtimeEvent)
    }
  }

  async function initialize(): Promise<void> {
    if (!canView.value || loading.value) return
    loading.value = true
    error.value = null
    try {
      await Promise.all([loadInboxes(), loadCatalog()])
      await loadConversations({ silent: true })
      await synchronize()
      initialized.value = true
    } catch (caught) {
      error.value = apiErrorMessage(caught, 'Não foi possível abrir o atendimento.')
      toast.add({ title: error.value, color: 'error' })
    } finally {
      loading.value = false
    }
  }

  async function sendMessage(input: CommunicationComposerPayload): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation || !canReply.value || sending.value) return false
    sending.value = true
    try {
      const response = await api.communication.conversations.send(conversation.id, {
        body: input.body,
        internal_note: input.internalNote,
        reply_to_message_id: input.replyToMessageId,
        idempotency_key: input.internalNote
          ? undefined
          : `web-${Date.now()}-${crypto.randomUUID()}`,
        file: input.file,
        kind: input.internalNote ? undefined : input.kind,
        ptt: input.internalNote ? undefined : input.ptt
      })
      conversation.messages = mergeCommunicationMessages(conversation.messages ?? [], [response.data])
      await Promise.all([
        refreshConversationDetail(conversation.id, { silent: true }),
        loadConversations({ silent: true })
      ])
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao enviar mensagem.'), color: 'error' })
      return false
    } finally {
      sending.value = false
    }
  }

  async function queueMessageAction(
    messageId: number,
    action: () => Promise<unknown>,
    successTitle: string
  ): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation || !canReply.value || !outboundOperational.value || messageActionLoadingId.value !== null) {
      return false
    }
    messageActionLoadingId.value = messageId
    try {
      await action()
      toast.add({
        title: successTitle,
        description: 'A atualização aparecerá quando o WhatsApp confirmar a ação.',
        color: 'success'
      })
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao enfileirar a ação.'), color: 'error' })
      return false
    } finally {
      messageActionLoadingId.value = null
    }
  }

  async function editMessage(messageId: number, text: string): Promise<boolean> {
    const conversation = selectedConversation.value
    const normalized = text.trim()
    if (!conversation || !normalized) return false
    return queueMessageAction(
      messageId,
      () => api.communication.conversations.editMessage(conversation.id, messageId, normalized),
      'Edição enfileirada'
    )
  }

  async function revokeMessage(messageId: number): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation) return false
    return queueMessageAction(
      messageId,
      () => api.communication.conversations.revokeMessage(conversation.id, messageId),
      'Revogação enfileirada'
    )
  }

  async function reactMessage(messageId: number, emoji: string | null): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation) return false
    return queueMessageAction(
      messageId,
      () => api.communication.conversations.reactMessage(conversation.id, messageId, emoji),
      emoji ? 'Reação enfileirada' : 'Remoção da reação enfileirada'
    )
  }

  async function votePoll(messageId: number, optionNames: string[]): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation || !optionNames.length) return false
    return queueMessageAction(
      messageId,
      () => api.communication.conversations.votePoll(conversation.id, messageId, optionNames),
      'Voto enfileirado'
    )
  }

  async function sendReceipt(messageId: number, receipt: 'READ' | 'PLAYED'): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation) return false
    return queueMessageAction(
      messageId,
      () => api.communication.conversations.receipt(conversation.id, messageId, receipt),
      receipt === 'PLAYED' ? 'Confirmação de reprodução enfileirada' : 'Confirmação de leitura enfileirada'
    )
  }

  async function recoverMessage(
    messageId: number,
    operation: 'UNAVAILABLE' | 'MEDIA_RETRY'
  ): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation) return false
    return queueMessageAction(
      messageId,
      () => api.communication.conversations.recoverMessage(conversation.id, messageId, operation),
      operation === 'MEDIA_RETRY' ? 'Recuperação da mídia enfileirada' : 'Solicitação da mensagem enfileirada'
    )
  }

  async function ensurePresenceSubscription(conversationId: number): Promise<void> {
    if (!canReply.value || !outboundOperational.value || presenceSubscriptions.has(conversationId)) return
    presenceSubscriptions.add(conversationId)
    try {
      await api.communication.conversations.subscribePresence(conversationId)
    } catch {
      presenceSubscriptions.delete(conversationId)
    }
  }

  async function setChatPresence(presence: CommunicationChatPresence): Promise<void> {
    const conversation = selectedConversation.value
    if (!conversation || !canReply.value || !outboundOperational.value) return
    const now = Date.now()
    const throttleWindow = presence === 'PAUSED' ? 1_000 : 10_000
    if (lastPresenceState === presence && now - lastPresenceSentAt < throttleWindow) return
    lastPresenceState = presence
    lastPresenceSentAt = now
    try {
      await api.communication.conversations.setPresence(
        conversation.id,
        presence,
        presence === 'RECORDING' ? 'AUDIO' : 'TEXT'
      )
    } catch {
      lastPresenceState = null
    }
  }

  async function setDisappearingTimer(seconds: 0 | 86400 | 604800 | 7776000): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation || !canReply.value || !outboundOperational.value) return false
    try {
      await api.communication.conversations.setDisappearing(conversation.id, seconds)
      toast.add({
        title: seconds === 0 ? 'Mensagens temporárias desativadas' : 'Temporizador enfileirado',
        color: 'success'
      })
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao alterar mensagens temporárias.'), color: 'error' })
      return false
    }
  }

  async function updateConversation(
    patch: Partial<Pick<CommunicationConversation,
      'status' | 'assignee_membership_id' | 'work_department_id' | 'priority' | 'snoozed_until'>>
  ): Promise<boolean> {
    const conversation = selectedConversation.value
    if (!conversation || !canReply.value) return false
    try {
      const response = await api.communication.conversations.update(conversation.id, {
        lock_version: conversation.lock_version,
        ...patch
      })
      conversations.value = mergeCommunicationConversations(conversations.value, [response.data])
      return true
    } catch (caught) {
      if ((caught as { data?: { code?: string } })?.data?.code === 'version_conflict') {
        await selectConversation(conversation.id)
      }
      toast.add({ title: apiErrorMessage(caught, 'Falha ao atualizar conversa.'), color: 'error' })
      return false
    }
  }

  async function toggleLabel(label: CommunicationLabel): Promise<void> {
    const conversation = selectedConversation.value
    if (!conversation || !canReply.value) return
    const assigned = conversation.labels?.some(item => item.id === label.id) ?? false
    try {
      if (assigned) {
        await api.communication.conversations.removeLabel(conversation.id, label.id)
      } else {
        await api.communication.conversations.addLabel(conversation.id, label.id)
      }
      await selectConversation(conversation.id)
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao atualizar marcador.'), color: 'error' })
    }
  }

  async function loadAdministration(): Promise<void> {
    if (!canManage.value || adminLoading.value) return
    adminLoading.value = true
    try {
      const [automation, members, departmentResponse] = await Promise.all([
        api.communication.automation.list(),
        api.office.members.list(),
        api.work.departments.list({ per_page: 100, is_active: true })
      ])
      policies.value = automation.data
      automationMeta.value = automation.meta
      officeMembers.value = members.data.filter(member => member.is_active)
      departments.value = departmentResponse.data
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao carregar administração.'), color: 'error' })
    } finally {
      adminLoading.value = false
    }
  }

  async function createInbox(body: {
    name: string
    is_enabled?: boolean
    is_default?: boolean
    work_department_id?: number | null
  }): Promise<CommunicationInbox | null> {
    try {
      const response = await api.communication.inboxes.create(body)
      await loadInboxes()
      return response.data
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao criar inbox.'), color: 'error' })
      return null
    }
  }

  async function updateInbox(
    inbox: CommunicationInbox,
    patch: Partial<Pick<CommunicationInbox,
      'name' | 'is_enabled' | 'is_default' | 'work_department_id'>>
  ): Promise<boolean> {
    try {
      await api.communication.inboxes.update(inbox.id, {
        ...patch,
        lock_version: inbox.lock_version
      })
      await loadInboxes()
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao atualizar inbox.'), color: 'error' })
      return false
    }
  }

  async function replaceInboxMembers(inboxId: number, membershipIds: number[]): Promise<boolean> {
    try {
      await api.communication.inboxes.replaceMembers(inboxId, membershipIds)
      await loadInboxes()
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao atualizar membros.'), color: 'error' })
      return false
    }
  }

  async function startPairing(inboxId: number): Promise<CommunicationPairingState | null> {
    try {
      await api.communication.inboxes.startPairing(inboxId)
      await loadInboxes()
      return await getPairing(inboxId)
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao iniciar pareamento.'), color: 'error' })
      return null
    }
  }

  async function getPairing(inboxId: number): Promise<CommunicationPairingState | null> {
    try {
      return (await api.communication.inboxes.pairing(inboxId)).data
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao consultar pareamento.'), color: 'error' })
      return null
    }
  }

  async function revokeInbox(inboxId: number): Promise<boolean> {
    try {
      await api.communication.inboxes.revoke(inboxId)
      await loadInboxes()
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao revogar sessão.'), color: 'error' })
      return false
    }
  }

  async function updateOfficeEnabled(enabled: boolean): Promise<boolean> {
    try {
      await api.communication.inboxes.updateOfficeSettings(enabled)
      await loadInboxes()
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao alterar o switch do escritório.'), color: 'error' })
      return false
    }
  }

  async function savePolicy(body: CommunicationPolicyBody): Promise<boolean> {
    try {
      const response = await api.communication.automation.upsert(body)
      policies.value = [
        ...policies.value.filter(item => item.id !== response.data.id),
        response.data
      ].sort((a, b) => `${a.module_key}:${a.submodule_key}`.localeCompare(`${b.module_key}:${b.submodule_key}`))
      return true
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao salvar política.'), color: 'error' })
      return false
    }
  }

  async function loadRecipients(
    clientId: number,
    moduleKey: string,
    submoduleKey: string
  ): Promise<CommunicationRecipientConfiguration | null> {
    try {
      return (await api.communication.automation.recipients(clientId, moduleKey, submoduleKey)).data
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao carregar destinatários.'), color: 'error' })
      return null
    }
  }

  async function saveRecipients(
    configuration: CommunicationRecipientConfiguration,
    moduleKey: string,
    submoduleKey: string,
    recipientMode: CommunicationRecipientMode,
    identityIds: number[]
  ): Promise<CommunicationRecipientConfiguration | null> {
    try {
      return (await api.communication.automation.updateRecipients(configuration.client_id, {
        module_key: moduleKey,
        submodule_key: submoduleKey,
        recipient_mode: recipientMode,
        identity_ids: identityIds,
        lock_version: configuration.lock_version
      })).data
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao salvar destinatários.'), color: 'error' })
      return null
    }
  }

  const reloadForFilters = useDebounceFn(() => {
    if (initialized.value) void loadConversations()
  }, 250)

  let pollTimer: ReturnType<typeof setInterval> | null = null

  function stopCursorPoll(): void {
    if (pollTimer === null) return
    clearInterval(pollTimer)
    pollTimer = null
  }

  function ensureCursorPoll(): void {
    if (!initialized.value || !canView.value) {
      stopCursorPoll()
      return
    }
    if (realtime.channelsReady.value) {
      stopCursorPoll()
      return
    }
    if (pollTimer !== null) return
    pollTimer = setInterval(() => {
      if (!initialized.value || !canView.value || realtime.channelsReady.value) {
        stopCursorPoll()
        return
      }
      void synchronize()
    }, 5_000)
  }

  watch([search, inboxFilter, statusFilter, assigneeFilter, departmentFilter, unassignedOnly], reloadForFilters)
  watch(realtime.state, (next, previous) => {
    // Transporte voltou: re-assina canais (subscriptions Map podia estar stale).
    if (previous === 'unavailable' && (next === 'connecting' || next === 'connected')) {
      reconcileSubscriptions({ force: true })
    }
    if (next === 'connected' && previous !== 'connected') {
      void synchronize()
    }
    ensureCursorPoll()
  })
  watch(() => realtime.channelsReady.value, () => {
    ensureCursorPoll()
  })
  watch(canView, (allowed) => {
    if (allowed && !initialized.value && !loading.value) void initialize()
    ensureCursorPoll()
  }, { immediate: true })
  watch(initialized, () => {
    ensureCursorPoll()
  })
  watch(sessionEpoch, () => {
    dispose()
    inboxes.value = []
    conversations.value = []
    selectedConversationId.value = null
    labels.value = []
    cannedResponses.value = []
    events.value = []
    chatPresenceByConversation.value = {}
    contactPresenceByConversation.value = {}
    cursor.value = 0
    policies.value = []
    featureMeta.value = { ...EMPTY_FEATURE_META }
    initialized.value = false
    if (canView.value) void initialize()
  })

  function dispose(): void {
    stopCursorPoll()
    for (const unsubscribe of subscriptions.values()) unsubscribe()
    subscriptions.clear()
    for (const timer of signalTimers.values()) clearTimeout(timer)
    signalTimers.clear()
    presenceSubscriptions.clear()
    chatPresenceByConversation.value = {}
    contactPresenceByConversation.value = {}
    officeSubscription?.()
    officeSubscription = null
    initialized.value = false
  }

  return {
    adminLoading: readonly(adminLoading),
    assigneeFilter,
    automationMeta,
    cannedResponses,
    canManage,
    canReply,
    canView,
    communicationOperational,
    conversations,
    conversationsLoading: readonly(conversationsLoading),
    createInbox,
    cursor: readonly(cursor),
    departments,
    departmentFilter,
    detailLoading: readonly(detailLoading),
    dispose,
    editMessage,
    error: readonly(error),
    events,
    featureMeta,
    getPairing,
    inboxes,
    inboxFilter,
    initialize,
    initialized: readonly(initialized),
    labels,
    loadAdministration,
    loadCatalog,
    loadRecipients,
    loading: readonly(loading),
    messageActionLoadingId: readonly(messageActionLoadingId),
    officeMembers,
    outboundOperational,
    policies,
    realtimeState: realtime.state,
    reactMessage,
    recoverMessage,
    replaceInboxMembers,
    revokeInbox,
    savePolicy,
    saveRecipients,
    search,
    selectedConversation,
    selectedConversationId: readonly(selectedConversationId),
    selectedInbox,
    selectedSignals,
    selectConversation,
    sendReceipt,
    sendMessage,
    sending: readonly(sending),
    startPairing,
    statusFilter,
    setChatPresence,
    setDisappearingTimer,
    syncError: readonly(syncError),
    syncing: readonly(syncing),
    synchronize,
    toggleLabel,
    unassignedOnly,
    updateConversation,
    updateInbox,
    updateOfficeEnabled,
    revokeMessage,
    votePoll
  }
}

export const useCommunicationWorkspace = createSharedComposable(_useCommunicationWorkspace)
