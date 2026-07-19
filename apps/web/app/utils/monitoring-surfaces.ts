/**
 * Espelho TypeScript da page-payload-matrix (change tornar-monitoramento-fiscal-confiavel).
 * result_kind e política de documento — sem coordenadas SERPRO no contrato público.
 * Fonte canônica backend: MonitoringSurfaceRegistry.
 */

export type MonitoringResultKind
  = | 'STRUCTURED'
    | 'PDF'
    | 'ASYNC_PDF'
    | 'AGGREGATE'
    | 'UNAVAILABLE'

export type DocumentUnavailableReason
  = | 'STRUCTURED_ONLY'
    | 'PROCESSING'
    | 'NOT_SUPPORTED'
    | 'NOT_PRODUCTION'
    | 'NOT_COLLECTED'

/** Descritor público de evidência (DTO backend FiscalDocumentDescriptorDto). */
export interface FiscalEvidenceDocument {
  available: boolean
  kind?: string | null
  label?: string | null
  content_type?: string | null
  observed_at?: string | null
  source_surface?: string | null
  source_label?: string | null
  href?: string | null
  unavailable_reason?: DocumentUnavailableReason | string | null
}

export interface MonitoringSurfaceMatrixEntry {
  surfaceKey: string
  route: string
  resultKind: MonitoringResultKind
  /** true somente quando a superfície pode publicar href com artefato real. */
  allowsDocument: boolean
  /** Superfícies que NUNCA mostram ação de documento (matriz). */
  neverDocumentAction: boolean
}

/**
 * Todas as surface_key públicas da matriz de monitoramento.
 * Ordem alinhada ao MonitoringSurfaceRegistry backend.
 */
export const MONITORING_SURFACE_MATRIX: readonly MonitoringSurfaceMatrixEntry[] = [
  {
    surfaceKey: 'monitoring_dashboard',
    route: '/monitoring',
    resultKind: 'AGGREGATE',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'simples_mei_pgdasd',
    route: '/monitoring/simples-mei',
    resultKind: 'PDF',
    allowsDocument: true,
    neverDocumentAction: false
  },
  {
    surfaceKey: 'simples_mei_pgmei',
    route: '/monitoring/simples-mei',
    resultKind: 'STRUCTURED',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'dctfweb',
    route: '/monitoring/dctfweb',
    resultKind: 'PDF',
    allowsDocument: true,
    neverDocumentAction: false
  },
  {
    surfaceKey: 'mit',
    route: '/monitoring/dctfweb',
    resultKind: 'STRUCTURED',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'fgts',
    route: '/monitoring/fgts',
    resultKind: 'STRUCTURED',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'installments',
    route: '/monitoring/installments',
    resultKind: 'PDF',
    allowsDocument: true,
    neverDocumentAction: false
  },
  {
    surfaceKey: 'sitfis',
    route: '/monitoring/sitfis',
    resultKind: 'ASYNC_PDF',
    allowsDocument: true,
    neverDocumentAction: false
  },
  {
    surfaceKey: 'mailbox_list',
    route: '/monitoring/mailbox',
    resultKind: 'STRUCTURED',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'mailbox_detail',
    route: '/monitoring/mailbox/:id',
    resultKind: 'STRUCTURED',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'declarations',
    route: '/monitoring/declarations',
    resultKind: 'AGGREGATE',
    allowsDocument: true,
    neverDocumentAction: false
  },
  {
    surfaceKey: 'guides',
    route: '/monitoring/guides',
    resultKind: 'PDF',
    allowsDocument: true,
    neverDocumentAction: false
  },
  {
    surfaceKey: 'registrations',
    route: '/monitoring/registrations',
    resultKind: 'STRUCTURED',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'tax_processes',
    route: '/monitoring/tax-processes',
    resultKind: 'STRUCTURED',
    allowsDocument: false,
    neverDocumentAction: true
  },
  {
    surfaceKey: 'client_detail',
    route: '/monitoring/clients/:clientId',
    resultKind: 'AGGREGATE',
    allowsDocument: false,
    neverDocumentAction: true
  }
] as const

export const MONITORING_SURFACE_KEYS: readonly string[]
  = MONITORING_SURFACE_MATRIX.map(e => e.surfaceKey)

/** MIT, Caixa Postal, Cadastros, e-Processo — sem botão de documento. */
export const STRUCTURED_ONLY_SURFACE_KEYS = [
  'mit',
  'mailbox_list',
  'mailbox_detail',
  'registrations',
  'tax_processes'
] as const

export const ALL_RESULT_KINDS: readonly MonitoringResultKind[] = [
  'STRUCTURED',
  'PDF',
  'ASYNC_PDF',
  'AGGREGATE'
] as const

export function getMonitoringSurface(surfaceKey: string): MonitoringSurfaceMatrixEntry | undefined {
  return MONITORING_SURFACE_MATRIX.find(e => e.surfaceKey === surfaceKey)
}

/**
 * Botão/href de documento só com artefato real: available && href não vazio.
 * A UI NÃO fabrica URL a partir do nome do módulo.
 */
export function documentActionVisible(
  document: Pick<FiscalEvidenceDocument, 'available' | 'href'> | null | undefined
): boolean {
  if (document == null) return false
  if (!document.available) return false
  const href = typeof document.href === 'string' ? document.href.trim() : ''
  return href.length > 0
}

/** Superfícies da matriz que nunca exibem ação de documento. */
export function surfaceNeverShowsDocumentAction(surfaceKey: string): boolean {
  const entry = getMonitoringSurface(surfaceKey)
  if (entry) return entry.neverDocumentAction
  return true
}

/**
 * Helper tipado: MIT / mailbox / cadastros / e-Processo nunca mostram ação.
 */
export function isStructuredOnlySurface(surfaceKey: string): boolean {
  return (STRUCTURED_ONLY_SURFACE_KEYS as readonly string[]).includes(surfaceKey)
}

/**
 * Fail-closed: se a superfície proíbe documento, a ação fica oculta mesmo
 * com descriptor inconsistente (available/href presentes por bug).
 */
export function documentActionVisibleForSurface(
  surfaceKey: string,
  document: Pick<FiscalEvidenceDocument, 'available' | 'href'> | null | undefined
): boolean {
  if (surfaceNeverShowsDocumentAction(surfaceKey)) return false
  return documentActionVisible(document)
}

export function resultKindsInMatrix(): MonitoringResultKind[] {
  const set = new Set<MonitoringResultKind>()
  for (const e of MONITORING_SURFACE_MATRIX) {
    set.add(e.resultKind)
  }
  return [...set]
}
