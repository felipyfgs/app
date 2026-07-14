import type {
  Client,
  ClientCredential,
  CursorMeta,
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
      create: (body: { name: string, cnpj: string, notes?: string }) =>
        client<{ data: Client }>('/api/v1/clients', { method: 'POST', body }),
      get: (id: number) => client<{ data: Client }>(`/api/v1/clients/${id}`),
      update: (id: number, body: { name?: string, notes?: string | null, is_active?: boolean }) =>
        client<{ data: Client }>(`/api/v1/clients/${id}`, { method: 'PATCH', body })
    },
    establishments: {
      create: (clientId: number, body: { cnpj: string, trade_name?: string, is_matrix?: boolean }) =>
        client<{ data: Establishment }>(`/api/v1/clients/${clientId}/establishments`, { method: 'POST', body }),
      update: (id: number, body: { trade_name?: string | null, is_matrix?: boolean, is_active?: boolean }) =>
        client<{ data: Establishment }>(`/api/v1/establishments/${id}`, { method: 'PATCH', body })
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
      get: (accessKey: string) => client<{ data: NfseNote }>(`/api/v1/notes/${encodeURIComponent(accessKey)}`),
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
