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

const labels: Record<string, string> = {
  ACTIVE: 'Ativa',
  BLOCKED: 'Bloqueada',
  COMPLETED: 'Concluída',
  ERROR: 'Erro',
  EXPIRED: 'Expirada',
  FAILED: 'Falhou',
  IDLE: 'Aguardando',
  INTERMEDIARY: 'Intermediário',
  ISSUER: 'Emitente',
  MANUAL: 'Manual',
  PENDING: 'Pendente',
  PROCESSING: 'Processando',
  READY: 'Disponível',
  RUNNING: 'Em execução',
  SCHEDULED: 'Agendada',
  SUPERSEDED: 'Substituída',
  TAKER: 'Tomador',
  UNKNOWN: 'Em revisão',
  WAITING: 'Na fila'
}

export function statusLabel(value?: string | null): string {
  return value ? (labels[value] || value) : '—'
}
