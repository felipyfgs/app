import { hasConfirmedAdminAccess, unwrapMeUser } from '~/utils/permissions'
import type { MeIdentity } from '~/utils/permissions'

export default defineNuxtRouteMiddleware(async (to) => {
  const { isAuthenticated, refreshIdentity, user } = useSanctumAuth()
  const guestOnly = to.path === '/login' || to.path === '/two-factor-challenge'

  if (!guestOnly || isAuthenticated.value) {
    try {
      await refreshIdentity()
    } catch {
      // A ausência de sessão é tratada logo abaixo sem revelar o motivo.
      user.value = null
    }
  }

  if (!isAuthenticated.value) {
    return guestOnly ? undefined : navigateTo('/login')
  }

  const identity = unwrapMeUser(user.value as MeIdentity)
  const requiresSetup = identity?.requires_two_factor_setup === true

  if (guestOnly) {
    return navigateTo(requiresSetup ? '/two-factor/setup' : '/')
  }

  if (requiresSetup && to.path !== '/two-factor/setup') {
    return navigateTo('/two-factor/setup')
  }

  if (!requiresSetup && to.path === '/two-factor/setup') {
    return navigateTo('/')
  }

  if (to.path.startsWith('/admin') && !hasConfirmedAdminAccess(identity)) {
    return navigateTo('/')
  }
})
