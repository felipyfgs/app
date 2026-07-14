<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

/**
 * Cabeçalho da sidebar — cópia estrutural de TeamsMenu do template
 * (`.reference/nuxt-dashboard-template/app/components/TeamsMenu.vue`).
 * Não permite troca de escritório (tenancy da sessão).
 */
defineProps<{
  collapsed?: boolean
}>()

const { me } = useDashboard()

const officeLabel = computed(() => me.value?.office?.name || 'NFS-e ADN')
const officeSlug = computed(() => me.value?.office?.slug || '')

const selectedOffice = computed(() => ({
  label: officeLabel.value,
  avatar: {
    alt: officeLabel.value,
    icon: 'i-lucide-building-2' as const
  }
}))

const items = computed<DropdownMenuItem[][]>(() => {
  return [[{
    label: selectedOffice.value.label,
    avatar: selectedOffice.value.avatar,
    type: 'checkbox' as const,
    checked: true,
    onSelect(e: Event) {
      // Mantém o item marcado; sem troca de office.
      e.preventDefault()
    }
  }], [{
    label: 'Escritório da sessão',
    icon: 'i-lucide-lock',
    disabled: true,
    description: officeSlug.value
      ? `Slug: ${officeSlug.value} · sem troca livre`
      : 'Vinculado ao usuário autenticado'
  }]]
})
</script>

<template>
  <UDropdownMenu
    :items="items"
    :content="{ align: 'center', collisionPadding: 12 }"
    :ui="{ content: collapsed ? 'w-40' : 'w-(--reka-dropdown-menu-trigger-width)' }"
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
      class="data-[state=open]:bg-elevated"
      :class="[!collapsed && 'py-2']"
      :ui="{
        trailingIcon: 'text-dimmed'
      }"
      :aria-label="`Escritório ativo: ${officeLabel}`"
      data-testid="office-identity"
    />
  </UDropdownMenu>
</template>
