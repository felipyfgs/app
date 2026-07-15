/**
 * Vocabulário visual/acessível de situação fiscal (15.8).
 * Distingue por texto, ícone e tom — nunca só por cor.
 * Fonte canônica: backend FiscalSituation enum.
 */

export type FiscalSituationCode
  = | 'UP_TO_DATE'
    | 'PENDING'
    | 'PROCESSING'
    | 'ATTENTION'
    | 'ERROR'
    | 'NOT_APPLICABLE'
    | 'UNKNOWN'
    | 'UNSUPPORTED'
    | 'BLOCKED'

export type FiscalStatusTone = 'success' | 'warning' | 'error' | 'info' | 'neutral'

export interface FiscalStatusMeta {
  code: FiscalSituationCode | string
  label: string
  description: string
  icon: string
  color: FiscalStatusTone
  /** Origem / limitação quando estado não é “em dia”. */
  sourceHint?: string
}

const CATALOG: Record<FiscalSituationCode, Omit<FiscalStatusMeta, 'code'>> = {
  UP_TO_DATE: {
    label: 'Em dia',
    description: 'Situação atualizada com evidência oficial positiva.',
    icon: 'i-lucide-circle-check',
    color: 'success'
  },
  PENDING: {
    label: 'Pendente',
    description: 'Há obrigação ou consulta pendente de resolução.',
    icon: 'i-lucide-circle-dashed',
    color: 'warning'
  },
  PROCESSING: {
    label: 'Processando',
    description: 'Consulta ou sincronização em andamento.',
    icon: 'i-lucide-loader-circle',
    color: 'info'
  },
  ATTENTION: {
    label: 'Atenção',
    description: 'Requer revisão do operador (divergência ou prazo).',
    icon: 'i-lucide-triangle-alert',
    color: 'warning'
  },
  ERROR: {
    label: 'Erro',
    description: 'Falha na consulta ou no processamento.',
    icon: 'i-lucide-circle-x',
    color: 'error'
  },
  NOT_APPLICABLE: {
    label: 'Não aplicável',
    description: 'Obrigações não se aplicam a este contribuinte/competência.',
    icon: 'i-lucide-minus-circle',
    color: 'neutral'
  },
  UNKNOWN: {
    label: 'Desconhecido',
    description: 'Sem evidência suficiente para classificar a situação.',
    icon: 'i-lucide-help-circle',
    color: 'neutral',
    sourceHint: 'Sem evidência oficial'
  },
  UNSUPPORTED: {
    label: 'Não suportado',
    description: 'Sem integração M2M oficial para esta informação.',
    icon: 'i-lucide-ban',
    color: 'neutral',
    sourceHint: 'Sem API oficial'
  },
  BLOCKED: {
    label: 'Bloqueado',
    description: 'Consulta bloqueada (autorização, franquia ou kill-switch).',
    icon: 'i-lucide-shield-off',
    color: 'error'
  }
}

const ALL_CODES = Object.keys(CATALOG) as FiscalSituationCode[]

export function normalizeFiscalSituation(value?: string | null): FiscalSituationCode | string {
  if (!value) return 'UNKNOWN'
  return String(value).trim().toUpperCase()
}

export function fiscalStatusMeta(value?: string | null): FiscalStatusMeta {
  const code = normalizeFiscalSituation(value)
  const known = CATALOG[code as FiscalSituationCode]
  if (known) {
    return { code, ...known }
  }
  return {
    code,
    label: code || 'Desconhecido',
    description: 'Situação não catalogada.',
    icon: 'i-lucide-help-circle',
    color: 'neutral'
  }
}

export function fiscalStatusLabel(value?: string | null): string {
  return fiscalStatusMeta(value).label
}

export function fiscalStatusIcon(value?: string | null): string {
  return fiscalStatusMeta(value).icon
}

export function fiscalStatusColor(value?: string | null): FiscalStatusTone {
  return fiscalStatusMeta(value).color
}

/** Opções de filtro para selects de situação. */
export function fiscalSituationFilterItems(includeAll = true): Array<{ label: string, value: string }> {
  const items = ALL_CODES.map(code => ({
    label: CATALOG[code].label,
    value: code
  }))
  if (includeAll) {
    return [{ label: 'Todas as situações', value: 'all' }, ...items]
  }
  return items
}

/** Cobertura de módulo (FULL / PARTIAL / NONE / …). */
export function coverageLabel(value?: string | null): string {
  const v = String(value || '').toUpperCase()
  switch (v) {
    case 'FULL':
      return 'Cobertura plena'
    case 'PARTIAL':
      return 'Cobertura parcial'
    case 'NONE':
    case 'UNSUPPORTED':
      return 'Sem cobertura'
    case 'NOT_APPLICABLE':
      return 'Não aplicável'
    case 'UNKNOWN':
      return 'Cobertura desconhecida'
    default:
      return value || 'Cobertura desconhecida'
  }
}

export type FiscalCoverageTone = 'success' | 'warning' | 'error' | 'info' | 'neutral'

export interface FiscalCoverageMeta {
  code: string
  label: string
  description: string
  icon: string
  color: FiscalCoverageTone
}

export function coverageMeta(value?: string | null): FiscalCoverageMeta {
  const code = String(value || 'UNKNOWN').trim().toUpperCase() || 'UNKNOWN'
  switch (code) {
    case 'FULL':
      return {
        code,
        label: 'Cobertura plena',
        description: 'Integração M2M oficial com cobertura plena neste módulo.',
        icon: 'i-lucide-shield-check',
        color: 'success'
      }
    case 'PARTIAL':
      return {
        code,
        label: 'Cobertura parcial',
        description: 'Parte das obrigações é coberta; campos sem fonte oficial permanecem não suportados.',
        icon: 'i-lucide-shield-alert',
        color: 'warning'
      }
    case 'NONE':
    case 'UNSUPPORTED':
      return {
        code,
        label: 'Sem cobertura',
        description: 'Sem integração M2M oficial para esta informação.',
        icon: 'i-lucide-shield-off',
        color: 'neutral'
      }
    case 'NOT_APPLICABLE':
      return {
        code,
        label: 'Não aplicável',
        description: 'Cobertura não se aplica a este contribuinte/módulo.',
        icon: 'i-lucide-minus-circle',
        color: 'neutral'
      }
    default:
      return {
        code,
        label: coverageLabel(code),
        description: 'Cobertura não catalogada.',
        icon: 'i-lucide-help-circle',
        color: 'neutral'
      }
  }
}

/** Origem do dado (DEMO / SIMULATED / LIVE). */
export type FiscalOriginTone = 'warning' | 'info' | 'success' | 'neutral'

export interface FiscalOriginMeta {
  code: string
  label: string
  description: string
  icon: string
  color: FiscalOriginTone
  synthetic: boolean
  banner: string | null
}

export function dataOriginMeta(value?: string | null): FiscalOriginMeta {
  const code = String(value || '').trim().toUpperCase()
  switch (code) {
    case 'DEMO':
      return {
        code,
        label: 'Dados demonstrativos',
        description: 'Dataset sintético do office demo — sem validade fiscal.',
        icon: 'i-lucide-flask-conical',
        color: 'warning',
        synthetic: true,
        banner: 'Dados demonstrativos — sem validade fiscal'
      }
    case 'SIMULATED':
      return {
        code,
        label: 'Dados simulados',
        description: 'Resposta simulada localmente — sem validade fiscal.',
        icon: 'i-lucide-sparkles',
        color: 'info',
        synthetic: true,
        banner: 'Dados demonstrativos — sem validade fiscal'
      }
    case 'LIVE':
      return {
        code,
        label: 'Fonte produtiva',
        description: 'Dados provenientes de fontes oficiais no tenant ativo.',
        icon: 'i-lucide-radio-tower',
        color: 'success',
        synthetic: false,
        banner: null
      }
    default:
      return {
        code: code || 'UNKNOWN',
        label: code || 'Origem desconhecida',
        description: 'Origem do dado não catalogada.',
        icon: 'i-lucide-help-circle',
        color: 'neutral',
        synthetic: false,
        banner: null
      }
  }
}

/**
 * Classifica o empty state da carteira a partir de situação agregada / erro.
 * Nunca inventa dados: só escolhe a mensagem honesta.
 */
export function resolveFiscalEmptyKind(input: {
  loading?: boolean
  error?: string | null
  hasRows?: boolean
  hasPrevious?: boolean
  situation?: string | null
  filtered?: boolean
}): 'loading' | 'empty' | 'error' | 'unsupported' | 'blocked' | 'filtered' {
  if (input.loading && !input.hasRows && !input.hasPrevious) return 'loading'
  if (input.error && !input.hasRows && !input.hasPrevious) return 'error'
  if (input.hasRows) return 'empty' // não deveria ser chamado com rows
  const sit = String(input.situation || '').toUpperCase()
  if (sit === 'UNSUPPORTED') return 'unsupported'
  if (sit === 'BLOCKED') return 'blocked'
  if (input.filtered) return 'filtered'
  return 'empty'
}
