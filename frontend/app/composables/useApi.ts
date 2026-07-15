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
  OutboundCapacityForecast,
  OutboundCompetenceSummary,
  OutboundDeadlineMetrics,
  OutboundDeadlinePendingItem,
  OutboundKillSwitchStatus,
  OutboundMonthlyReadiness,
  OutboundNumberState,
  OutboundSeriesCursor,
  PageMeta,
  SvrsNfceChannelSummary,
  SvrsNfceProfileSummary,
  SvrsNfceRecovery,
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
  operational_filter?: 'with_credential' | 'without_credential' | 'expiring' | 'capture_problem'
  sort?: 'legal_name' | 'cnpj' | 'is_active'
  direction?: 'asc' | 'desc'
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
  page?: number
  per_page?: number
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
        client<{ data: NoteClientAggregate[], meta: PageMeta & { total_clients: number } }>(
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
      /** Import multipart de XML/ZIP de saídas (NF-e / NFC-e) — síncrono legado. */
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
      },
      /** Lote assíncrono (ou síncrono se flag off) — preferir este caminho. */
      importBatch: (files: File[], opts?: { clientId?: number | null, establishmentId?: number | null, idempotencyKey?: string }) => {
        const body = new FormData()
        for (const file of files) {
          body.append('files[]', file)
        }
        if (opts?.clientId != null && opts.clientId > 0) {
          body.append('client_id', String(opts.clientId))
        }
        if (opts?.establishmentId != null && opts.establishmentId > 0) {
          body.append('establishment_id', String(opts.establishmentId))
        }
        if (opts?.idempotencyKey) {
          body.append('idempotency_key', opts.idempotencyKey)
        }
        return client<{
          data: {
            public_id: string
            status: string
            imported_count?: number
            duplicate_count?: number
            failed_count?: number
            unmatched_count?: number
            item_count?: number
            file_count?: number
            created?: boolean
          }
        }>('/api/v1/documents/import-batches', { method: 'POST', body })
      },
      importBatches: (params?: { page?: number, per_page?: number }) =>
        client<{ data: Array<Record<string, unknown>>, meta?: PageMeta }>('/api/v1/documents/import-batches', { query: params }),
      importBatchGet: (publicId: string) =>
        client<{ data: Record<string, unknown> }>(`/api/v1/documents/import-batches/${encodeURIComponent(publicId)}`),
      importBatchItems: (publicId: string, params?: { page?: number, per_page?: number, status?: string }) =>
        client<{ data: Array<Record<string, unknown>>, meta?: PageMeta }>(
          `/api/v1/documents/import-batches/${encodeURIComponent(publicId)}/items`,
          { query: params }
        ),
      importBatchRetryItem: (publicId: string, itemId: number) =>
        client<{ data: Record<string, unknown> }>(
          `/api/v1/documents/import-batches/${encodeURIComponent(publicId)}/items/${itemId}/retry`,
          { method: 'POST' }
        ),
      importBatchCsvUrl: (publicId: string) =>
        apiUrl(`/api/v1/documents/import-batches/${encodeURIComponent(publicId)}/export.csv`)
    },
    officeFiscal: {
      get: () =>
        client<{ data: { identity: Record<string, unknown> | null, credential: Record<string, unknown> | null } }>(
          '/api/v1/office/fiscal-identity'
        ),
      upsertIdentity: (body: { cnpj: string, legal_name?: string }) =>
        client<{ data: Record<string, unknown> }>('/api/v1/office/fiscal-identity', { method: 'POST', body }),
      uploadCredential: (pfx: File, password: string) => {
        const body = new FormData()
        body.append('pfx', pfx)
        body.append('password', password)
        return client<{ data: Record<string, unknown> }>('/api/v1/office/fiscal-identity/credential', {
          method: 'POST',
          body
        })
      },
      revokeCredential: (id: number) =>
        client<{ data: Record<string, unknown> }>(`/api/v1/office/fiscal-identity/credentials/${id}/revoke`, {
          method: 'POST'
        })
    },
    quarantine: {
      list: (params?: { reason?: string, limit?: number }) =>
        client<{ data: Array<Record<string, unknown>> }>('/api/v1/operations/quarantine', { query: params }),
      resolve: (id: number, body: { resolution_status: 'RESOLVED' | 'DISMISSED', resolution_code?: string, resolution_notes?: string }) =>
        client<{ data: Record<string, unknown> }>(`/api/v1/operations/quarantine/${id}/resolve`, {
          method: 'POST',
          body
        })
    },
    officeAutXml: {
      overview: () =>
        client<{
          data: {
            identity: Record<string, unknown> | null
            office_cnpj: string | null
            cursor: Record<string, unknown> | null
            stream: {
              stream_ready: boolean
              stream_reason: string | null
              quiet_hours: number
              activated_at: string | null
              ready_at: string | null
            }
            coverage: Record<string, unknown>
            enrollments: Array<Record<string, unknown>>
            checklist?: Record<string, unknown>
          }
        }>('/api/v1/office/autxml'),
      cursor: () =>
        client<{
          data: {
            cursor: Record<string, unknown> | null
            cursors?: Array<Record<string, unknown>>
            stream: {
              stream_ready: boolean
              stream_reason: string | null
              quiet_hours: number
              activated_at: string | null
              ready_at: string | null
            }
            recent_runs: Array<Record<string, unknown>>
          }
        }>('/api/v1/office/autxml/cursor'),
      enroll: (establishmentId: number) =>
        client<{ data: Record<string, unknown> }>('/api/v1/office/autxml/enrollments', {
          method: 'POST',
          body: { establishment_id: establishmentId }
        }),
      confirm: (enrollmentId: number) =>
        client<{ data: Record<string, unknown> }>(
          `/api/v1/office/autxml/enrollments/${enrollmentId}/confirm`,
          { method: 'POST' }
        ),
      inactivate: (enrollmentId: number) =>
        client<{ data: Record<string, unknown> }>(
          `/api/v1/office/autxml/enrollments/${enrollmentId}/inactivate`,
          { method: 'POST' }
        )
    },
    /** @deprecated Preferir `documents` — alias de compat. */
    notes: {
      list: (params?: NoteListParams) =>
        client<{ data: NfseNote[], meta: CursorMeta }>('/api/v1/documents', { query: params }),
      byClient: (params?: NoteListParams) =>
        client<{ data: NoteClientAggregate[], meta: PageMeta & { total_clients: number } }>(
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
      list: (params?: { page?: number, per_page?: number }) =>
        client<{ data: ExportJob[], meta: PageMeta }>('/api/v1/exports', { query: params }),
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
        client<{ data: { configured: boolean, csc_id?: string | null, configured_at?: string | null, csc?: string | null } }>(
          `/api/v1/outbound/profiles/${profileId}/csc`
        ),
      storeCsc: (profileId: number, body: { csc: string, csc_id: string }) =>
        client<{ data: { configured: boolean, csc_id?: string | null, configured_at?: string | null, csc?: string | null } }>(
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
        client<{ data: unknown }>('/api/v1/outbound/kill-switch', { method: 'POST', body }),
      svrsNfce: {
        summary: () =>
          client<{ data: SvrsNfceChannelSummary }>('/api/v1/outbound/svrs-nfce/summary'),
        recoveries: (params?: {
          status?: string
          profile_id?: number
          client_id?: number
          page?: number
          per_page?: number
        }) =>
          client<{ data: SvrsNfceRecovery[], meta: { current_page: number, last_page: number, total: number } }>(
            '/api/v1/outbound/svrs-nfce/recoveries',
            { query: params }
          ),
        profileSummary: (profileId: number) =>
          client<{ data: SvrsNfceProfileSummary }>(`/api/v1/outbound/svrs-nfce/profiles/${profileId}/summary`),
        enqueue: (body: { number_state_id: number }) =>
          client<{ data: SvrsNfceRecovery }>('/api/v1/outbound/svrs-nfce/recoveries', {
            method: 'POST',
            body
          }),
        retry: (recoveryId: number) =>
          client<{ data: SvrsNfceRecovery }>(`/api/v1/outbound/svrs-nfce/recoveries/${recoveryId}/retry`, {
            method: 'POST'
          }),
        killSwitchStatus: () =>
          client<{ data: { active: boolean, source?: string | null } }>('/api/v1/outbound/svrs-nfce/kill-switch'),
        killSwitch: (body: { active: boolean, reason: string }) =>
          client<{ data: { active: boolean, source?: string | null } }>('/api/v1/outbound/svrs-nfce/kill-switch', {
            method: 'POST',
            body
          }),
        breakerStatus: () =>
          client<{ data: { global: { state: string, open_until?: number | null, failures?: number } } }>(
            '/api/v1/outbound/svrs-nfce/breaker'
          ),
        breakerReset: (body: { scope: 'global' | 'root', client_id?: number, reason: string }) =>
          client<{ data: { global: { state: string } } }>('/api/v1/outbound/svrs-nfce/breaker/reset', {
            method: 'POST',
            body
          })
      },
      deadline: {
        competence: (competence: string) =>
          client<{ data: OutboundCompetenceSummary }>('/api/v1/outbound/deadline/competence', {
            query: { competence }
          }),
        capacity: (competence: string) =>
          client<{ data: OutboundCapacityForecast }>('/api/v1/outbound/deadline/capacity', {
            query: { competence }
          }),
        pending: (params?: {
          competence?: string
          urgency_band?: string
          model?: string
          root_cnpj?: string
          client_id?: number
          source?: string
          page?: number
          per_page?: number
        }) =>
          client<{ data: OutboundDeadlinePendingItem[], meta: PageMeta }>('/api/v1/outbound/deadline/pending', {
            query: params
          }),
        contingencyBatch: (competence?: string) =>
          client<{ data: Array<Record<string, unknown>> }>('/api/v1/outbound/deadline/contingency-batch', {
            query: competence ? { competence } : undefined
          }),
        metrics: (competence?: string) =>
          client<{ data: OutboundDeadlineMetrics }>('/api/v1/outbound/deadline/metrics', {
            query: competence ? { competence } : undefined
          }),
        confirmPartial: (body: { competence: string, notes?: string }) =>
          client<{ data: OutboundMonthlyReadiness }>('/api/v1/outbound/deadline/confirm-partial', {
            method: 'POST',
            body
          }),
        exportMonthly: (body: { competence: string, include_events?: boolean, notes?: string }) =>
          client<{
            data: {
              export: ExportJob
              readiness: OutboundMonthlyReadiness
              has_manifest: boolean
              completeness_scope: string
            }
          }>('/api/v1/outbound/deadline/export', { method: 'POST', body }),
        advanceTarget: (body: { competence: string, target_at: string }) =>
          client<{ data: { competence: string, target_at: string, due_at: string, updated_rows: number } }>(
            '/api/v1/outbound/deadline/advance-target',
            { method: 'POST', body }
          )
      }
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
