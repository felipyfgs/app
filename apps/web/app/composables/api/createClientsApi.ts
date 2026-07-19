import type {
  Client,
  ClientContact,
  ClientCredential,
  CnpjLookupResult,
  CreateClientPayload,
  CreateClientResponse,
  Establishment,
  PageMeta
} from '~/types/api'
import type { ApiClient, ClientListParams } from './types'

export function createClientsApi(client: ApiClient) {
  return {
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
    }
  }
}
