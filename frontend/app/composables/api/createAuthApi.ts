import type {
  LoginResponse,
  MeResponse,
  TenantMembershipsPayload,
  TenantSwitchResult,
  TwoFactorQrCode
} from '~/types/api'
import type { ApiClient } from './types'

export function createAuthApi(client: ApiClient) {
  return {
    me: () => client<MeResponse>('/api/v1/me'),
    tenants: {
      memberships: () =>
        client<{ data: TenantMembershipsPayload }>('/api/v1/tenants/memberships'),
      switch: (officeId: number) =>
        client<{ data: TenantSwitchResult }>('/api/v1/tenants/switch', {
          method: 'POST',
          body: { office_id: officeId }
        })
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
