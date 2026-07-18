import type { MeResponse, MeUser, OfficeRole } from '~/types/api'

export type MeIdentity = MeUser | MeResponse | null | undefined

export function unwrapMeUser(identity: MeIdentity): MeUser | null {
  if (!identity) {
    return null
  }

  return 'data' in identity ? identity.data : identity
}

/**
 * ADMIN efetivo do office (papel efetivo). TOTP não é mais gate.
 */
export function hasConfirmedAdminAccess(user?: MeUser | null): boolean {
  return user?.role === 'ADMIN'
}

/** Flag global PLATFORM_ADMIN (sem membership fiscal implícita). */
export function isPlatformAdmin(user?: MeUser | null): boolean {
  return Boolean(user?.is_platform_admin)
}

/**
 * Área de plataforma `/admin/*` e console SERPRO.
 */
export function canAccessPlatformAdmin(user?: MeUser | null): boolean {
  return isPlatformAdmin(user)
}

/**
 * @deprecated Prefer `canAccessPlatformAdmin`.
 */
export function canAccessPlatformSerproConsole(user?: MeUser | null): boolean {
  return canAccessPlatformAdmin(user)
}

/** Contexto privilegiado ativo (seletor global com office resolvido). */
export function isPlatformPrivilegedContext(user?: MeUser | null): boolean {
  const office = user?.current_office ?? user?.office
  return isPlatformAdmin(user) && user?.access_mode === 'platform_privileged' && !!office
}

/**
 * Configuração do escritório (`/settings`): ADMIN efetivo, ou PLATFORM_ADMIN
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
  return roleCanMutate(user?.role)
}

/**
 * Papel real da membership (Work mutações).
 * Em membership (ou access_mode ausente legado) usa role; em privilegiado exige real_office_role.
 */
export function realOfficeRole(user?: MeUser | null): OfficeRole | null {
  if (user?.real_office_role) {
    return user.real_office_role
  }
  if (user?.access_mode === 'platform_privileged') {
    return user.has_real_membership ? (user.role ?? null) : null
  }
  // membership ou payload legado sem access_mode
  return user?.role ?? null
}

/**
 * Mutação Work no office corrente.
 * PLATFORM_ADMIN em contexto privilegiado atua com o papel efetivo (ADMIN).
 * Membership real: papel real da OfficeMembership.
 */
function hasRealWorkMutationAccess(user?: MeUser | null): boolean {
  if (isPlatformPrivilegedContext(user)) {
    return roleCanMutate(user?.role ?? 'ADMIN')
  }
  return roleCanMutate(realOfficeRole(user))
}

/**
 * Superfície de ADMIN do escritório (nav + ações).
 * Office ADMIN (efetivo/real) ou PLATFORM_ADMIN com office selecionado.
 */
export function hasOfficeAdminSurface(user?: MeUser | null): boolean {
  if (hasConfirmedAdminAccess(user)) return true
  if (realOfficeRole(user) === 'ADMIN') return true
  return isPlatformPrivilegedContext(user)
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
 * Somente ADMIN efetivo — senha recente é gate no backend.
 */
export function canExecuteHighRiskMutation(user?: MeUser | null): boolean {
  return hasConfirmedAdminAccess(user)
}

// ── Módulo operacional (Work) — ocultação UI ≠ autorização ────────────────
// Leitura: papel efetivo (inclui platform_privileged).
// Mutação/export: membership real.

export function canViewWork(user?: MeUser | null): boolean {
  return !!user?.role || isPlatformPrivilegedContext(user)
}

/** Acesso de leitura à área Fiscal (Monitoramento). */
export function canViewFiscal(user?: MeUser | null): boolean {
  return !!user?.role || isPlatformPrivilegedContext(user)
}

export function canManageWorkCatalog(user?: MeUser | null): boolean {
  return hasOfficeAdminSurface(user)
}

/**
 * Gestão de equipe do escritório (`/conta/equipe`).
 * Office ADMIN ou PLATFORM_ADMIN com office selecionado (paridade de superfície).
 */
export function canManageOfficeTeam(user?: MeUser | null): boolean {
  return hasOfficeAdminSurface(user)
}

export function canCreateWorkProcesses(user?: MeUser | null): boolean {
  return hasRealWorkMutationAccess(user)
}

export function canExecuteWorkTasks(user?: MeUser | null): boolean {
  return hasRealWorkMutationAccess(user)
}

export function canAdministerWork(user?: MeUser | null): boolean {
  return hasOfficeAdminSurface(user)
}

export function canExportWork(user?: MeUser | null): boolean {
  return hasRealWorkMutationAccess(user)
}

export function canDownloadWorkEvidence(user?: MeUser | null): boolean {
  return hasRealWorkMutationAccess(user)
}

export function isWorkOperator(user?: MeUser | null): boolean {
  const role = realOfficeRole(user) ?? user?.role
  return role === 'OPERATOR'
}
