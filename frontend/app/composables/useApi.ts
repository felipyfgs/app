import type {
  Client,
  ClientContact,
  ClientCredential,
  CnpjLookupResult,
  CreateClientPayload,
  CreateClientResponse,
  CursorMeta,
  DfeDocumentMetadata,
  Establishment,
  ExportFilters,
  ExportJob,
  DocumentDirection,
  FiscalRole,
  LoginResponse,
  MeResponse,
  InboxItem,
  InboxMeta,
  InboxItemType,
  InboxSeverity,
  NfseNote,
  NoteClientAggregate,
  NotesInsights,
  OperationsSummary,
  OutboundCaptureProfile,
  OutboundCaptureRun,
  OutboundKillSwitchStatus,
  OutboundNumberState,
  OutboundSeriesCursor,
  PageMeta,
  SyncRun,
  TwoFactorQrCode
} from '~/types/api'

export interface InboxListParams {
  severity?: InboxSeverity | ''
  type?: InboxItemType | ''
  limit?: number
  cursor?: string
}

export interface ClientListParams {
  q?: string
  page?: number
  per_page?: number
  /** Filtro de estado no escritório (true/false) */
  is_active?: boolean | 0 | 1
}

export interface NoteListParams {
  /** Busca de triagem: número, nome, CNPJ ou chave. */
  q?: string
  /** Tipo DF-e (NFSE, NFE, CTE, …). */
  kind?: string
  /** @deprecated Preferir `q`; mantido para compat. */
  access_key?: string
  issuer_cnpj?: string
  taker_cnpj?: string
  competence?: string
  status?: string
  fiscal_role?: FiscalRole | ''
  /** Entrada (IN) / Saída (OUT) / Indefinida. */
  direction?: DocumentDirection | ''
  client_id?: number
  establishment_id?: number
  issued_from?: string
  issued_to?: string
  /** Fila: falta nome de emitente ou tomador. */
  missing_party_name?: boolean | 0 | 1
  cursor?: string
  limit?: number
}

export function useApi() {
  const client = useSanctumClient()
  const apiBase = useRuntimeConfig().public.apiBase.replace(/\/$/, '')
  const apiUrl = (path: string) => `${apiBase}${path}`

  return {
    me: () => client<MeResponse>('/api/v1/me'),
    clients: {
      list: (params?: ClientListParams) =>
        client<{ data: Client[], meta: PageMeta }>('/api/v1/clients', { query: params }),
      create: (body: CreateClientPayload) =>
        client<{ data: CreateClientResponse }>('/api/v1/clients', { method: 'POST', body }),
      get: (id: number) => client<{ data: Client }>(`/api/v1/clients/${id}`),
      update: (id: number, body: Partial<{
        legal_name: string
        display_name: string | null
        notes: string | null
        is_active: boolean
        inactive_reason: string | null
        legal_nature_code: string | null
        legal_nature_name: string | null
        company_size_code: string | null
        company_size_name: string | null
        tax_regime: string | null
      }>) =>
        client<{ data: Client }>(`/api/v1/clients/${id}`, { method: 'PATCH', body })
    },
    cnpj: {
      lookup: (cnpj: string) => client<{ data: CnpjLookupResult }>(
        `/api/v1/cnpj/${encodeURIComponent(cnpj)}/lookup`
      )
    },
    establishments: {
      create: (clientId: number, body: Record<string, unknown>) =>
        client<{ data: Establishment }>(`/api/v1/clients/${clientId}/establishments`, { method: 'POST', body }),
      update: (id: number, body: Record<string, unknown>) =>
        client<{ data: Establishment }>(`/api/v1/establishments/${id}`, { method: 'PATCH', body })
    },
    contacts: {
      list: (clientId: number) =>
        client<{ data: ClientContact[] }>(`/api/v1/clients/${clientId}/contacts`),
      create: (clientId: number, body: Record<string, unknown>) =>
        client<{ data: ClientContact }>(`/api/v1/clients/${clientId}/contacts`, { method: 'POST', body }),
      update: (clientId: number, contactId: number, body: Record<string, unknown>) =>
        client<{ data: ClientContact }>(`/api/v1/clients/${clientId}/contacts/${contactId}`, { method: 'PATCH', body }),
      remove: (clientId: number, contactId: number) =>
        client(`/api/v1/clients/${clientId}/contacts/${contactId}`, { method: 'DELETE' })
    },
    credentials: {
      get: (clientId: number) =>
        client<{ data: ClientCredential | null }>(`/api/v1/clients/${clientId}/credential`),
      activate: (clientId: number, pfx: File, password: string) => {
        const body = new FormData()
        body.append('pfx', pfx)
        body.append('password', password)
        return client<{ data: ClientCredential }>(`/api/v1/clients/${clientId}/credential`, {
          method: 'POST',
          body
        })
      }
    },
    documents: {
      list: (params?: NoteListParams) =>
        client<{ data: NfseNote[], meta: CursorMeta }>('/api/v1/documents', { query: params }),
      byClient: (params?: NoteListParams) =>
        client<{ data: NoteClientAggregate[], meta: { total_clients: number } }>(
          '/api/v1/documents/by-client',
          { query: params }
        ),
      insights: (params?: NoteListParams) =>
        client<{ data: NotesInsights }>('/api/v1/documents/insights', { query: params }),
      get: (accessKey: string) => client<{
        data: {
          note: NfseNote
          events: Array<{
            id: number
            access_key: string
            event_type?: string | null
            event_at?: string | null
            status?: string | null
          }>
          document: DfeDocumentMetadata | null
        }
      }>(`/api/v1/documents/${encodeURIComponent(accessKey)}`),
      xmlUrl: (accessKey: string) => apiUrl(`/api/v1/documents/${encodeURIComponent(accessKey)}/xml`),
      /** Desbloqueio de XML completo (ciência 210210). */
      unlockXml: (accessKey: string) =>
        client<{
          data: {
            status: string
            has_full_xml: boolean
            message: string
            manifestation_status?: string | null
            protocol?: string | null
          }
        }>(`/api/v1/documents/${encodeURIComponent(accessKey)}/unlock-xml`, { method: 'POST' }),
      /** Manifestação do destinatário (ciência / conclusivas). */
      manifest: (
        accessKey: string,
        body: {
          type: 'CIENCIA' | 'CONFIRMACAO' | 'DESCONHECIMENTO' | 'NAO_REALIZADA'
          justification?: string
          purpose?: 'UNLOCK_XML' | 'FISCAL'
        }
      ) =>
        client<{
          data: {
            status: string
            has_full_xml: boolean
            message: string
            manifestation_status?: string | null
            protocol?: string | null
            c_stat?: string | null
          }
        }>(`/api/v1/documents/${encodeURIComponent(accessKey)}/manifestations`, {
          method: 'POST',
          body
        }),
      /** Import multipart de XML/ZIP de saídas (NF-e / NFC-e). */
      import: (files: File[], clientId?: number | null) => {
        const body = new FormData()
        for (const file of files) {
          body.append('files[]', file)
        }
        if (clientId != null && clientId > 0) {
          body.append('client_id', String(clientId))
        }
        return client<{
          data: {
            imported: number
            skipped: number
            errors: number
            items: Array<{
              status: string
              filename: string
              access_key?: string
              kind?: string
              message?: string
              sha256?: string
            }>
          }
        }>('/api/v1/documents/import', { method: 'POST', body })
      }
    },
    /** @deprecated Preferir `documents` — alias de compat. */
    notes: {
      list: (params?: NoteListParams) =>
        client<{ data: NfseNote[], meta: CursorMeta }>('/api/v1/documents', { query: params }),
      byClient: (params?: NoteListParams) =>
        client<{ data: NoteClientAggregate[], meta: { total_clients: number } }>(
          '/api/v1/documents/by-client',
          { query: params }
        ),
      insights: (params?: NoteListParams) =>
        client<{ data: NotesInsights }>('/api/v1/documents/insights', { query: params }),
      get: (accessKey: string) => client<{
        data: {
          note: NfseNote
          events: Array<{
            id: number
            access_key: string
            event_type?: string | null
            event_at?: string | null
            status?: string | null
          }>
          document: DfeDocumentMetadata | null
        }
      }>(`/api/v1/documents/${encodeURIComponent(accessKey)}`),
      xmlUrl: (accessKey: string) => apiUrl(`/api/v1/documents/${encodeURIComponent(accessKey)}/xml`)
    },
    sync: {
      history: (params?: { cursor?: string, limit?: number }) =>
        client<{ data: SyncRun[], meta: CursorMeta }>('/api/v1/sync-runs', { query: params }),
      trigger: (establishmentId: number) =>
        client<{ data: { sync_cursor_id: number } }>('/api/v1/sync-runs', {
          method: 'POST',
          body: { establishment_id: establishmentId }
        })
    },
    exports: {
      list: () => client<{ data: ExportJob[] }>('/api/v1/exports'),
      create: (body: { filters?: ExportFilters, include_events?: boolean }) =>
        client<{ data: ExportJob }>('/api/v1/exports', { method: 'POST', body }),
      downloadUrl: (id: number) => apiUrl(`/api/v1/exports/${id}/download`)
    },
    operations: {
      summary: () => client<{ data: OperationsSummary }>('/api/v1/operations/summary'),
      inbox: (params?: InboxListParams) =>
        client<{ data: InboxItem[], meta: InboxMeta }>('/api/v1/operations/inbox', { query: params })
    },
    outbound: {
      profiles: (params?: { client_id?: number, establishment_id?: number }) =>
        client<{ data: OutboundCaptureProfile[] }>('/api/v1/outbound/profiles', { query: params }),
      profile: (id: number) =>
        client<{ data: OutboundCaptureProfile }>(`/api/v1/outbound/profiles/${id}`),
      seed: (establishmentId: number, body: { environment: string, xml?: string, file?: File }) => {
        if (body.file) {
          const fd = new FormData()
          fd.append('environment', body.environment)
          fd.append('file', body.file)
          return client<{ data: { profile: OutboundCaptureProfile, series: OutboundSeriesCursor } }>(
            `/api/v1/outbound/establishments/${establishmentId}/seed`,
            { method: 'POST', body: fd }
          )
        }
        return client<{ data: { profile: OutboundCaptureProfile, series: OutboundSeriesCursor } }>(
          `/api/v1/outbound/establishments/${establishmentId}/seed`,
          { method: 'POST', body: { environment: body.environment, xml: body.xml } }
        )
      },
      series: (profileId: number) =>
        client<{ data: OutboundSeriesCursor[] }>(`/api/v1/outbound/profiles/${profileId}/series`),
      numbers: (seriesId: number, gapsOnly?: boolean) =>
        client<{ data: OutboundNumberState[] }>(`/api/v1/outbound/series/${seriesId}/numbers`, {
          query: gapsOnly ? { gaps_only: 1 } : undefined
        }),
      runs: (params?: { series_cursor_id?: number }) =>
        client<{ data: OutboundCaptureRun[] }>('/api/v1/outbound/runs', { query: params }),
      activate: (profileId: number, body: { mandate_reference: string, allowlisted?: boolean }) =>
        client<{ data: OutboundCaptureProfile }>(`/api/v1/outbound/profiles/${profileId}/activate`, {
          method: 'POST',
          body
        }),
      uploadPackage: (profileId: number, files: File[]) => {
        const fd = new FormData()
        files.forEach(f => fd.append('files[]', f))
        return client<{ data: { imported: number, skipped: number, quarantined: number, errors: number } }>(
          `/api/v1/outbound/profiles/${profileId}/package`,
          { method: 'POST', body: fd }
        )
      },
      cscState: (profileId: number) =>
        client<{ data: { configured: boolean, csc_id?: string | null, configured_at?: string | null } }>(
          `/api/v1/outbound/profiles/${profileId}/csc`
        ),
      storeCsc: (profileId: number, body: { csc: string, csc_id: string }) =>
        client<{ data: { configured: boolean, csc_id?: string | null } }>(
          `/api/v1/outbound/profiles/${profileId}/csc`,
          { method: 'POST', body }
        ),
      triggerQuery: (seriesId: number) =>
        client<{ data: { queued: boolean, series_id: number } }>(
          `/api/v1/outbound/series/${seriesId}/trigger-query`,
          { method: 'POST' }
        ),
      resetSeries: (seriesId: number, body: { reason: string, discovery_position: number, confirm: boolean }) =>
        client<{ data: OutboundSeriesCursor }>(`/api/v1/outbound/series/${seriesId}/reset`, {
          method: 'POST',
          body
        }),
      killSwitchStatus: () =>
        client<{ data: OutboundKillSwitchStatus }>('/api/v1/outbound/kill-switch'),
      killSwitch: (body: { active: boolean, reason: string, profile_id?: number }) =>
        client<{ data: unknown }>('/api/v1/outbound/kill-switch', { method: 'POST', body })
    },
    twoFactor: {
      confirmPassword: (password: string) =>
        client('/user/confirm-password', { method: 'POST', body: { password } }),
      enable: () => client('/user/two-factor-authentication', { method: 'POST' }),
      qrCode: () => client<TwoFactorQrCode>('/user/two-factor-qr-code'),
      confirm: (code: string) =>
        client('/user/confirmed-two-factor-authentication', { method: 'POST', body: { code } }),
      recoveryCodes: () => client<string[]>('/user/two-factor-recovery-codes'),
      challenge: (body: { code?: string, recovery_code?: string }) =>
        client<LoginResponse>('/two-factor-challenge', { method: 'POST', body })
    }
  }
}
