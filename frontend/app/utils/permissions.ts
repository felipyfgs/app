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

/** Flag global PLATFORM_ADMIN (sem membership fiscal implícita). */
export function isPlatformAdmin(user?: MeUser | null): boolean {
  return Boolean(user?.is_platform_admin)
}

/**
 * Área de plataforma `/admin/*` e console SERPRO.
 * Navegação comum de PLATFORM_ADMIN NÃO exige TOTP global (OpenSpec 6.2 / 4.3).
 * Setup pendente de 2FA do Fortify ainda bloqueia o painel inteiro via middleware.
 */
export function canAccessPlatformAdmin(user?: MeUser | null): boolean {
  if (!isPlatformAdmin(user)) return false
  if (user?.requires_two_factor_setup) return false
  return true
}

/**
 * @deprecated Prefer `canAccessPlatformAdmin` — TOTP não é mais gate global de plataforma.
 * Mantido como alias para compatibilidade de imports de testes legados.
 */
export function canAccessPlatformSerproConsole(user?: MeUser | null): boolean {
  return canAccessPlatformAdmin(user)
}

/** Contexto privilegiado ativo (seletor global com office resolvido). */
export function isPlatformPrivilegedContext(user?: MeUser | null): boolean {
  return isPlatformAdmin(user) && user?.access_mode === 'platform_privileged' && !!user?.office
}

/**
 * Configuração do escritório (`/settings`): ADMIN com 2FA, ou PLATFORM_ADMIN
 * em contexto privilegiado com office selecionado.
 */
export function canAccessOfficeSettings(user?: MeUser | null): boolean {
  if (hasConfirmedAdminAccess(user)) return true
  return isPlatformPrivilegedContext(user)
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

/** Importação de XML de saída (mesmo perfil de mutação que export). */
export function canImportDocuments(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

/** Associação de categorias fiscais (ADMIN/OPERATOR). */
export function canAssociateCategories(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

/** Triagem interna da Caixa Postal (ADMIN/OPERATOR). */
export function canTriageMailbox(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

/**
 * Mutações fiscais de alto risco (emissão/transmissão).
 * Somente ADMIN com 2FA confirmado — alinhado a OfficeRole::canMutateFiscal.
 */
export function canExecuteHighRiskMutation(user?: MeUser | null): boolean {
  return hasConfirmedAdminAccess(user)
}

// ── Módulo operacional (Work) — ocultação UI ≠ autorização ────────────────

export function canViewWork(user?: MeUser | null): boolean {
  return !!user?.role
}

export function canManageWorkCatalog(user?: MeUser | null): boolean {
  return hasConfirmedAdminAccess(user)
}

export function canCreateWorkProcesses(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

export function canExecuteWorkTasks(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

export function canAdministerWork(user?: MeUser | null): boolean {
  return hasConfirmedAdminAccess(user)
}

export function canExportWork(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

export function canDownloadWorkEvidence(user?: MeUser | null): boolean {
  return hasMutationAccess(user)
}

export function isWorkOperator(user?: MeUser | null): boolean {
  return user?.role === 'OPERATOR'
}
