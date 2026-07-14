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
  FiscalRole,
  LoginResponse,
  MeResponse,
  NfseNote,
  OperationsSummary,
  PageMeta,
  SyncRun,
  TwoFactorQrCode
} from '~/types/api'

export interface ClientListParams {
  q?: string
  page?: number
  per_page?: number
  /** Filtro de estado no escritório (true/false) */
  is_active?: boolean | 0 | 1
}

export interface NoteListParams {
  access_key?: string
  issuer_cnpj?: string
  taker_cnpj?: string
  competence?: string
  status?: string
  fiscal_role?: FiscalRole | ''
  client_id?: number
  establishment_id?: number
  issued_from?: string
  issued_to?: string
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
    notes: {
      list: (params?: NoteListParams) =>
        client<{ data: NfseNote[], meta: CursorMeta }>('/api/v1/notes', { query: params }),
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
      }>(`/api/v1/notes/${encodeURIComponent(accessKey)}`),
      xmlUrl: (accessKey: string) => apiUrl(`/api/v1/notes/${encodeURIComponent(accessKey)}/xml`)
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
      summary: () => client<{ data: OperationsSummary }>('/api/v1/operations/summary')
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
