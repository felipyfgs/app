/**
 * Tokens visuais canônicos da faixa de KPIs do painel.
 *
 * Fonte: `.reference/nuxt-dashboard-template/app/components/home/HomeStats.vue`
 * + `frontend/app/components/home/HomeStats.vue` / `MonitoringKpiStrip.vue`.
 *
 * Use via `ShellKpiStrip` ou, se precisar de markup custom, importe os helpers.
 */

export type KpiTone = 'default' | 'primary' | 'success' | 'warning' | 'error' | 'info'

export interface DashboardKpiItem {
  /** Chave estável (testid / v-for). */
  key: string
  /** Título curto em pt-BR (CSS uppercase no card). */
  title: string
  icon: string
  value: string | number
  /** Destino opcional (UPageCard `to`). */
  to?: string
  tone?: KpiTone
  /** Ícone de alerta ao lado do valor (ex.: crítico > 0). */
  critical?: boolean
  /** aria-label do card. */
  ariaLabel?: string
}

/** Classes do leading circular (ícone) por tom. */
export function kpiLeadingClass(tone: KpiTone = 'default', active = false): string {
  if (active) {
    return 'mb-1.5 p-2 sm:mb-2.5 sm:p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col'
  }
  switch (tone) {
    case 'error':
      return 'mb-1.5 p-2 sm:mb-2.5 sm:p-2.5 rounded-full bg-error/10 ring ring-inset ring-error/25 flex-col'
    case 'warning':
      return 'mb-1.5 p-2 sm:mb-2.5 sm:p-2.5 rounded-full bg-warning/10 ring ring-inset ring-warning/25 flex-col'
    case 'success':
      return 'mb-1.5 p-2 sm:mb-2.5 sm:p-2.5 rounded-full bg-success/10 ring ring-inset ring-success/25 flex-col'
    case 'info':
      return 'mb-1.5 p-2 sm:mb-2.5 sm:p-2.5 rounded-full bg-info/10 ring ring-inset ring-info/25 flex-col'
    case 'primary':
    case 'default':
    default:
      return 'mb-1.5 p-2 sm:mb-2.5 sm:p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col'
  }
}

/** `:ui` do UPageCard no padrão HomeStats do template. */
export function kpiPageCardUi(tone: KpiTone = 'default', active = false) {
  return {
    // p denso no mobile evita título/valor vazando do card
    container: 'min-w-0 gap-y-1 overflow-hidden p-2.5 sm:gap-y-1.5 sm:p-4 lg:p-6',
    wrapper: 'min-w-0 max-w-full items-start overflow-hidden',
    leading: kpiLeadingClass(tone, active),
    title: 'w-full max-w-full truncate font-normal text-muted text-[10px] leading-tight uppercase sm:text-xs'
  } as const
}

/** Classes do grid colado (N colunas no lg). */
export function kpiGridClass(cols: number): string {
  const n = Math.min(Math.max(cols, 2), 6)
  switch (n) {
    case 2: return 'grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-2 lg:gap-px'
    case 3: return 'grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-3 lg:gap-px'
    case 5: return 'grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-5 lg:gap-px'
    case 6: return 'grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-6 lg:gap-px'
    default: return 'grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-4 lg:gap-px'
  }
}

/**
 * Humaniza códigos de status em pt-BR quando não há mapa.
 * Preferir catálogos explícitos (`statusLabel`, `fiscalStatusMeta`, work-labels).
 */
export function humanizeStatusCode(value?: string | null): string {
  if (!value) return '—'
  const raw = String(value).trim()
  if (!raw) return '—'
  // Já legível (tem espaço ou acento comum)
  if (/\s/.test(raw) || /[áàâãéêíóôõúç]/i.test(raw)) return raw
  return raw
    .replace(/[_-]+/g, ' ')
    .toLowerCase()
    .replace(/\b\w/g, c => c.toUpperCase())
}
