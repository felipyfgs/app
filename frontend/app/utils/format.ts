export function formatDateTime(value?: string | null): string {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short'
  }).format(date)
}

/**
 * CNPJ limpo para armazenamento/clipboard: maiúsculo, sem máscara.
 * Aceita numérico ou alfanumérico (14 chars no domínio).
 */
export function normalizeCnpj(value?: string | null): string {
  return String(value || '').replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
}

/**
 * Máscara visual `AA.AAA.AAA/AAAA-AA` (14 chars).
 * Valores incompletos/inesperados retornam o limpo sem forçar máscara.
 */
export function formatCnpj(value?: string | null): string {
  const clean = normalizeCnpj(value)
  if (!clean) {
    return '—'
  }
  if (clean.length !== 14) {
    return clean
  }
  return `${clean.slice(0, 2)}.${clean.slice(2, 5)}.${clean.slice(5, 8)}/${clean.slice(8, 12)}-${clean.slice(12, 14)}`
}

export function formatCurrency(value?: string | number | null): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  const amount = Number(value)
  if (!Number.isFinite(amount)) {
    return '—'
  }
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(amount)
}

export function formatBytes(value?: number | null): string {
  if (value === null || value === undefined || value < 0) {
    return '—'
  }
  if (value < 1024) {
    return `${value} B`
  }
  const units = ['KB', 'MB', 'GB', 'TB']
  let amount = value / 1024
  let unit = units[0]
  for (let index = 1; amount >= 1024 && index < units.length; index += 1) {
    amount /= 1024
    unit = units[index]
  }
  return `${amount.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} ${unit}`
}

/**
 * Labels gerais do painel.
 * Situação de NFS-e na lista/chip: Autorizada · Cancelada · Em revisão
 * (ver `nfseOperationalLabel` / `officialStatusDescription`).
 */
const labels: Record<string, string> = {
  ACTIVE: 'Ativa',
  AUTHORIZED: 'Autorizada',
  BLOCKED: 'Bloqueada',
  CANCELLED: 'Cancelada',
  COMPLETED: 'Concluída',
  ERROR: 'Erro',
  EXPIRED: 'Expirada',
  FAILED: 'Falhou',
  IDLE: 'Aguardando',
  INTERMEDIARY: 'Intermediário',
  ISSUER: 'Emitente',
  JUDICIAL: 'Autorizada',
  MANUAL: 'Manual',
  NONE: 'Sem cursor',
  OFF: 'Captura off',
  ON: 'Captura on',
  PARTIAL: 'Parcial',
  PENDING: 'Pendente',
  PROCESSING: 'Processando',
  READY: 'Disponível',
  REPLACED: 'Cancelada',
  REVIEW: 'Em revisão',
  RUNNING: 'Em execução',
  SCHEDULED: 'Agendada',
  SUBSTITUTE: 'Autorizada',
  SUPERSEDED: 'Cancelada',
  TAKER: 'Tomador',
  UNKNOWN: 'Em revisão',
  WAITING: 'Na fila'
}

/** Grupo operacional da nota NFS-e (lista / filtro / insight). */
export type NfseOperationalGroup = 'AUTHORIZED' | 'CANCELLED' | 'REVIEW'

export function nfseOperationalGroup(status?: string | null): NfseOperationalGroup {
  const s = (status || '').toUpperCase()
  if (['ACTIVE', 'SUBSTITUTE', 'JUDICIAL', 'AUTHORIZED'].includes(s)) return 'AUTHORIZED'
  if (['CANCELLED', 'SUPERSEDED', 'REPLACED'].includes(s)) return 'CANCELLED'
  return 'REVIEW'
}

/** Label operacional pt-BR (chip da grade). */
export function nfseOperationalLabel(status?: string | null): string {
  switch (nfseOperationalGroup(status)) {
    case 'AUTHORIZED':
      return 'Autorizada'
    case 'CANCELLED':
      return 'Cancelada'
    default:
      return 'Em revisão'
  }
}

/** Nuance granular no detalhe (não é o chip da lista). */
export function nfseGranularLabel(status?: string | null): string | null {
  if (!status) return null
  const map: Record<string, string> = {
    ACTIVE: 'NFS-e Gerada',
    SUBSTITUTE: 'NFS-e de Substituição',
    CANCELLED: 'Cancelada por evento',
    SUPERSEDED: 'Substituída',
    REPLACED: 'Substituída',
    JUDICIAL: 'Decisão judicial',
    UNKNOWN: 'Situação indefinida'
  }
  return map[status.toUpperCase()] || null
}

/** Descrição curta do cStat NFS-e Nacional (documento). */
export function officialStatusDescription(cStat?: string | null): string | null {
  if (!cStat) return null
  const map: Record<string, string> = {
    100: 'NFS-e Gerada',
    101: 'NFS-e de Substituição Gerada',
    102: 'NFS-e de Decisão Judicial',
    103: 'NFS-e Avulsa'
  }
  return map[cStat] || `Código de situação ${cStat}`
}

/** Situação oficial no detalhe: cStat se houver, senão nuance do enum. */
export function noteOfficialSituation(
  status?: string | null,
  cStat?: string | null,
  apiLabel?: string | null
): string | null {
  if (apiLabel) return apiLabel
  return officialStatusDescription(cStat) || nfseGranularLabel(status)
}

export function statusLabel(value?: string | null): string {
  if (!value) return '—'
  // Enums de NFS-e → label operacional (mesmo mapa do backend)
  if (['SUBSTITUTE', 'JUDICIAL', 'CANCELLED', 'SUPERSEDED', 'REPLACED', 'UNKNOWN', 'AUTHORIZED', 'REVIEW'].includes(value.toUpperCase())) {
    return nfseOperationalLabel(value)
  }
  return labels[value] || value
}
