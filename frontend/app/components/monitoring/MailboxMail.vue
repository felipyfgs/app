<script setup lang="ts">
/**
 * Detalhe canônico de mensagem (arquétipo InboxMail).
 * Corpo/anexos via download protegido — sem bytes embutidos.
 * Triagem: NEW / IN_REVIEW / RESOLVED apenas; não altera ciência oficial.
 */
import {
  MAILBOX_TRIAGE_SELECT_ITEMS,
  parseMailboxTriageStatus,
  type MailboxTriageStatus
} from '~/utils/mailbox-triage'

export interface MailboxMessageDetail {
  id: number
  client_id?: number | null
  subject_preview?: string | null
  sender_label?: string | null
  sender_code?: string | null
  triage_status?: string | null
  triage_note?: string | null
  severity_hint?: string | null
  received_at_official?: string | null
  created_at?: string | null
  due_at?: string | null
  official_read_indicator?: string | boolean | null
  official_read_observed_at?: string | null
  category_label?: string | null
  category_code?: string | null
  source?: string | null
  has_body?: boolean
  attachment_count?: number
  attachments?: Array<{
    id: number
    filename?: string | null
    name?: string | null
  }>
}

export interface MailboxDteState {
  status?: string | null
  source?: string | null
  observed_at?: string | null
}

const props = defineProps<{
  messageId: number
  /** Em painel adjacente, botão fechar navega de volta. */
  showClose?: boolean
}>()

const emit = defineEmits<{
  close: []
  triaged: []
}>()

const api = useApi()
const toast = useToast()
const { canTriageMailbox, sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const message = ref<MailboxMessageDetail | null>(null)
const meta = ref<Record<string, unknown> | null>(null)
const dte = ref<MailboxDteState | null>(null)
const triageStatus = ref<MailboxTriageStatus>('NEW')
const triageNote = ref('')
const saving = ref(false)
let loadSeq = 0

const triageItems = [...MAILBOX_TRIAGE_SELECT_ITEMS]

const attachments = computed(() => message.value?.attachments || [])

const officialReadLabel = computed(() => {
  if (meta.value?.official_read_unchanged === true) {
    return 'Inalterada pela triagem'
  }
  const v = message.value?.official_read_indicator
  if (v === true || v === 'READ' || v === 'true') return 'Lida (oficial)'
  if (v === false || v === 'UNREAD' || v === 'false') return 'Não lida (oficial)'
  if (v == null || v === '') return '—'
  return String(v)
})

async function loadDte(clientId: number, parentSeq: number, parentEpoch: number) {
  try {
    const res = await api.fiscal.mailbox.state({ client_id: clientId })
    if (parentSeq !== loadSeq || parentEpoch !== sessionEpoch.value) return
    const data = res.data as { dte?: MailboxDteState } | null
    dte.value = data?.dte || null
  } catch {
    if (parentSeq !== loadSeq || parentEpoch !== sessionEpoch.value) return
    dte.value = null
  }
}

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  if (!Number.isFinite(props.messageId) || props.messageId < 1) {
    loadError.value = 'ID inválido.'
    message.value = null
    return
  }
  loading.value = true
  loadError.value = null
  dte.value = null
  try {
    const res = await api.fiscal.mailbox.get(props.messageId)
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    message.value = res.data as unknown as MailboxMessageDetail
    meta.value = res.meta || null
    triageStatus.value = parseMailboxTriageStatus(res.data.triage_status) || 'NEW'
    triageNote.value = String(res.data.triage_note || '')
    const cid = Number(res.data.client_id)
    if (Number.isFinite(cid) && cid > 0) {
      void loadDte(cid, seq, epoch)
    }
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    message.value = null
    loadError.value = apiErrorMessage(caught, 'Mensagem não encontrada ou sem permissão.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function saveTriage() {
  if (!canTriageMailbox.value) return
  const status = parseMailboxTriageStatus(triageStatus.value)
  if (!status) {
    toast.add({
      title: 'Triagem inválida. Use NEW, IN_REVIEW ou RESOLVED.',
      color: 'warning'
    })
    return
  }
  saving.value = true
  try {
    const res = await api.fiscal.mailbox.triage(props.messageId, {
      triage_status: status,
      note: triageNote.value || undefined
    })
    message.value = res.data as unknown as MailboxMessageDetail
    meta.value = {
      ...(meta.value || {}),
      ...(res.meta || {}),
      official_read_unchanged: true
    }
    toast.add({
      title: 'Triagem atualizada',
      color: 'success'
    })
    emit('triaged')
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao triar.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

function openBody() {
  window.open(api.fiscal.mailbox.bodyDownloadUrl(props.messageId), '_blank', 'noopener')
}

function openAttachment(attachmentId: number) {
  window.open(
    api.fiscal.mailbox.attachmentDownloadUrl(props.messageId, attachmentId),
    '_blank',
    'noopener'
  )
}

function onClose() {
  emit('close')
}

watch(() => props.messageId, () => {
  void load()
}, { immediate: true })

watch(sessionEpoch, () => {
  message.value = null
  meta.value = null
  dte.value = null
  loadError.value = null
  void load()
})
</script>

<template>
  <UDashboardPanel id="mailbox-detail" data-testid="mailbox-detail">
    <template #header>
      <UDashboardNavbar :title="String(message?.subject_preview || `Mensagem #${messageId}`)" data-testid="page-navbar" :toggle="false">
        <template #leading>
          <UButton
            v-if="showClose"
            icon="i-lucide-x"
            color="neutral"
            variant="ghost"
            class="-ms-1.5"
            aria-label="Fechar detalhe"
            @click="onClose"
          />
          <UDashboardSidebarCollapse
            v-else
            class="lg:hidden"
          />
        </template>
        <template #right>
          <UButton
            v-if="message?.has_body"
            color="neutral"
            variant="ghost"
            icon="i-lucide-file-text"
            label="Corpo"
            @click="openBody"
          />
          <UButton
            to="/monitoring/mailbox"
            color="neutral"
            variant="ghost"
            icon="i-lucide-arrow-left"
            label="Lista"
            class="lg:hidden"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="flex flex-1 flex-col overflow-y-auto">
        <UAlert
          v-if="loadError"
          color="error"
          icon="i-lucide-circle-x"
          :title="loadError"
          class="m-4"
        >
          <template #actions>
            <UButton
              size="xs"
              color="neutral"
              variant="outline"
              label="Tentar de novo"
              @click="load"
            />
          </template>
        </UAlert>
        <div
          v-else-if="loading"
          class="p-8 text-center text-sm text-muted"
        >
          Carregando mensagem…
        </div>
        <template v-else-if="message">
          <div class="flex flex-col gap-1 border-b border-default p-4 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-highlighted">
                  {{ message.sender_label || message.sender_code || 'Remetente' }}
                </p>
                <p class="text-sm text-muted">
                  Cliente
                  <NuxtLink
                    v-if="message.client_id"
                    class="text-primary"
                    :to="`/monitoring/clients/${message.client_id}?tab=overview`"
                  >
                    #{{ message.client_id }}
                  </NuxtLink>
                  <span v-else>—</span>
                  · Recebida
                  {{ formatDateTime(String(message.received_at_official || message.created_at || '') || null) }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <UBadge
                  color="neutral"
                  variant="subtle"
                >
                  {{ message.triage_status || '—' }}
                </UBadge>
                <UBadge
                  v-if="message.severity_hint"
                  color="warning"
                  variant="subtle"
                >
                  {{ message.severity_hint }}
                </UBadge>
              </div>
            </div>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  Prazo
                </dt>
                <dd>{{ formatDateTime(String(message.due_at || '') || null) }}</dd>
              </div>
              <div>
                <dt class="text-muted">
                  Leitura oficial
                </dt>
                <dd>{{ officialReadLabel }}</dd>
              </div>
              <div>
                <dt class="text-muted">
                  DTE
                </dt>
                <dd>
                  <template v-if="dte?.status">
                    {{ dte.status }}
                    <span
                      v-if="dte.observed_at"
                      class="text-xs text-muted"
                    >
                      · {{ formatDateTime(dte.observed_at) }}
                    </span>
                  </template>
                  <template v-else>
                    —
                  </template>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Categoria
                </dt>
                <dd>{{ message.category_label || message.category_code || '—' }}</dd>
              </div>
              <div>
                <dt class="text-muted">
                  Fonte
                </dt>
                <dd>{{ message.source || '—' }}</dd>
              </div>
            </dl>
            <p class="mt-3 whitespace-pre-wrap text-sm">
              {{ message.subject_preview || 'Sem prévia de assunto.' }}
            </p>
          </div>

          <div
            v-if="attachments.length"
            class="border-b border-default p-4 sm:px-6"
          >
            <h3 class="mb-2 text-sm font-medium">
              Anexos protegidos
            </h3>
            <ul class="flex flex-col gap-2">
              <li
                v-for="att in attachments"
                :key="String(att.id)"
              >
                <UButton
                  size="sm"
                  color="neutral"
                  variant="soft"
                  icon="i-lucide-paperclip"
                  :label="String(att.filename || att.name || `Anexo #${att.id}`)"
                  @click="openAttachment(Number(att.id))"
                />
              </li>
            </ul>
          </div>

          <div
            v-if="canTriageMailbox"
            class="p-4 sm:px-6"
            data-testid="mailbox-triage-form"
          >
            <h3 class="mb-1 text-sm font-medium">
              Triagem interna
            </h3>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
              <UFormField
                label="Status"
                class="flex-1"
              >
                <USelect
                  v-model="triageStatus"
                  :items="triageItems"
                  value-key="value"
                />
              </UFormField>
              <UFormField
                label="Nota"
                class="flex-[2]"
              >
                <UInput
                  v-model="triageNote"
                  placeholder="Opcional"
                />
              </UFormField>
              <UButton
                label="Salvar triagem"
                :loading="saving"
                data-testid="mailbox-triage-save"
                @click="saveTriage"
              />
            </div>
          </div>
        </template>
      </div>
    </template>
  </UDashboardPanel>
</template>
