<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

/**
 * Rodapé da sidebar — estrutura de
 * `.reference/nuxt-dashboard-template/app/components/UserMenu.vue`
 * (dropdown + botão block/ghost + chevrons; sem seletor de cores de marca).
 */
defineProps<{
  collapsed?: boolean
}>()

const colorMode = useColorMode()
const { logout } = useSanctumAuth()
const { me, canAccessAdministration } = useDashboard()

const displayUser = computed(() => ({
  name: me.value?.name || 'Usuário',
  avatar: {
    alt: me.value?.name || 'Usuário',
    text: (me.value?.name || 'U').slice(0, 1).toUpperCase()
  }
}))

async function onLogout() {
  await logout()
  await navigateTo('/login')
}

const { $pwa } = useNuxtApp()

const roleLabel = computed(() => {
  const role = me.value?.role
  if (role === 'ADMIN') return 'Administrador'
  if (role === 'OPERATOR') return 'Operador'
  if (role === 'VIEWER') return 'Visualizador'
  return null
})

const needsTwoFactorSetup = computed(() => Boolean(me.value?.requires_two_factor_setup))
const twoFactorConfirmed = computed(() => Boolean(me.value?.two_factor_confirmed))

const items = computed<DropdownMenuItem[][]>(() => {
  const account: DropdownMenuItem[] = [{
    type: 'label',
    label: displayUser.value.name,
    avatar: displayUser.value.avatar,
    ...(roleLabel.value
      ? { description: roleLabel.value }
      : {})
  }]

  const profile: DropdownMenuItem[] = [{
    label: me.value?.email || 'Conta',
    icon: 'i-lucide-user',
    disabled: true,
    ...(roleLabel.value
      ? { description: `Papel: ${roleLabel.value}` }
      : {})
  }]

  if (needsTwoFactorSetup.value) {
    profile.push({
      label: 'Configurar 2FA',
      icon: 'i-lucide-shield-alert',
      to: '/two-factor/setup',
      description: 'Obrigatório para administração'
    })
  } else if (me.value?.role === 'ADMIN') {
    profile.push({
      label: twoFactorConfirmed.value ? '2FA ativo' : 'Segundo fator',
      icon: twoFactorConfirmed.value ? 'i-lucide-shield-check' : 'i-lucide-shield',
      to: '/two-factor/setup',
      disabled: twoFactorConfirmed.value
    })
  }

  if (canAccessAdministration.value) {
    profile.push({
      label: 'Configurações',
      icon: 'i-lucide-sliders-horizontal',
      to: '/settings'
    })
    profile.push({
      label: 'Administração',
      icon: 'i-lucide-shield',
      to: '/admin'
    })
  }

  const groups: DropdownMenuItem[][] = [account, profile]

  if ($pwa?.showInstallPrompt) {
    groups.push([{
      label: 'Instalar aplicativo',
      icon: 'i-lucide-download',
      onSelect: () => {
        void $pwa.install()
      }
    }])
  }

  groups.push([{
    label: 'Aparência',
    icon: 'i-lucide-sun-moon',
    children: [{
      label: 'Claro',
      icon: 'i-lucide-sun',
      type: 'checkbox',
      checked: colorMode.value === 'light',
      onSelect(e: Event) {
        e.preventDefault()
        colorMode.preference = 'light'
      }
    }, {
      label: 'Escuro',
      icon: 'i-lucide-moon',
      type: 'checkbox',
      checked: colorMode.value === 'dark',
      onSelect(e: Event) {
        e.preventDefault()
        colorMode.preference = 'dark'
      }
    }]
  }], [{
    label: 'Sair',
    icon: 'i-lucide-log-out',
    color: 'error',
    onSelect: () => {
      onLogout()
    }
  }])

  return groups
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
        ...displayUser,
        label: collapsed ? undefined : displayUser.name,
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
      :aria-label="`Menu do usuário: ${displayUser.name}`"
      data-testid="user-menu"
    />
  </UDropdownMenu>
</template>
