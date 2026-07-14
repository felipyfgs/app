import type { MeResponse, MeUser, OfficeRole } from '~/types/api'

export type MeIdentity = MeUser | MeResponse | null | undefined

export function unwrapMeUser(identity: MeIdentity): MeUser | null {
  if (!identity) {
    return null
  }

  return 'data' in identity ? identity.data : identity
}

export function hasConfirmedAdminAccess(user?: MeUser | null): boolean {
  return user?.role === 'ADMIN'
    && (!user.two_factor_required || user.two_factor_confirmed)
    && !user.requires_two_factor_setup
}

function roleCanMutate(role?: OfficeRole | null): boolean {
  return role === 'ADMIN' || role === 'OPERATOR'
}

function hasMutationAccess(user?: MeUser | null): boolean {
  if (!roleCanMutate(user?.role)) {
    return false
  }

  return user?.role !== 'ADMIN' || hasConfirmedAdminAccess(user)
}

export function canManageClients(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

export function canManageCredentials(user?: MeUser | null): boolean {
  return hasConfirmedAdminAccess(user)
}

export function canTriggerSync(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

export function canCreateExport(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}
