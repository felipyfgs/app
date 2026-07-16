import type {
  ActivationMethod,
  CreateOfficeMemberBody,
  CreateOfficeMemberResult,
  CredentialDeliveryPayload,
  OfficeAutXmlEnrollment,
  OfficeAutXmlOverview,
  OfficeCanonicalCredential,
  OfficeInstitutionalProfile,
  OfficeMember,
  OfficeMembersMeta,
  OfficeMonitorSchedulePolicy,
  OfficeOnboardingActionable,
  OfficeRole,
  OfficeSerproAuthorization,
  OfficeSubscription,
  OfficeTechnicalConsent,
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
      /**
       * Equipe do escritório corrente (membership ADMIN real).
       * Nunca envia office_id — escopo só via CurrentOffice.
       */
      members: {
        list: () =>
          client<{ data: OfficeMember[], meta: OfficeMembersMeta }>('/api/v1/office/members'),
        create: (body: CreateOfficeMemberBody) =>
          client<{ data: CreateOfficeMemberResult }>('/api/v1/office/members', {
            method: 'POST',
            body
          }),
        updateRole: (membershipId: number, body: { role: OfficeRole }) =>
          client<{ data: OfficeMember }>(`/api/v1/office/members/${membershipId}`, {
            method: 'PATCH',
            body
          }),
        updateRecipient: (
          membershipId: number,
          body: { name: string, email: string, method: ActivationMethod }
        ) =>
          client<{ data: CredentialDeliveryPayload }>(
            `/api/v1/office/members/${membershipId}/recipient`,
            { method: 'PATCH', body }
          ),
        deactivate: (membershipId: number) =>
          client<{ data: OfficeMember }>(`/api/v1/office/members/${membershipId}/deactivate`, {
            method: 'POST'
          }),
        reactivate: (membershipId: number, body?: { method?: ActivationMethod }) =>
          client<{ data: CredentialDeliveryPayload }>(
            `/api/v1/office/members/${membershipId}/reactivate`,
            { method: 'POST', body: body || {} }
          ),
        regenerateActivation: (membershipId: number, body: { method: ActivationMethod }) =>
          client<{ data: CredentialDeliveryPayload }>(
            `/api/v1/office/members/${membershipId}/activation/regenerate`,
            { method: 'POST', body }
          )
      },
      /**
       * Perfil institucional unificado (OpenSpec configuracao-escritorio-unificada).
       * Paths canônicos; se a API ainda não existir, a UI trata 404 com empty state.
       */
      profile: {
        show: () =>
          client<{ data: OfficeInstitutionalProfile }>('/api/v1/office/profile'),
        update: (body: {
          cnpj?: string
          legal_name?: string
          institutional_email?: string
          institutional_phone?: string
          /** Confirmação forte obrigatória na troca de CNPJ. */
          confirm_cnpj_change?: boolean
        }) =>
          client<{ data: OfficeInstitutionalProfile }>('/api/v1/office/profile', {
            method: 'PUT',
            body
          })
      },
      technicalConsent: {
        show: () =>
          client<{ data: OfficeTechnicalConsent }>('/api/v1/office/technical-consent'),
        accept: (body?: { version?: string }) =>
          client<{ data: OfficeTechnicalConsent }>('/api/v1/office/technical-consent', {
            method: 'POST',
            body: body || {}
          }),
        revoke: () =>
          client<{ data: OfficeTechnicalConsent }>('/api/v1/office/technical-consent/revoke', {
            method: 'POST',
            body: {}
          })
      },
      canonicalCredential: {
        show: () =>
          client<{ data: OfficeCanonicalCredential | null }>('/api/v1/office/canonical-credential'),
        upload: (pfx: File, password: string, options?: { password_confirmation?: string }) => {
          const body = new FormData()
          body.append('pfx', pfx)
          body.append('password', password)
          if (options?.password_confirmation) {
            body.append('password_confirmation', options.password_confirmation)
          }
          return client<{ data: OfficeCanonicalCredential }>(
            '/api/v1/office/canonical-credential',
            { method: 'POST', body }
          )
        },
        replace: (pfx: File, password: string, options?: {
          password_confirmation?: string
          reconfirm_password?: string
        }) => {
          const body = new FormData()
          body.append('pfx', pfx)
          body.append('password', password)
          if (options?.password_confirmation) {
            body.append('password_confirmation', options.password_confirmation)
          }
          if (options?.reconfirm_password) {
            body.append('reconfirm_password', options.reconfirm_password)
          }
          return client<{ data: OfficeCanonicalCredential }>(
            '/api/v1/office/canonical-credential/replace',
            { method: 'POST', body }
          )
        },
        remove: (body?: { confirm?: boolean, reconfirm_password?: string }) =>
          client<{ data: null }>('/api/v1/office/canonical-credential/remove', {
            method: 'POST',
            body: { confirm: true, ...body }
          })
      },
      monitorSchedules: {
        list: () =>
          client<{ data: OfficeMonitorSchedulePolicy[] }>('/api/v1/office/monitor-schedules'),
        update: (monitorKey: string, body: { day_of_month: number }) =>
          client<{ data: OfficeMonitorSchedulePolicy }>(
            `/api/v1/office/monitor-schedules/${encodeURIComponent(monitorKey)}`,
            { method: 'PUT', body }
          )
      },
      onboardingStatus: () =>
        client<{ data: OfficeOnboardingActionable }>('/api/v1/office/onboarding-status'),
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
        proxyPowers: (params?: {
          client_id?: number
          page?: number
          per_page?: number
          sort?: 'id' | 'client_id' | 'power_code' | 'system_code' | 'status'
          direction?: 'asc' | 'desc'
        }, options?: { signal?: AbortSignal }) =>
          client<{ data: TaxProxyPower[], meta: PageMeta }>('/api/v1/office/serpro-authorization/proxy-powers', {
            query: params,
            signal: options?.signal
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
        entries: (params?: {
          year?: number
          month?: number
          page?: number
          per_page?: number
          sort?: 'occurred_at' | 'quantity' | 'result' | 'client_id' | 'id'
          direction?: 'asc' | 'desc'
        }) =>
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
      overview: (
        params?: { page?: number, per_page?: number },
        options?: { signal?: AbortSignal }
      ) =>
        client<{ data: OfficeAutXmlOverview, meta: PageMeta }>('/api/v1/office/autxml', {
          query: params,
          signal: options?.signal
        }),
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
        client<{ data: OfficeAutXmlEnrollment }>('/api/v1/office/autxml/enrollments', {
          method: 'POST',
          body: { establishment_id: establishmentId }
        }),
      confirm: (enrollmentId: number) =>
        client<{ data: OfficeAutXmlEnrollment }>(
          `/api/v1/office/autxml/enrollments/${enrollmentId}/confirm`,
          { method: 'POST' }
        ),
      inactivate: (enrollmentId: number) =>
        client<{ data: OfficeAutXmlEnrollment }>(
          `/api/v1/office/autxml/enrollments/${enrollmentId}/inactivate`,
          { method: 'POST' }
        )
    }
  }
}
