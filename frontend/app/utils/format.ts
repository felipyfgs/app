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
