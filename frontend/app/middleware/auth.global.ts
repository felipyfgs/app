import {
  canAccessPlatformSerproConsole,
  hasConfirmedAdminAccess,
  unwrapMeUser
} from '~/utils/permissions'
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
    if (guestOnly) return undefined
    // Deep-link seguro: login.vue aplica safeRedirectTarget na query redirect.
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath }
    })
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

  // CT-e não é Configurações: alias legado → catálogo filtrado (sem layout Settings).
  if (to.path.replace(/\/+$/, '') === '/settings/cte') {
    const accepted = new Set([
      'kind',
      'direction',
      'q',
      'client_id',
      'establishment_id',
      'fiscal_role',
      'acquisition_source',
      'artifact_quality',
      'coverage_status',
      'status',
      'competence',
      'issued_from',
      'issued_to',
      'missing_party_name',
      'issuer_cnpj',
      'taker_cnpj'
    ])
    const query: Record<string, string> = { kind: 'CTE' }
    for (const [key, raw] of Object.entries(to.query)) {
      if (!accepted.has(key) || key === 'kind') continue
      const value = Array.isArray(raw) ? raw[0] : raw
      if (typeof value === 'string' && value) query[key] = value
    }
    return navigateTo({ path: '/docs/catalog', query }, { replace: true })
  }

  // Console global SERPRO: PLATFORM_ADMIN (+ TOTP) ou office ADMIN (shell com denied).
  const isPlatformSerproPath = to.path === '/admin/serpro' || to.path.startsWith('/admin/serpro/')
  if (isPlatformSerproPath) {
    if (canAccessPlatformSerproConsole(identity) || hasConfirmedAdminAccess(identity)) {
      return undefined
    }
    return navigateTo('/')
  }

  // Gate normal: /settings e /admin exigem ADMIN com 2FA confirmado.
  if (
    (to.path.startsWith('/admin') || to.path.startsWith('/settings'))
    && !hasConfirmedAdminAccess(identity)
  ) {
    return navigateTo('/')
  }
})
