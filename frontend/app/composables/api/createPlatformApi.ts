import type {
  SerproCatalogEntry,
  SerproContractSanitized,
  SerproGlobalHealth,
  SerproKillSwitchStatus,
  SerproReadinessSnapshot,
  SerproRolloutState,
  SerproUsageConsolidation,
  SerproUsageReconciliation
} from '~/types/api'
import type { ApiClient } from './types'

/**
 * API global PLATFORM_ADMIN (prefixo /api/v1/platform/*).
 * Sem office context; respostas sanitizadas — nunca segredo/XML/vault id.
 *
 * Rotas de readiness/rollout além das existentes usam paths esperados pelo design;
 * se a API ainda não as expuser, a UI trata 404 com degradacao.
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
         * Rollout/canário (design). Path esperado; degradável se ausente.
         */
        rollout: {
          show: () =>
            client<{ data: SerproRolloutState }>('/api/v1/platform/serpro/rollout'),
          update: (body: Record<string, unknown>) =>
            client<{ data: SerproRolloutState }>('/api/v1/platform/serpro/rollout', {
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
