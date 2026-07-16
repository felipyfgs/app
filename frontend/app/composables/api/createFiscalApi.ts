import type {
  FgtsCoverageManifest,
  FiscalCategory,
  FiscalFinding,
  FiscalMonitoringRun,
  FiscalMutationOperation,
  FiscalMutationPreflight,
  FiscalPendingItem,
  FiscalSnapshot,
  PageMeta
} from '~/types/api'
import type {
  FiscalModuleClientsPage,
  FiscalModuleOverviewResponse,
  FiscalModulePortfolioFilters,
  FiscalPortfolioModuleKey
} from '~/types/fiscal-modules'
import type { ApiClient, ApiUrl } from './types'

export function createFiscalApi(client: ApiClient, apiUrl: ApiUrl) {
  return {
    fiscal: {
      categories: () =>
        client<{ data: FiscalCategory[] }>('/api/v1/fiscal/categories'),
      categoryLinks: {
        list: (params?: { client_id?: number, status?: string }) =>
          client<{ data: Array<Record<string, unknown>> }>('/api/v1/fiscal/category-links', {
            query: params
          }),
        associate: (body: {
          client_id: number
          fiscal_category_id: number
          coverage?: string
          status?: string
          notes?: string
        }) =>
          client<{ data: Record<string, unknown> }>('/api/v1/fiscal/category-links', {
            method: 'POST',
            body
          }),
        associateBatch: (body: {
          fiscal_category_id: number
          client_ids: number[]
          coverage?: string
        }) =>
          client<{ data: { created?: number, updated?: number, errors?: unknown[] } }>(
            '/api/v1/fiscal/category-links/batch',
            { method: 'POST', body }
          )
      },
      /**
       * Read model de carteira por módulo (overview + clients).
       * office_id só via membership ativa no backend — nunca enviar no query.
       */
      modules: {
        overview: <M extends FiscalPortfolioModuleKey>(module: M, params?: FiscalModulePortfolioFilters) =>
          client<FiscalModuleOverviewResponse<M>>(
            `/api/v1/fiscal/modules/${encodeURIComponent(module)}/overview`,
            { query: params }
          ),
        clients: <M extends FiscalPortfolioModuleKey>(module: M, params?: FiscalModulePortfolioFilters) =>
          client<FiscalModuleClientsPage<M>>(
            `/api/v1/fiscal/modules/${encodeURIComponent(module)}/clients`,
            { query: params }
          )
      },
      runs: {
        list: (params?: {
          page?: number
          per_page?: number
          client_id?: number
          status?: string
          system_code?: string
        }) =>
          client<{ data: FiscalMonitoringRun[], meta?: PageMeta, current_page?: number, last_page?: number, total?: number, per_page?: number }>(
            '/api/v1/fiscal/runs',
            { query: params }
          ),
        get: (id: number) =>
          client<{ data: FiscalMonitoringRun }>(`/api/v1/fiscal/runs/${id}`),
        create: (body: Record<string, unknown>) =>
          client<{ data: FiscalMonitoringRun }>('/api/v1/fiscal/runs', { method: 'POST', body })
      },
      snapshots: {
        list: (params?: {
          page?: number
          per_page?: number
          client_id?: number
          current_only?: boolean | 0 | 1
          situation?: string
        }) =>
          client<{ data: FiscalSnapshot[], meta?: PageMeta, current_page?: number, last_page?: number, total?: number, per_page?: number }>(
            '/api/v1/fiscal/snapshots',
            { query: params }
          ),
        get: (id: number) =>
          client<{ data: FiscalSnapshot }>(`/api/v1/fiscal/snapshots/${id}`)
      },
      findings: (params?: {
        page?: number
        per_page?: number
        client_id?: number
        active_only?: boolean | 0 | 1
      }) =>
        client<{ data: FiscalFinding[], meta?: PageMeta, current_page?: number, last_page?: number, total?: number, per_page?: number }>(
          '/api/v1/fiscal/findings',
          { query: params }
        ),
      pending: (params?: {
        page?: number
        per_page?: number
        client_id?: number
        status?: string
        situation?: string
      }) =>
        client<{ data: FiscalPendingItem[], meta?: PageMeta, current_page?: number, last_page?: number, total?: number, per_page?: number }>(
          '/api/v1/fiscal/pending-items',
          { query: params }
        ),
      evidenceDownloadUrl: (id: number) =>
        apiUrl(`/api/v1/fiscal/evidence/${id}/download`),
      dctfweb: {
        list: (params?: { page?: number, per_page?: number, client_id?: number }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta, current_page?: number, last_page?: number, total?: number }>(
            '/api/v1/fiscal/dctfweb/declarations',
            { query: params }
          ),
        get: (id: number) =>
          client<{ data: Record<string, unknown>, evidence_versions?: Array<Record<string, unknown>> }>(
            `/api/v1/fiscal/dctfweb/declarations/${id}`
          ),
        consult: (body: Record<string, unknown>) =>
          client<{ data: unknown }>('/api/v1/fiscal/dctfweb/consult', { method: 'POST', body }),
        transmit: (body: Record<string, unknown>) =>
          client<{ data: unknown }>('/api/v1/fiscal/dctfweb/transmit', { method: 'POST', body })
      },
      mit: {
        list: (params?: { page?: number, per_page?: number, client_id?: number }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta, current_page?: number, last_page?: number, total?: number }>(
            '/api/v1/fiscal/mit/apuracoes',
            { query: params }
          ),
        get: (id: number) =>
          client<{ data: Record<string, unknown> }>(`/api/v1/fiscal/mit/apuracoes/${id}`),
        consult: (body: Record<string, unknown>) =>
          client<{ data: unknown }>('/api/v1/fiscal/mit/consult', { method: 'POST', body }),
        encerrar: (body: Record<string, unknown>) =>
          client<{ data: unknown }>('/api/v1/fiscal/mit/encerrar', { method: 'POST', body })
      },
      installments: {
        modalities: () =>
          client<{ data: Array<Record<string, unknown>> }>('/api/v1/fiscal/installments/modalities'),
        orders: (params?: { page?: number, per_page?: number, client_id?: number }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta, current_page?: number, last_page?: number, total?: number }>(
            '/api/v1/fiscal/installments/orders',
            { query: params }
          ),
        order: (id: number) =>
          client<{ data: Record<string, unknown> }>(`/api/v1/fiscal/installments/orders/${id}`),
        parcels: (params?: { page?: number, per_page?: number, order_id?: number }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta }>('/api/v1/fiscal/installments/parcels', {
            query: params
          }),
        guides: (params?: { page?: number, per_page?: number, client_id?: number }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta }>('/api/v1/fiscal/installments/guides', {
            query: params
          }),
        enqueue: (body: Record<string, unknown>) =>
          client<{ data: unknown }>('/api/v1/fiscal/installments/runs', { method: 'POST', body })
      },
      sitfis: {
        show: (clientId: number) =>
          client<{ data: Record<string, unknown> }>('/api/v1/fiscal/sitfis', {
            query: { client_id: clientId }
          }),
        refresh: (body: { client_id: number, force?: boolean }) =>
          client<{ data: Record<string, unknown> }>('/api/v1/fiscal/sitfis/refresh', {
            method: 'POST',
            body
          })
      },
      mailbox: {
        list: (params?: {
          page?: number
          per_page?: number
          client_id?: number
          triage_status?: string
        }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta, current_page?: number, last_page?: number, total?: number }>(
            '/api/v1/fiscal/mailbox/messages',
            { query: params }
          ),
        get: (id: number) =>
          client<{ data: Record<string, unknown>, meta?: Record<string, unknown> }>(
            `/api/v1/fiscal/mailbox/messages/${id}`
          ),
        triage: (id: number, body: { triage_status: string, note?: string }) =>
          client<{ data: Record<string, unknown>, meta?: Record<string, unknown> }>(
            `/api/v1/fiscal/mailbox/messages/${id}/triage`,
            { method: 'PATCH', body }
          ),
        state: (params?: { client_id?: number }) =>
          client<{ data: Record<string, unknown> }>('/api/v1/fiscal/mailbox/state', { query: params }),
        alerts: (params?: { client_id?: number }) =>
          client<{ data: Array<Record<string, unknown>> }>('/api/v1/fiscal/mailbox/alerts', { query: params }),
        bodyDownloadUrl: (id: number) =>
          apiUrl(`/api/v1/fiscal/mailbox/messages/${id}/body`),
        attachmentDownloadUrl: (messageId: number, attachmentId: number) =>
          apiUrl(`/api/v1/fiscal/mailbox/messages/${messageId}/attachments/${attachmentId}`)
      },
      declarations: {
        catalog: () =>
          client<{ data: Array<Record<string, unknown>> }>('/api/v1/fiscal/declarations/catalog'),
        summary: (params?: { client_id?: number }) =>
          client<{ data: Record<string, unknown> }>('/api/v1/fiscal/declarations/summary', { query: params }),
        list: (params?: {
          page?: number
          per_page?: number
          client_id?: number
          status?: string
          competence?: string
        }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta, current_page?: number, last_page?: number, total?: number }>(
            '/api/v1/fiscal/declarations',
            { query: params }
          ),
        get: (id: number) =>
          client<{ data: Record<string, unknown> }>(`/api/v1/fiscal/declarations/${id}`)
      },
      guides: {
        list: (params?: {
          page?: number
          per_page?: number
          client_id?: number
          status?: string
          payment_status?: string
          competence?: string
        }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta, current_page?: number, last_page?: number, total?: number }>(
            '/api/v1/fiscal/guides',
            { query: params }
          ),
        get: (id: number) =>
          client<{ data: Record<string, unknown> }>(`/api/v1/fiscal/guides/${id}`),
        preflight: (body: Record<string, unknown>) =>
          client<{ data: Record<string, unknown> }>('/api/v1/fiscal/guides/preflight', {
            method: 'POST',
            body
          }),
        issueDownloadToken: (id: number) =>
          client<{
            data: {
              token: string
              expires_at?: string
              version_id?: number
              download_path: string
            }
          }>(`/api/v1/fiscal/guides/${id}/download-token`, { method: 'POST' }),
        downloadUrl: (token: string) =>
          apiUrl(`/api/v1/fiscal/guides/downloads/${encodeURIComponent(token)}`)
      },
      simplesMei: {
        catalog: () =>
          client<{ data: Array<Record<string, unknown>> }>('/api/v1/fiscal/simples-mei/catalog'),
        regimes: (clientId: number) =>
          client<{ data: Array<Record<string, unknown>> }>(
            `/api/v1/fiscal/simples-mei/clients/${clientId}/regimes`
          ),
        competences: (clientId: number) =>
          client<{ data: Array<Record<string, unknown>> }>(
            `/api/v1/fiscal/simples-mei/clients/${clientId}/competences`
          ),
        snapshots: (clientId: number, params?: { page?: number, per_page?: number }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta }>(
            `/api/v1/fiscal/simples-mei/clients/${clientId}/snapshots`,
            { query: params }
          ),
        guideStubs: (clientId: number) =>
          client<{ data: Array<Record<string, unknown>> }>(
            `/api/v1/fiscal/simples-mei/clients/${clientId}/guide-stubs`
          ),
        consult: (body: Record<string, unknown>) =>
          client<{ data: unknown }>('/api/v1/fiscal/simples-mei/consult', { method: 'POST', body })
      },
      fgts: {
        coverage: () =>
          client<{ data: FgtsCoverageManifest }>('/api/v1/fiscal/fgts/coverage'),
        competences: (params?: {
          page?: number
          per_page?: number
          client_id?: number
          competence_period_key?: string
        }) =>
          client<{ data: Array<Record<string, unknown>>, meta?: PageMeta, current_page?: number, last_page?: number, total?: number }>(
            '/api/v1/fiscal/fgts/competences',
            { query: params }
          ),
        competence: (id: number) =>
          client<{
            data: Record<string, unknown>
            events?: Array<Record<string, unknown>>
            coverage?: FgtsCoverageManifest
          }>(`/api/v1/fiscal/fgts/competences/${id}`),
        events: (params?: {
          page?: number
          per_page?: number
          client_id?: number
          competence_period_key?: string
          event_code?: string
        }) =>
          client<{
            data: Array<Record<string, unknown>>
            meta?: PageMeta
            coverage?: Record<string, unknown>
          }>('/api/v1/fiscal/fgts/events', { query: params }),
        sync: (body: Record<string, unknown>) =>
          client<{ data: unknown }>('/api/v1/fiscal/fgts/sync', { method: 'POST', body })
      },
      mutations: {
        confirmTotp: (code: string) =>
          client<{ data: { confirmed: boolean, window_minutes?: number, seconds_remaining?: number } }>(
            '/api/v1/auth/confirm-totp',
            { method: 'POST', body: { code } }
          ),
        preflight: (body: Record<string, unknown>) =>
          client<{ data: FiscalMutationPreflight }>('/api/v1/fiscal/mutations/preflight', {
            method: 'POST',
            body
          }),
        execute: (body: Record<string, unknown>) =>
          client<{ data: FiscalMutationOperation }>('/api/v1/fiscal/mutations', {
            method: 'POST',
            body
          }),
        get: (id: number) =>
          client<{ data: FiscalMutationOperation }>(`/api/v1/fiscal/mutations/${id}`)
      }
    }
  }
}
