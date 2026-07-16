import type {
  OfficeSerproAuthorization,
  OfficeSubscription,
  OfficeUsageEntry,
  OfficeUsageSummary,
  PageMeta,
  SerproPlatformHealth,
  TaxProxyPower
} from '~/types/api'
import type { ApiClient } from './types'

export function createOfficeApi(client: ApiClient) {
  return {
    office: {
      subscription: () =>
        client<{ data: OfficeSubscription }>('/api/v1/office/subscription'),
      serproAuthorization: {
        show: (params?: { environment?: string }) =>
          client<{
            data: OfficeSerproAuthorization
            platform_health?: SerproPlatformHealth | null
            term_representation_strategy?: string | null
          }>('/api/v1/office/serpro-authorization', { query: params }),
        configureAuthor: (body: Record<string, unknown>) =>
          client<{ data: OfficeSerproAuthorization }>('/api/v1/office/serpro-authorization/author', {
            method: 'POST',
            body
          }),
        uploadTermo: (body: FormData | { termo_xml?: string, environment?: string }) =>
          client<{ data: OfficeSerproAuthorization }>('/api/v1/office/serpro-authorization/termo', {
            method: 'POST',
            body
          }),
        storeAuthorA1: (pfx: File, password: string, consent: boolean, environment?: string) => {
          const body = new FormData()
          body.append('pfx', pfx)
          body.append('password', password)
          body.append('consent', consent ? '1' : '0')
          if (environment) body.append('environment', environment)
          return client<{ data: OfficeSerproAuthorization }>(
            '/api/v1/office/serpro-authorization/author-a1',
            { method: 'POST', body }
          )
        },
        refreshToken: (params?: { environment?: string }) =>
          client<{ data: OfficeSerproAuthorization }>('/api/v1/office/serpro-authorization/refresh-token', {
            method: 'POST',
            body: params || {}
          }),
        proxyPowers: (params?: { client_id?: number }) =>
          client<{ data: TaxProxyPower[] }>('/api/v1/office/serpro-authorization/proxy-powers', {
            query: params
          }),
        importProxyPower: (body: Record<string, unknown>) =>
          client<{ data: TaxProxyPower }>('/api/v1/office/serpro-authorization/proxy-powers', {
            method: 'POST',
            body
          }),
        syncProxyPowers: (body?: Record<string, unknown>) =>
          client<{ data?: unknown }>('/api/v1/office/serpro-authorization/proxy-powers/sync', {
            method: 'POST',
            body: body || {}
          }),
        eligibility: (body: Record<string, unknown>) =>
          client<{ data: Record<string, unknown> }>('/api/v1/office/serpro-authorization/eligibility', {
            method: 'POST',
            body
          }),
        health: (params?: { environment?: string }) =>
          client<{ data: SerproPlatformHealth }>('/api/v1/office/serpro-authorization/health', {
            query: params
          })
      },
      serproUsage: {
        summary: (params?: { year?: number, month?: number }) =>
          client<{ data: OfficeUsageSummary }>('/api/v1/office/serpro-usage', { query: params }),
        entries: (params?: { year?: number, month?: number, page?: number, per_page?: number }) =>
          client<{ data: OfficeUsageEntry[], meta?: PageMeta, current_page?: number, last_page?: number, total?: number, per_page?: number }>(
            '/api/v1/office/serpro-usage/entries',
            { query: params }
          )
      }
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
    }
  }
}
