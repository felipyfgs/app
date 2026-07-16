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
  enabled: platformEnabled,
  privileged
} = usePlatformOfficeSelect()

const officeLabel = computed(() => me.value?.current_office?.name || me.value?.office?.name || (platformEnabled.value ? 'Selecione um escritório' : 'Escritório'))
const officeSlug = computed(() => me.value?.current_office?.slug || me.value?.office?.slug || '')
const officeId = computed(() => me.value?.current_office?.id ?? me.value?.office?.id ?? null)
const isPlatform = computed(() => isPlatformAdmin(me.value))

/** Selo compacto: "Plataforma · <Office>" quando privilegiado; só o nome no modo membership. */
const displayLabel = computed(() => {
  if (privileged.value && officeLabel.value) {
    return `Plataforma · ${officeLabel.value}`
  }
  return officeLabel.value
})

const selectedOffice = computed(() => ({
  label: displayLabel.value,
  avatar: {
    alt: displayLabel.value,
    icon: (privileged.value ? 'i-lucide-shield' : 'i-lucide-building-2') as 'i-lucide-shield' | 'i-lucide-building-2'
  }
}))

const multiMembership = computed(() => memberships.value.length > 1)
const switching = computed(() => membershipSwitching.value || platformSwitching.value)
const loading = computed(() => membershipsLoading.value || platformLoading.value)

const items = computed<DropdownMenuItem[][]>(() => {
  // PLATFORM_ADMIN: seletor global (somente selectable=true).
  if (isPlatform.value) {
    const selectable = platformOffices.value.filter(o => o.selectable !== false && o.is_active !== false)
    const officeRows: DropdownMenuItem[] = selectable.length
      ? selectable.map(o => ({
          label: o.name || `Escritório #${o.id}`,
          description: [o.slug, o.status].filter(Boolean).join(' · ') || 'Seletor global',
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
          label: platformLoadError.value || 'Nenhum escritório selecionável',
          icon: 'i-lucide-building-2',
          disabled: true
        }]

    const footer: DropdownMenuItem[] = [{
      label: privileged.value ? `Plataforma · ${officeLabel.value}` : 'Selecione um escritório',
      icon: 'i-lucide-shield',
      disabled: true,
      description: privileged.value
        ? (officeSlug.value ? `Ativo: ${officeSlug.value}` : 'Office resolvido no servidor')
        : 'Contexto global da plataforma'
    }]

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
        ? (privileged ? `Plataforma · ${officeLabel}` : `Seletor global de escritórios. ${officeLabel}`)
        : `Escritório ativo: ${officeLabel}${multiMembership ? '. Abrir seletor entre memberships autorizadas' : '. Única membership da sessão'}`"
      :aria-haspopup="(isPlatform || multiMembership) ? 'listbox' : 'menu'"
      :title="collapsed ? displayLabel : undefined"
      data-testid="office-identity"
      :data-office-id="isPlatform ? 'platform-global' : 'session'"
      :data-privileged="privileged ? 'true' : 'false'"
      :data-platform-seal="privileged ? 'true' : 'false'"
    />
  </UDropdownMenu>
</template>
