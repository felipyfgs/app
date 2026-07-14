<script setup lang="ts">
/**
 * Coluna lateral do detalhe: progresso de setup, certificado e atalhos.
 * Sem multi-estabelecimento — 1 cliente = 1 CNPJ.
 */
import type { Client, ClientCredential, Establishment } from '~/types/api'

const props = defineProps<{
  client: Client
  credential: ClientCredential | null
  establishments: Establishment[]
  canManageCredentials: boolean
}>()

const primary = computed(() => props.establishments[0] || null)
/** Detalhe completo (ADMIN) ou summary do show (OPERATOR/VIEWER). */
const hasCredential = computed(() => !!props.credential || !!props.client.credential_summary)
const hasContacts = computed(() => (props.client.contacts?.length || 0) > 0)
const hasCnpj = computed(() => !!primary.value || !!props.client.cnpj)

const setupSteps = computed(() => [
  { key: 'client', label: 'Cliente cadastrado', done: true },
  { key: 'cnpj', label: 'CNPJ do estabelecimento', done: hasCnpj.value },
  { key: 'a1', label: 'Certificado A1', done: hasCredential.value },
  { key: 'contact', label: 'Contato interno', done: hasContacts.value }
])

const progressPct = computed(() => {
  const done = setupSteps.value.filter(s => s.done).length
  return Math.round((done / setupSteps.value.length) * 100)
})

const nextHint = computed(() => {
  const pending = setupSteps.value.find(s => !s.done)
  return pending?.label || 'Setup completo'
})

const certColor = computed((): 'success' | 'warning' | 'error' | 'neutral' => {
  const c = props.credential
  const s = props.client.credential_summary
  if (!c && !s) return 'neutral'
  if (c) {
    if (c.expires_alert_1 || c.status === 'EXPIRED') return 'error'
    if (c.expires_alert_7 || c.expires_alert_30) return 'warning'
    return 'success'
  }
  if (s!.expires_alert_1 || s!.status === 'EXPIRED') return 'error'
  if (s!.expires_alert_7 || s!.expires_alert_30) return 'warning'
  return 'success'
})

const certLabel = computed(() => {
  const c = props.credential
  const s = props.client.credential_summary
  if (!c && !s) return 'Sem certificado'
  const validTo = c?.valid_to || s?.valid_to
  if (validTo) {
    return `Válido até ${formatDateTime(validTo)}`
  }
  return c?.status || s?.status || 'Ativo'
})

const captureReady = computed(() => {
  const e = primary.value
  if (!e) return false
  return e.is_active
    && e.capture_enabled !== false
    && (e.capture_eligibility?.eligible !== false)
})
</script>

<template>
  <div class="space-y-4" data-testid="client-detail-aside">
    <UPageCard
      title="Progresso de integração"
      description="Etapas para captura ADN operacional."
      variant="subtle"
      :ui="{ body: 'space-y-3' }"
    >
      <div class="flex items-end justify-between gap-2">
        <span class="text-2xl font-semibold tabular-nums text-highlighted">
          {{ progressPct }}%
        </span>
        <span class="text-xs text-muted">
          {{ nextHint }}
        </span>
      </div>
      <UProgress
        :model-value="progressPct"
        :max="100"
        size="sm"
        :color="progressPct >= 100 ? 'success' : 'primary'"
      />
      <ul class="space-y-1.5">
        <li
          v-for="step in setupSteps"
          :key="step.key"
          class="flex items-center gap-2 text-sm"
        >
          <UIcon
            :name="step.done ? 'i-lucide-circle-check' : 'i-lucide-circle'"
            class="size-4 shrink-0"
            :class="step.done ? 'text-success' : 'text-muted'"
          />
          <span :class="step.done ? 'text-highlighted' : 'text-muted'">
            {{ step.label }}
          </span>
        </li>
      </ul>
    </UPageCard>

    <UPageCard
      title="Certificado digital"
      variant="subtle"
      :ui="{ body: 'space-y-3' }"
    >
      <div class="flex items-start justify-between gap-2">
        <div class="flex min-w-0 items-start gap-3">
          <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-elevated ring ring-inset ring-default">
            <UIcon name="i-lucide-badge-check" class="size-5 text-primary" />
          </div>
          <div class="min-w-0">
            <p class="font-medium text-highlighted">
              Certificado A1
            </p>
            <p class="text-sm text-muted">
              <template v-if="!canManageCredentials && !hasCredential">
                Gerenciado por ADMIN
              </template>
              <template v-else>
                {{ certLabel }}
              </template>
            </p>
            <p v-if="credential?.subject_name" class="mt-1 truncate text-xs text-muted">
              {{ credential.subject_name }}
            </p>
          </div>
        </div>
        <UBadge
          :color="certColor"
          variant="subtle"
          size="sm"
          class="shrink-0"
        >
          {{ hasCredential ? 'Ativo' : 'Pendente' }}
        </UBadge>
      </div>
      <p class="text-xs text-muted">
        O e-CNPJ da raiz pode ser usado neste cliente (matriz ou filial), ou o certificado próprio da filial.
      </p>
      <UButton
        block
        color="neutral"
        variant="subtle"
        size="sm"
        icon="i-lucide-arrow-right"
        label="Gerenciar certificado"
        :to="clientSectionPath(client.id, 'certificado')"
      />
    </UPageCard>

    <UPageCard
      title="Captura ADN"
      variant="subtle"
      :ui="{ body: 'space-y-3' }"
    >
      <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <dt class="text-muted">
            CNPJ
          </dt>
          <dd class="font-mono text-xs font-medium text-highlighted">
            {{ client.cnpj || primary?.cnpj || '—' }}
          </dd>
        </div>
        <div>
          <dt class="text-muted">
            Elegível
          </dt>
          <dd class="font-medium text-highlighted">
            {{ captureReady ? 'Sim' : 'Não' }}
          </dd>
        </div>
      </dl>
      <UButton
        block
        color="neutral"
        variant="subtle"
        size="sm"
        icon="i-lucide-refresh-cw"
        label="Sincronização"
        :to="clientSectionPath(client.id, 'sincronizacao')"
      />
    </UPageCard>

    <UPageCard
      title="Atalhos"
      variant="subtle"
      :ui="{ body: 'space-y-1 p-2 sm:p-2' }"
    >
      <UButton
        block
        color="neutral"
        variant="ghost"
        class="justify-start"
        icon="i-lucide-clipboard-list"
        label="Cadastro e contatos"
        :to="clientSectionPath(client.id, 'cadastro')"
      />
      <UButton
        block
        color="neutral"
        variant="ghost"
        class="justify-start"
        icon="i-lucide-map-pin-house"
        label="Estabelecimentos / filiais"
        :to="clientSectionPath(client.id, 'estabelecimentos')"
      />
      <UButton
        block
        color="neutral"
        variant="ghost"
        class="justify-start"
        icon="i-lucide-layout-dashboard"
        label="Resumo"
        :to="clientSectionPath(client.id)"
      />
    </UPageCard>
  </div>
</template>
