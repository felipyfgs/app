<script setup lang="ts">
/**
 * Cabeçalho de identidade do detalhe do cliente
 * (inspiração HubStrom + UPageCard do template).
 * 1 cliente = 1 CNPJ (sem multi-estabelecimento).
 */
import type { Client, Establishment } from '~/types/api'

const props = defineProps<{
  client: Client
  establishments: Establishment[]
  canManageClients: boolean
}>()

const emit = defineEmits<{
  edit: []
}>()

// edit → pai navega para Cadastro com edição liberada

const displayName = computed(() =>
  props.client.display_name || props.client.legal_name || props.client.name
)

const primary = computed(() =>
  props.establishments[0] || null
)

const fullCnpj = computed(() =>
  props.client.cnpj || primary.value?.cnpj || props.client.root_cnpj
)

const initials = computed(() => {
  const source = displayName.value.trim()
  if (!source) return '?'
  const parts = source.split(/\s+/).filter(Boolean)
  if (parts.length === 1) return parts[0]!.slice(0, 2).toUpperCase()
  return `${parts[0]![0] || ''}${parts[1]![0] || ''}`.toUpperCase()
})

const titleLine = computed(() => {
  const base = props.client.legal_name || props.client.name
  const trade = props.client.trade_name || primary.value?.trade_name
  if (primary.value?.is_matrix) return `${base} (MATRIZ)`
  if (trade) return `${base} · ${trade}`
  return base
})
</script>

<template>
  <UPageCard
    variant="subtle"
    data-testid="client-detail-header"
    :ui="{ body: 'sm:p-5' }"
  >
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="flex min-w-0 items-start gap-3 sm:gap-4">
        <UAvatar
          :alt="displayName"
          :text="initials"
          size="xl"
          class="shrink-0 ring ring-inset ring-default"
        />
        <div class="min-w-0 space-y-2">
          <div>
            <h1 class="truncate text-lg font-semibold text-highlighted sm:text-xl">
              {{ titleLine }}
            </h1>
            <p v-if="client.display_name && client.display_name !== client.legal_name" class="truncate text-sm text-muted">
              {{ client.display_name }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 font-mono text-sm text-muted">
              <UIcon name="i-lucide-building-2" class="size-3.5 shrink-0" />
              {{ fullCnpj }}
            </span>
            <UBadge
              :color="client.is_active ? 'success' : 'neutral'"
              variant="subtle"
              size="sm"
            >
              {{ client.is_active ? 'Ativo' : 'Inativo' }}
            </UBadge>
            <UBadge
              v-if="primary?.is_matrix"
              color="primary"
              variant="subtle"
              size="sm"
            >
              Matriz
            </UBadge>
            <UBadge
              v-else-if="primary"
              color="neutral"
              variant="subtle"
              size="sm"
            >
              Filial (cliente próprio)
            </UBadge>
            <UBadge
              v-if="client.company_size_name"
              color="neutral"
              variant="subtle"
              size="sm"
            >
              {{ client.company_size_name }}
            </UBadge>
          </div>
        </div>
      </div>

      <div class="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end">
        <UButton
          v-if="canManageClients"
          color="primary"
          variant="soft"
          icon="i-lucide-pencil"
          label="Editar cliente"
          data-testid="client-page-edit"
          @click="emit('edit')"
        />
        <UButton
          color="neutral"
          variant="subtle"
          icon="i-lucide-map-pin-house"
          label="Estabelecimentos"
          :to="clientSectionPath(client.id, 'estabelecimentos')"
        />
        <UButton
          color="neutral"
          variant="outline"
          icon="i-lucide-badge-check"
          label="Certificado A1"
          :to="clientSectionPath(client.id, 'certificado')"
        />
      </div>
    </div>
  </UPageCard>
</template>
