import type {
  CommunicationAutomationMeta,
  CommunicationAutomationPolicy,
  CommunicationCannedResponse,
  CommunicationContact,
  CommunicationConversation,
  CommunicationConversationStatus,
  CommunicationEvent,
  CommunicationFeatureMeta,
  CommunicationInbox,
  CommunicationLabel,
  CommunicationMessage,
  CommunicationPairingState,
  CommunicationQueuedCommand,
  CommunicationRecipientConfiguration,
  CommunicationRecipientMode,
  CommunicationSendKind,
  CommunicationSyncMeta
} from '~/types/communication'
import type { ApiClient, ApiUrl } from './types'

export interface CommunicationConversationFilters {
  q?: string
  inbox_id?: number
  status?: CommunicationConversationStatus
  assignee_membership_id?: number
  work_department_id?: number
  unassigned?: boolean
  page?: number
  per_page?: number
}

export interface CommunicationPolicyBody {
  module_key: string
  submodule_key: string
  inbox_id: number | null
  is_enabled: boolean
  send_day: number
  send_time: string
  timezone: string
  recipient_mode: CommunicationRecipientMode
  template_key: string
  template_version: string
  lock_version: number
}

export function createCommunicationApi(client: ApiClient, apiUrl: ApiUrl) {
  const base = '/api/v1/communication'

  return {
    communication: {
      inboxes: {
        list: () => client<{ data: CommunicationInbox[], meta: CommunicationFeatureMeta }>(`${base}/inboxes`),
        create: (body: {
          name: string
          is_enabled?: boolean
          is_default?: boolean
          work_department_id?: number | null
        }) => client<{ data: CommunicationInbox }>(`${base}/inboxes`, { method: 'POST', body }),
        update: (id: number, body: Partial<Pick<CommunicationInbox,
          'name' | 'is_enabled' | 'is_default' | 'work_department_id'>> & { lock_version: number }) =>
          client<{ data: CommunicationInbox }>(`${base}/inboxes/${id}`, { method: 'PATCH', body }),
        replaceMembers: (id: number, membershipIds: number[]) =>
          client<{ data: { membership_ids: number[] } }>(`${base}/inboxes/${id}/members`, {
            method: 'PUT',
            body: { membership_ids: membershipIds }
          }),
        startPairing: (id: number) =>
          client<{ data: { status: string, commands: string[] } }>(`${base}/inboxes/${id}/pairing`, {
            method: 'POST'
          }),
        pairing: (id: number) =>
          client<{ data: CommunicationPairingState }>(`${base}/inboxes/${id}/pairing`),
        revoke: (id: number) =>
          client<{ data: { command_id: string } }>(`${base}/inboxes/${id}/session`, { method: 'DELETE' }),
        updateOfficeSettings: (enabled: boolean) =>
          client<{ data: { enabled: boolean } }>(`${base}/settings`, {
            method: 'PATCH',
            body: { enabled }
          })
      },
      contacts: {
        list: (params?: { q?: string, include_inactive?: boolean, page?: number, per_page?: number }) =>
          client<{ data: CommunicationContact[], meta: { current_page: number, last_page: number, total: number } }>(
            `${base}/contacts`,
            { query: params }
          ),
        get: (id: number) => client<{ data: CommunicationContact }>(`${base}/contacts/${id}`),
        create: (body: {
          name?: string | null
          phone: string
          client_id?: number
          client_contact_id?: number
          is_primary?: boolean
          receives_automatic?: boolean
        }) => client<{ data: CommunicationContact }>(`${base}/contacts`, { method: 'POST', body }),
        update: (id: number, body: { name?: string | null, is_active?: boolean }) =>
          client<{ data: CommunicationContact }>(`${base}/contacts/${id}`, { method: 'PATCH', body }),
        addIdentity: (contactId: number, phone: string) =>
          client<{ data: { id: number, address_masked: string } }>(`${base}/contacts/${contactId}/identities`, {
            method: 'POST',
            body: { phone }
          }),
        linkIdentity: (identityId: number, body: {
          client_id: number
          client_contact_id?: number | null
          is_primary?: boolean
          receives_automatic?: boolean
        }) => client<{ data: {
          id: number
          identity_id: number
          client_id: number
          client_contact_id?: number | null
          is_primary: boolean
          receives_automatic: boolean
        } }>(`${base}/identities/${identityId}/links`, { method: 'POST', body }),
        unlinkIdentity: (identityId: number, linkId: number) =>
          client<unknown>(`${base}/identities/${identityId}/links/${linkId}`, { method: 'DELETE' }),
        exportUrl: (contactId: number) => apiUrl(`${base}/contacts/${contactId}/export`),
        purge: (contactId: number) => client<{ data: {
          contact_id: number
          purged_at: string
          deleted_blobs: number
          tombstone_digest: string
        } }>(`${base}/contacts/${contactId}/personal-data`, { method: 'DELETE' })
      },
      conversations: {
        list: (params?: CommunicationConversationFilters) =>
          client<{ data: CommunicationConversation[], meta: { current_page: number, last_page: number, total: number } }>(
            `${base}/conversations`,
            { query: params }
          ),
        get: (id: number) => client<{ data: CommunicationConversation }>(`${base}/conversations/${id}`),
        update: (id: number, body: {
          lock_version: number
          status?: CommunicationConversationStatus
          assignee_membership_id?: number | null
          work_department_id?: number | null
          priority?: number
          snoozed_until?: string | null
        }) => client<{ data: CommunicationConversation }>(`${base}/conversations/${id}`, {
          method: 'PATCH',
          body
        }),
        send: (id: number, body: {
          body: string
          internal_note?: boolean
          reply_to_message_id?: number | null
          idempotency_key?: string
          file?: File | null
          kind?: CommunicationSendKind
          ptt?: boolean
        }) => {
          const payload = new FormData()
          payload.set('body', body.body)
          if (body.internal_note) payload.set('internal_note', '1')
          if (body.reply_to_message_id) payload.set('reply_to_message_id', String(body.reply_to_message_id))
          if (body.idempotency_key) payload.set('idempotency_key', body.idempotency_key)
          if (body.kind) payload.set('kind', body.kind)
          if (body.ptt) payload.set('ptt', '1')
          if (body.file) payload.set('file', body.file, body.file.name)
          return client<{ data: CommunicationMessage }>(`${base}/conversations/${id}/messages`, {
            method: 'POST',
            body: payload
          })
        },
        editMessage: (conversationId: number, messageId: number, text: string) =>
          client<{ data: CommunicationQueuedCommand }>(
            `${base}/conversations/${conversationId}/messages/${messageId}/edit`,
            { method: 'PUT', body: { text } }
          ),
        revokeMessage: (conversationId: number, messageId: number) =>
          client<{ data: CommunicationQueuedCommand }>(
            `${base}/conversations/${conversationId}/messages/${messageId}`,
            { method: 'DELETE' }
          ),
        reactMessage: (conversationId: number, messageId: number, emoji: string | null) =>
          client<{ data: CommunicationQueuedCommand }>(
            `${base}/conversations/${conversationId}/messages/${messageId}/reaction`,
            { method: 'PUT', body: { emoji } }
          ),
        votePoll: (conversationId: number, messageId: number, optionNames: string[]) =>
          client<{ data: CommunicationQueuedCommand }>(
            `${base}/conversations/${conversationId}/messages/${messageId}/poll-votes`,
            { method: 'POST', body: { option_names: optionNames } }
          ),
        receipt: (conversationId: number, messageId: number, receipt: 'READ' | 'PLAYED') =>
          client<{ data: CommunicationQueuedCommand }>(
            `${base}/conversations/${conversationId}/messages/${messageId}/receipts`,
            { method: 'POST', body: { receipt } }
          ),
        recoverMessage: (
          conversationId: number,
          messageId: number,
          operation: 'UNAVAILABLE' | 'MEDIA_RETRY'
        ) => client<{ data: CommunicationQueuedCommand }>(
          `${base}/conversations/${conversationId}/messages/${messageId}/recovery`,
          { method: 'POST', body: { operation } }
        ),
        subscribePresence: (conversationId: number) =>
          client<{ data: CommunicationQueuedCommand }>(
            `${base}/conversations/${conversationId}/presence/subscribe`,
            { method: 'POST' }
          ),
        setPresence: (
          conversationId: number,
          presence: 'COMPOSING' | 'PAUSED' | 'RECORDING',
          media?: 'TEXT' | 'AUDIO'
        ) => client<{ data: CommunicationQueuedCommand }>(
          `${base}/conversations/${conversationId}/presence`,
          { method: 'PUT', body: { presence, ...(media ? { media } : {}) } }
        ),
        setDisappearing: (
          conversationId: number,
          timerSeconds: 0 | 86400 | 604800 | 7776000
        ) => client<{ data: CommunicationQueuedCommand }>(
          `${base}/conversations/${conversationId}/disappearing`,
          { method: 'PUT', body: { timer_seconds: timerSeconds } }
        ),
        addLabel: (conversationId: number, labelId: number) =>
          client<{ data: { label_id: number } }>(`${base}/conversations/${conversationId}/labels/${labelId}`, {
            method: 'PUT'
          }),
        removeLabel: (conversationId: number, labelId: number) =>
          client<unknown>(`${base}/conversations/${conversationId}/labels/${labelId}`, { method: 'DELETE' })
      },
      catalog: {
        labels: () => client<{ data: CommunicationLabel[] }>(`${base}/labels`),
        createLabel: (body: { name: string, color?: string }) =>
          client<{ data: CommunicationLabel }>(`${base}/labels`, { method: 'POST', body }),
        deleteLabel: (id: number) => client<unknown>(`${base}/labels/${id}`, { method: 'DELETE' }),
        cannedResponses: (q?: string) =>
          client<{ data: CommunicationCannedResponse[] }>(`${base}/canned-responses`, {
            query: q ? { q } : undefined
          }),
        createCannedResponse: (body: Omit<CommunicationCannedResponse, 'id'>) =>
          client<{ data: CommunicationCannedResponse }>(`${base}/canned-responses`, { method: 'POST', body }),
        updateCannedResponse: (id: number, body: Omit<CommunicationCannedResponse, 'id'>) =>
          client<{ data: CommunicationCannedResponse }>(`${base}/canned-responses/${id}`, { method: 'PUT', body }),
        deleteCannedResponse: (id: number) => client<unknown>(`${base}/canned-responses/${id}`, { method: 'DELETE' })
      },
      events: {
        sync: (after: number, limit = 200) =>
          client<{ data: CommunicationEvent[], meta: CommunicationSyncMeta }>(`${base}/events`, {
            query: { after, limit }
          })
      },
      attachments: {
        downloadUrl: (id: number) => apiUrl(`${base}/attachments/${id}/download`)
      },
      automation: {
        list: () => client<{ data: CommunicationAutomationPolicy[], meta: CommunicationAutomationMeta }>(
          `${base}/automation-policies`
        ),
        upsert: (body: CommunicationPolicyBody) =>
          client<{ data: CommunicationAutomationPolicy }>(`${base}/automation-policies`, {
            method: 'PUT',
            body
          }),
        recipients: (clientId: number, moduleKey: string, submoduleKey: string) =>
          client<{ data: CommunicationRecipientConfiguration }>(
            `${base}/clients/${clientId}/automation-recipients`,
            { query: { module_key: moduleKey, submodule_key: submoduleKey } }
          ),
        updateRecipients: (clientId: number, body: {
          module_key: string
          submodule_key: string
          recipient_mode: CommunicationRecipientMode
          identity_ids: number[]
          lock_version: number
        }) => client<{ data: CommunicationRecipientConfiguration }>(
          `${base}/clients/${clientId}/automation-recipients`,
          { method: 'PUT', body }
        )
      }
    }
  }
}
