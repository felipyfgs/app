import {
  canAccessOfficeSettings,
  canAccessPlatformAdmin,
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

  // Procurações manuais removidas do tenant — redireciona para settings unificado.
  if (to.path.replace(/\/+$/, '') === '/settings/proxies') {
    return navigateTo('/settings', { replace: true })
  }

  // `/admin/*` reservado à plataforma (PLATFORM_ADMIN).
  // Exceção: departamentos do módulo Trabalho permanecem para ADMIN de office.
  const isAdminPath = to.path === '/admin' || to.path.startsWith('/admin/')
  const isDepartmentsPath = to.path === '/admin/departments' || to.path.startsWith('/admin/departments/')
  if (isAdminPath) {
    if (isDepartmentsPath) {
      if (hasConfirmedAdminAccess(identity) || canAccessPlatformAdmin(identity)) {
        return undefined
      }
      return navigateTo('/')
    }
    // Console e hub de plataforma: sem TOTP global.
    if (canAccessPlatformAdmin(identity)) {
      return undefined
    }
    // Office ADMIN que ainda aponte para /admin → settings unificado.
    if (hasConfirmedAdminAccess(identity)) {
      return navigateTo('/settings', { replace: true })
    }
    return navigateTo('/')
  }

  // Configuração do escritório: ADMIN office (2FA) ou PLATFORM_ADMIN privilegiado.
  if (to.path.startsWith('/settings') && !canAccessOfficeSettings(identity)) {
    return navigateTo('/')
  }
})
