<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

/**
 * Cabeçalho da sidebar — arquétipo TeamsMenu do template
 * (`.reference/nuxt-dashboard-template/app/components/TeamsMenu.vue`).
 * Troca apenas entre memberships autorizadas (API tenants/*) — 15.2.
 */
defineProps<{
  collapsed?: boolean
}>()

const { me } = useDashboard()
const {
  memberships,
  loading,
  switching,
  loadMemberships,
  switchTo
} = useTenantSwitch()

const officeLabel = computed(() => me.value?.office?.name || 'Escritório')
const officeSlug = computed(() => me.value?.office?.slug || '')
const officeId = computed(() => me.value?.office?.id ?? null)

const selectedOffice = computed(() => ({
  label: officeLabel.value,
  avatar: {
    alt: officeLabel.value,
    icon: 'i-lucide-building-2' as const
  }
}))

const multiMembership = computed(() => memberships.value.length > 1)

const items = computed<DropdownMenuItem[][]>(() => {
  const officeRows: DropdownMenuItem[] = memberships.value.length
    ? memberships.value.map(m => ({
        label: m.office_name || `Escritório #${m.office_id}`,
        description: [m.office_slug, m.role].filter(Boolean).join(' · ') || undefined,
        avatar: {
          alt: m.office_name || 'Escritório',
          icon: 'i-lucide-building-2' as const
        },
        type: 'checkbox' as const,
        checked: m.is_current || m.office_id === officeId.value,
        disabled: switching.value,
        onSelect(e: Event) {
          e.preventDefault()
          if (m.is_current || m.office_id === officeId.value) return
          void switchTo(m.office_id)
        }
      }))
    : [{
        label: selectedOffice.value.label,
        avatar: selectedOffice.value.avatar,
        type: 'checkbox' as const,
        checked: true,
        onSelect(e: Event) {
          e.preventDefault()
        }
      }]

  const footer: DropdownMenuItem[] = multiMembership.value
    ? [{
        label: 'Somente memberships autorizadas',
        icon: 'i-lucide-shield-check',
        disabled: true,
        description: officeSlug.value
          ? `Ativo: ${officeSlug.value}`
          : 'Troca explícita · sem office livre'
      }]
    : [{
        label: 'Escritório da sessão',
        icon: 'i-lucide-lock',
        disabled: true,
        description: officeSlug.value
          ? `Slug: ${officeSlug.value} · única membership`
          : 'Vinculado ao usuário autenticado'
      }]

  if (loading.value) {
    footer.unshift({
      label: 'Carregando escritórios…',
      icon: 'i-lucide-loader-circle',
      disabled: true
    })
  }

  return [officeRows, footer]
})

onMounted(() => {
  void loadMemberships()
})

// Recarrega lista quando o escritório da sessão muda (ex.: outra aba).
watch(officeId, () => {
  void loadMemberships()
})
</script>

<template>
  <UDropdownMenu
    :items="items"
    :content="{ align: 'center', collisionPadding: 12 }"
    :ui="{ content: collapsed ? 'w-48' : 'w-(--reka-dropdown-menu-trigger-width)' }"
  >
    <UButton
      v-bind="{
        ...selectedOffice,
        label: collapsed ? undefined : selectedOffice.label,
        trailingIcon: collapsed ? undefined : 'i-lucide-chevrons-up-down'
      }"
      color="neutral"
      variant="ghost"
      block
      :square="collapsed"
      :loading="switching"
      class="data-[state=open]:bg-elevated"
      :class="[!collapsed && 'py-2']"
      :ui="{
        trailingIcon: 'text-dimmed'
      }"
      :aria-label="`Escritório ativo: ${officeLabel}${multiMembership ? '. Abrir seletor de escritórios autorizados' : ''}`"
      :aria-haspopup="multiMembership ? 'listbox' : 'menu'"
      data-testid="office-identity"
    />
  </UDropdownMenu>
</template>
