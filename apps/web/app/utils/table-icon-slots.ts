/**
 * Slots de ícone em células densas — largura fixa + grupo visual.
 * Ícones inativos/indisponíveis ocupam o mesmo espaço (alinhamento entre linhas).
 *
 * Imports diretos de `@nuxt/ui/components/*` — não usar `#components` aqui.
 * O barrel virtual invalida no HMR e pode deixar UDropdownMenu em TDZ
 * (ReferenceError) ao re-renderizar células da UTable.
 */
import type { DropdownMenuItem } from '@nuxt/ui'
import UButton from '@nuxt/ui/components/Button.vue'
import UDropdownMenu from '@nuxt/ui/components/DropdownMenu.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import { h, type VNode } from 'vue'

export const TABLE_ICON_SLOT_CLASS = 'inline-flex size-8 shrink-0 items-center justify-center'

export const TABLE_ICON_GROUP_CLASS
  = 'inline-flex items-center rounded-md ring-1 ring-inset ring-default divide-x divide-default'

type IconColor = 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'info'

export function tableIconSlot(child: VNode | VNode[] | null) {
  return h('div', { class: TABLE_ICON_SLOT_CLASS }, child ? (Array.isArray(child) ? child : [child]) : [])
}

export function tableIconGroup(slots: Array<VNode | null>, testId?: string) {
  return h('div', {
    class: TABLE_ICON_GROUP_CLASS,
    ...(testId ? { 'data-testid': testId } : {})
  }, slots.map(slot => tableIconSlot(slot)))
}

export function tableIconButton(args: {
  label: string
  icon: string
  testId: string
  color?: IconColor
  disabled?: boolean
  onClick?: () => void
  href?: string | null
  target?: string
  rel?: string
}) {
  const disabled = args.disabled === true || (!args.onClick && !args.href)
  return h(UTooltip, { text: args.label }, {
    default: () => h(UButton, {
      'size': 'xs',
      'color': args.color || 'neutral',
      'variant': 'ghost',
      'icon': args.icon,
      'square': true,
      'class': 'size-8',
      'aria-label': args.label,
      'disabled': disabled,
      'data-testid': args.testId,
      'href': disabled ? undefined : (args.href || undefined),
      'target': args.href && !disabled ? (args.target || '_blank') : undefined,
      'rel': args.href && !disabled ? (args.rel || 'noopener') : undefined,
      'onClick': disabled || args.href ? undefined : args.onClick
    })
  })
}

export function tableIconMenu(args: {
  label: string
  icon: string
  testId: string
  items: DropdownMenuItem[][]
  color?: IconColor
  disabled?: boolean
  align?: 'start' | 'end'
}) {
  const trigger = () => h(UButton, {
    'size': 'xs',
    'color': args.color || 'neutral',
    'variant': 'ghost',
    'icon': args.icon,
    'square': true,
    'class': 'size-8',
    'aria-label': args.label,
    'disabled': args.disabled === true,
    'data-testid': args.testId
  })

  if (args.disabled) {
    return h(UTooltip, { text: args.label }, { default: trigger })
  }

  return h(UDropdownMenu, {
    items: args.items,
    content: { align: args.align || 'start' }
  }, { default: trigger })
}
