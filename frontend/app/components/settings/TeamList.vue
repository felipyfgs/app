<script setup lang="ts">
/**
 * Lista de membros — arquétipo MembersList do template.
 * Fonte: .reference/nuxt-dashboard-template/app/components/settings/MembersList.vue
 */
import type { DropdownMenuItem } from '@nuxt/ui'
import type { OfficeMember, OfficeRole } from '~/types/api'

const props = defineProps<{
  members: OfficeMember[]
  actingId?: number | null
  canMutate?: boolean
}>()

const emit = defineEmits<{
  changeRole: [member: OfficeMember, role: OfficeRole]
  deactivate: [member: OfficeMember]
  reactivate: [member: OfficeMember]
  regenerate: [member: OfficeMember]
}>()

const roleItems: Array<{ label: string, value: OfficeRole }> = [
  { label: 'Admin', value: 'ADMIN' },
  { label: 'Operador', value: 'OPERATOR' },
  { label: 'Visualizador', value: 'VIEWER' }
]

function roleLabel(role: string): string {
  return roleItems.find(r => r.value === role)?.label || role
}

function statusColor(status: string): 'success' | 'warning' | 'error' | 'neutral' {
  switch (status) {
    case 'active': return 'success'
    case 'pending': return 'warning'
    case 'expired': return 'error'
    default: return 'neutral'
  }
}

function statusLabel(status: string): string {
  switch (status) {
    case 'active': return 'Ativo'
    case 'pending': return 'Pendente'
    case 'expired': return 'Expirado'
    case 'deactivated': return 'Desativado'
    default: return status
  }
}

function menuItems(member: OfficeMember): DropdownMenuItem[][] {
  if (!props.canMutate) return []

  const items: DropdownMenuItem[] = []

  if (member.status === 'pending' || member.status === 'expired') {
    items.push({
      label: 'Regenerar acesso',
      icon: 'i-lucide-refresh-cw',
      onSelect: () => emit('regenerate', member)
    })
  }

  if (member.is_active || member.status === 'active') {
    items.push({
      label: 'Desativar',
      icon: 'i-lucide-circle-off',
      color: 'warning',
      onSelect: () => emit('deactivate', member)
    })
  } else if (member.status === 'deactivated') {
    items.push({
      label: 'Reativar',
      icon: 'i-lucide-circle-check',
      color: 'success',
      onSelect: () => emit('reactivate', member)
    })
  }

  return items.length ? [items] : []
}

function onRoleChange(member: OfficeMember, role: OfficeRole) {
  if (role === member.role) return
  emit('changeRole', member, role)
}
</script>

<template>
  <ul
    role="list"
    class="divide-y divide-default"
    data-testid="team-list"
  >
    <li
      v-for="member in props.members"
      :key="member.id"
      class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6"
      :data-testid="`team-row-${member.id}`"
    >
      <div class="flex min-w-0 items-center gap-3">
        <UAvatar
          :alt="member.name || member.email || '?'"
          size="md"
        />
        <div class="min-w-0 text-sm">
          <p class="truncate font-medium text-highlighted">
            {{ member.name || '—' }}
          </p>
          <p class="truncate text-muted">
            {{ member.email || '—' }}
          </p>
        </div>
      </div>

      <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <UBadge
          size="sm"
          variant="subtle"
          :color="statusColor(member.status)"
          :label="statusLabel(member.status)"
        />

        <USelect
          v-if="props.canMutate && (member.is_active || member.status === 'active')"
          :model-value="member.role"
          :items="roleItems"
          value-key="value"
          label-key="label"
          color="neutral"
          size="sm"
          class="hidden w-36 sm:block"
          :disabled="props.actingId === member.id"
          :aria-label="`Papel de ${member.name || member.email}`"
          @update:model-value="(v: OfficeRole) => onRoleChange(member, v)"
        />
        <UBadge
          v-else
          size="sm"
          variant="outline"
          color="neutral"
          :label="roleLabel(member.role)"
          class="hidden sm:inline-flex"
        />

        <UDropdownMenu
          v-if="props.canMutate && menuItems(member).length"
          :items="menuItems(member)"
          :content="{ align: 'end' }"
        >
          <UButton
            icon="i-lucide-ellipsis-vertical"
            color="neutral"
            variant="ghost"
            :aria-label="`Ações de ${member.name || member.email}`"
            :loading="props.actingId === member.id"
            :data-testid="`team-actions-${member.id}`"
          />
        </UDropdownMenu>
      </div>
    </li>
  </ul>
</template>
