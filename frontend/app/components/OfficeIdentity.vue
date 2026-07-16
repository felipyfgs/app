<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import { isPlatformAdmin } from '~/utils/permissions'

/**
 * Cabeçalho da sidebar — arquétipo TeamsMenu do template
 * (`.reference/nuxt-dashboard-template/app/components/TeamsMenu.vue`).
 * Memberships autorizadas OU seletor global (PLATFORM_ADMIN).
 */
defineProps<{
  collapsed?: boolean
}>()

const { me } = useDashboard()
const {
  memberships,
  loading: membershipsLoading,
  switching: membershipSwitching,
  loadMemberships,
  switchTo
} = useTenantSwitch()

const {
  offices: platformOffices,
  loading: platformLoading,
  switching: platformSwitching,
  loadError: platformLoadError,
  loadOffices,
  selectOffice,
  clearSelection,
  enabled: platformEnabled,
  privileged
} = usePlatformOfficeSelect()

const officeLabel = computed(() => me.value?.office?.name || (platformEnabled.value ? 'Selecione um escritório' : 'Escritório'))
const officeSlug = computed(() => me.value?.office?.slug || '')
const officeId = computed(() => me.value?.office?.id ?? null)
const isPlatform = computed(() => isPlatformAdmin(me.value))

const selectedOffice = computed(() => ({
  label: officeLabel.value,
  avatar: {
    alt: officeLabel.value,
    icon: (privileged.value ? 'i-lucide-shield' : 'i-lucide-building-2') as 'i-lucide-shield' | 'i-lucide-building-2'
  }
}))

const multiMembership = computed(() => memberships.value.length > 1)
const switching = computed(() => membershipSwitching.value || platformSwitching.value)
const loading = computed(() => membershipsLoading.value || platformLoading.value)

const items = computed<DropdownMenuItem[][]>(() => {
  // PLATFORM_ADMIN: seletor global (qualquer office ativo).
  if (isPlatform.value) {
    const officeRows: DropdownMenuItem[] = platformOffices.value.length
      ? platformOffices.value.map(o => ({
          label: o.name || `Escritório #${o.id}`,
          description: [o.slug, o.plan].filter(Boolean).join(' · ') || 'Seletor global',
          avatar: {
            alt: o.name || 'Escritório',
            icon: 'i-lucide-building-2' as const
          },
          type: 'checkbox' as const,
          checked: o.id === officeId.value && privileged.value,
          disabled: switching.value,
          onSelect(e: Event) {
            e.preventDefault()
            if (o.id === officeId.value && privileged.value) return
            void selectOffice(o.id)
          }
        }))
      : [{
          label: platformLoadError.value || 'Nenhum escritório listado',
          icon: 'i-lucide-building-2',
          disabled: true
        }]

    const footer: DropdownMenuItem[] = [{
      label: privileged.value ? 'Contexto privilegiado ativo' : 'Seletor global PLATFORM_ADMIN',
      icon: 'i-lucide-shield',
      disabled: true,
      description: privileged.value
        ? (officeSlug.value ? `Ativo: ${officeSlug.value}` : 'Office resolvido no servidor')
        : 'Sem membership fictícia · auditoria interna'
    }]

    if (privileged.value) {
      footer.unshift({
        label: 'Encerrar contexto privilegiado',
        icon: 'i-lucide-log-out',
        disabled: switching.value,
        onSelect(e: Event) {
          e.preventDefault()
          void clearSelection()
        }
      })
    }

    if (loading.value) {
      footer.unshift({
        label: 'Carregando escritórios…',
        icon: 'i-lucide-loader-circle',
        disabled: true
      })
    }

    return [officeRows, footer]
  }

  // Memberships do escritório (usuário comum).
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
  if (isPlatform.value) {
    void loadOffices()
  } else {
    void loadMemberships()
  }
})

watch(officeId, () => {
  if (isPlatform.value) {
    void loadOffices()
  } else {
    void loadMemberships()
  }
})

watch(isPlatform, (v) => {
  if (v) void loadOffices()
  else void loadMemberships()
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
      :class="[!collapsed && 'py-2', privileged && 'ring-1 ring-warning/50']"
      :ui="{
        trailingIcon: 'text-dimmed'
      }"
      :aria-label="isPlatform
        ? `Seletor global de escritórios. Ativo: ${officeLabel}${privileged ? ' (contexto privilegiado)' : ''}`
        : `Escritório ativo: ${officeLabel}${multiMembership ? '. Abrir seletor entre memberships autorizadas' : '. Única membership da sessão'}`"
      :aria-haspopup="(isPlatform || multiMembership) ? 'listbox' : 'menu'"
      :title="collapsed ? officeLabel : undefined"
      data-testid="office-identity"
      :data-office-id="isPlatform ? 'platform-global' : 'session'"
      :data-privileged="privileged ? 'true' : 'false'"
    />
  </UDropdownMenu>
</template>
