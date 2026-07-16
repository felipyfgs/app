import type {
  ActivationMethod,
  CreatePlatformAdminBody,
  CreatePlatformAdminResult,
  CreatePlatformOfficeBody,
  CreatePlatformOfficeResult,
  CredentialDeliveryPayload,
  PlatformAdminUser,
  PlatformOfficeAdminDetail,
  PlatformOfficeAdminSummary,
  PlatformOfficeSelectResult,
  PlatformOfficesEnvelope,
  SerproCatalogEntry,
  SerproContractSanitized,
  SerproGlobalHealth,
  SerproKillSwitchStatus,
  SerproReadinessSnapshot,
  SerproUsageConsolidation,
  SerproUsageReconciliation
} from '~/types/api'
import type { ApiClient } from './types'

/**
 * API global PLATFORM_ADMIN (prefixo /api/v1/platform/*).
 * Sem office context; respostas sanitizadas — nunca segredo/XML/vault id.
 *
 * Paths alinhados às rotas Laravel em /api/v1/platform/* (sem inventar singular
 * quando a API só expõe o plural, ex.: /serpro/rollouts).
 */
export function createPlatformApi(client: ApiClient) {
  return {
    platform: {
      tenants: {
        list: (params?: { page?: number, per_page?: number }) =>
          client<{ data: Array<Record<string, unknown>> }>('/api/v1/platform/tenants', { query: params }),
        show: (officeId: number) =>
          client<{ data: Record<string, unknown> }>(`/api/v1/platform/tenants/${officeId}`)
      },
      /**
       * Seletor global de escritórios (PLATFORM_ADMIN).
       * Seleção privilegiada — não cria membership nem altera selected_office_id.
       * Administração (criação/pendentes): adminList / create / show / regenerate / updateFirstAdmin.
       */
      offices: {
        list: (params?: { page?: number, per_page?: number, q?: string }) =>
          client<{ data: PlatformOfficesEnvelope }>('/api/v1/platform/offices', {
            query: params
          }),
        select: (officeId: number) =>
          client<{ data: PlatformOfficeSelectResult }>('/api/v1/platform/offices/select', {
            method: 'POST',
            body: { office_id: officeId }
          }),
        clear: () =>
          client<{ data: { access_mode: string | null } }>('/api/v1/platform/offices/select', {
            method: 'DELETE'
          }),
        /** Lista admin (inclui PENDING_ACTIVATION). */
        adminList: (params?: { lifecycle_status?: string }) =>
          client<{ data: PlatformOfficeAdminSummary[] }>('/api/v1/platform/offices/admin', {
            query: params
          }),
        create: (body: CreatePlatformOfficeBody) =>
          client<{ data: CreatePlatformOfficeResult }>('/api/v1/platform/offices', {
            method: 'POST',
            body
          }),
        show: (officeId: number) =>
          client<{ data: PlatformOfficeAdminDetail }>(`/api/v1/platform/offices/${officeId}`),
        regenerateActivation: (officeId: number, body: { method: ActivationMethod }) =>
          client<{ data: CredentialDeliveryPayload }>(
            `/api/v1/platform/offices/${officeId}/activation/regenerate`,
            { method: 'POST', body }
          ),
        updateFirstAdmin: (
          officeId: number,
          body: { name: string, email: string, method: ActivationMethod }
        ) =>
          client<{ data: CredentialDeliveryPayload }>(
            `/api/v1/platform/offices/${officeId}/first-admin`,
            { method: 'PATCH', body }
          )
      },
      /** Administradores globais PLATFORM_ADMIN. */
      admins: {
        list: () =>
          client<{ data: PlatformAdminUser[] }>('/api/v1/platform/admins'),
        show: (userId: number) =>
          client<{ data: PlatformAdminUser }>(`/api/v1/platform/admins/${userId}`),
        create: (body: CreatePlatformAdminBody) =>
          client<{ data: CreatePlatformAdminResult }>('/api/v1/platform/admins', {
            method: 'POST',
            body
          }),
        update: (
          userId: number,
          body: {
            name: string
            email: string
            method: ActivationMethod
            default_office_id?: number | null
          }
        ) =>
          client<{ data: CredentialDeliveryPayload }>(`/api/v1/platform/admins/${userId}`, {
            method: 'PATCH',
            body
          }),
        regenerateActivation: (userId: number, body: { method: ActivationMethod }) =>
          client<{ data: CredentialDeliveryPayload }>(
            `/api/v1/platform/admins/${userId}/activation/regenerate`,
            { method: 'POST', body }
          )
      },
      serpro: {
        contracts: {
          list: (params?: { environment?: string }) =>
            client<{ data: SerproContractSanitized[] }>('/api/v1/platform/serpro/contracts', {
              query: params
            }),
          show: (id: number) =>
            client<{ data: SerproContractSanitized }>(`/api/v1/platform/serpro/contracts/${id}`),
          store: (body: FormData | Record<string, unknown>) =>
            client<{ data: SerproContractSanitized }>('/api/v1/platform/serpro/contracts', {
              method: 'POST',
              body
            }),
          activate: (id: number, body?: { replace?: boolean }) =>
            client<{ data: SerproContractSanitized }>(
              `/api/v1/platform/serpro/contracts/${id}/activate`,
              { method: 'POST', body: body || {} }
            ),
          deactivate: (id: number, body?: { reason?: string }) =>
            client<{ data: SerproContractSanitized }>(
              `/api/v1/platform/serpro/contracts/${id}/deactivate`,
              { method: 'POST', body: body || {} }
            ),
          block: (id: number, body: { reason: string }) =>
            client<{ data: SerproContractSanitized }>(
              `/api/v1/platform/serpro/contracts/${id}/block`,
              { method: 'POST', body }
            )
        },
        health: (params?: { environment?: string }) =>
          client<{ data: SerproGlobalHealth }>('/api/v1/platform/serpro/health', { query: params }),
        /**
         * Readiness read-only (design 8.1/9.1). Fallback path; se 404, UI deriva do health.
         */
        readiness: (params?: { environment?: string }) =>
          client<{ data: SerproReadinessSnapshot }>('/api/v1/platform/serpro/readiness', {
            query: params
          }),
        catalog: (params?: { environment?: string }) =>
          client<{ data: SerproCatalogEntry[] }>('/api/v1/platform/serpro/catalog', {
            query: params
          }),
        killSwitch: {
          status: () =>
            client<{ data: SerproKillSwitchStatus }>('/api/v1/platform/serpro/kill-switch'),
          set: (body: { active: boolean, reason: string, solution?: string }) =>
            client<{ data: SerproKillSwitchStatus }>('/api/v1/platform/serpro/kill-switch', {
              method: 'POST',
              body
            })
        },
        breakerReset: (body: { reason: string }) =>
          client<{ data: Record<string, unknown> }>('/api/v1/platform/serpro/breaker/reset', {
            method: 'POST',
            body
          }),
        /**
         * Aprovações de rollout (quatro olhos) — API real: /serpro/rollouts.
         * Snapshot de smoke/canário não existe como GET singular; a UI deriva de health/readiness.
         */
        rollouts: {
          list: (params?: { status?: string }) =>
            client<{ data: Array<Record<string, unknown>> }>('/api/v1/platform/serpro/rollouts', {
              query: params
            }),
          request: (body: Record<string, unknown>) =>
            client<{ data: Record<string, unknown> }>('/api/v1/platform/serpro/rollouts', {
              method: 'POST',
              body
            })
        },
        /**
         * Orçamentos globais (design). Path esperado; degradável se ausente.
         */
        budgets: {
          show: (params?: { year?: number, month?: number }) =>
            client<{ data: Record<string, unknown> }>('/api/v1/platform/serpro/budgets', {
              query: params
            })
        },
        usage: {
          consolidation: (params?: { year?: number, month?: number, recompute?: boolean }) =>
            client<{ data: SerproUsageConsolidation }>(
              '/api/v1/platform/serpro-usage/consolidation',
              { query: params }
            ),
          recompute: (body: { year: number, month: number, office_id?: number | null }) =>
            client<{ data: Record<string, unknown> }>('/api/v1/platform/serpro-usage/recompute', {
              method: 'POST',
              body
            }),
          registerReconciliation: (body: Record<string, unknown>) =>
            client<{ data: SerproUsageReconciliation }>(
              '/api/v1/platform/serpro-usage/reconciliations',
              { method: 'POST', body }
            )
        }
      }
    }
  }
}
