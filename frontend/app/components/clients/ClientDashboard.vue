<script setup lang="ts">
/**
 * Aba Resumo: dashboard operacional do cliente (sem repetir o formulário de Cadastro).
 */
import type { Client, ClientCredential, Establishment } from '~/types/api'
import {
  formatSourceDate,
  registrationSourceLabel,
  registrationStatusColor,
  registrationStatusIcon,
  registrationStatusLabel
} from '~/utils/registrationLabels'

const props = defineProps<{
  client: Client
  credential: ClientCredential | null
  establishments: Establishment[]
  triggeredIds: number[]
  canManageCredentials: boolean
  canManageClients?: boolean
  canTriggerSync?: boolean
  inModal?: boolean
}>()

const emit = defineEmits<{
  navigateSection: [section: 'resumo' | 'cadastro' | 'estabelecimentos' | 'certificado' | 'sincronizacao']
}>()

const primary = computed(() => props.establishments[0] || null)
const branches = computed(() => props.client.branches || [])
const fullCnpj = computed(() => props.client.cnpj || primary.value?.cnpj || props.client.root_cnpj)

/** Detalhe completo (ADMIN) ou summary do show (OPERATOR/VIEWER). */
const hasCredential = computed(() => !!props.credential || !!props.client.credential_summary)
const captureReady = computed(() => {
  const e = primary.value
  if (!e) return false
  return e.is_active
    && e.capture_enabled !== false
    && (e.capture_eligibility?.eligible !== false)
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
  if (validTo) return `Até ${formatDateTime(validTo)}`
  return c?.status || s?.status || 'Ativo'
})

function go(section: 'cadastro' | 'estabelecimentos' | 'certificado' | 'sincronizacao') {
  if (props.inModal) {
    emit('navigateSection', section)
    return
  }
  navigateTo(clientSectionPath(props.client.id, section))
}

const kpis = computed(() => [
  {
    key: 'status',
    label: 'Estado',
    value: props.client.is_active ? 'Ativo' : 'Inativo',
    icon: props.client.is_active ? 'i-lucide-circle-check' : 'i-lucide-circle-minus',
    color: (props.client.is_active ? 'success' : 'neutral') as 'success' | 'neutral'
  },
  {
    key: 'a1',
    label: 'Certificado A1',
    value: hasCredential.value ? 'Ativo' : 'Pendente',
    icon: 'i-lucide-badge-check',
    color: certColor.value
  },
  {
    key: 'branches',
    label: 'Filiais vinculadas',
    value: String(branches.value.length),
    icon: 'i-lucide-git-branch',
    color: 'primary' as const
  },
  {
    key: 'capture',
    label: 'Captura ADN',
    value: captureReady.value ? 'Elegível' : 'Bloqueada',
    icon: captureReady.value ? 'i-lucide-radio' : 'i-lucide-radio-off',
    color: (captureReady.value ? 'success' : 'warning') as 'success' | 'warning'
  }
])
</script>

<template>
  <div class="space-y-5" data-testid="client-dashboard">
    <!-- KPIs -->
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
      <UPageCard
        v-for="kpi in kpis"
        :key="kpi.key"
        variant="subtle"
        class="min-w-0"
      >
        <div class="flex items-start gap-3">
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-elevated ring ring-inset ring-default"
          >
            <UIcon :name="kpi.icon" class="size-4 text-primary" />
          </div>
          <div class="min-w-0">
            <p class="text-xs text-muted">
              {{ kpi.label }}
            </p>
            <p class="truncate font-semibold text-highlighted">
              {{ kpi.value }}
            </p>
          </div>
        </div>
      </UPageCard>
    </div>

    <!-- Snapshot + atalhos -->
    <div class="grid gap-4 lg:grid-cols-2">
      <UPageCard
        title="Identidade"
        description="Visão rápida — edição completa na aba Cadastro."
        variant="subtle"
      >
        <dl class="space-y-3 text-sm">
          <div>
            <dt class="text-muted">
              Razão social
            </dt>
            <dd class="font-medium text-highlighted">
              {{ client.legal_name || client.name }}
            </dd>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <dt class="text-muted">
                CNPJ
              </dt>
              <dd class="font-mono text-highlighted">
                {{ fullCnpj }}
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                Raiz
              </dt>
              <dd class="font-mono text-highlighted">
                {{ client.root_cnpj }}
              </dd>
            </div>
          </div>
          <div v-if="primary" class="flex flex-wrap gap-2">
            <UBadge
              :color="registrationStatusColor(primary.registration_status)"
              variant="subtle"
              :icon="registrationStatusIcon(primary.registration_status)"
            >
              {{ registrationStatusLabel(primary.registration_status) }}
            </UBadge>
            <UBadge color="neutral" variant="subtle">
              {{ registrationSourceLabel(client.registration_source) }}
            </UBadge>
            <UBadge
              v-if="client.registration_refreshed_at"
              color="neutral"
              variant="outline"
            >
              Atualizado {{ formatSourceDate(client.registration_refreshed_at) }}
            </UBadge>
          </div>
          <UButton
            size="sm"
            color="primary"
            variant="soft"
            icon="i-lucide-clipboard-list"
            label="Abrir cadastro"
            class="mt-1"
            @click="go('cadastro')"
          />
        </dl>
      </UPageCard>

      <div class="space-y-4">
        <UPageCard
          variant="subtle"
          class="cursor-pointer transition-shadow hover:ring-primary/30"
          role="button"
          tabindex="0"
          @click="go('certificado')"
          @keydown.enter="go('certificado')"
        >
          <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 ring ring-inset ring-primary/25">
              <UIcon name="i-lucide-badge-check" class="size-5 text-primary" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-2">
                <p class="font-medium text-highlighted">
                  Certificado A1
                </p>
                <UBadge :color="certColor" variant="subtle" size="sm">
                  {{ hasCredential ? 'Ativo' : 'Pendente' }}
                </UBadge>
              </div>
              <p class="text-sm text-muted">
                {{ certLabel }}
              </p>
            </div>
          </div>
        </UPageCard>

        <UPageCard
          variant="subtle"
          class="cursor-pointer transition-shadow hover:ring-primary/30"
          role="button"
          tabindex="0"
          @click="go('sincronizacao')"
          @keydown.enter="go('sincronizacao')"
        >
          <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 ring ring-inset ring-primary/25">
              <UIcon name="i-lucide-refresh-cw" class="size-5 text-primary" />
            </div>
            <div class="min-w-0">
              <p class="font-medium text-highlighted">
                Sincronização ADN
              </p>
              <p class="text-sm text-muted">
                <template v-if="triggeredIds.length">
                  Solicitada nesta sessão
                </template>
                <template v-else-if="!hasCredential && canManageCredentials">
                  Ative o A1 antes da captura
                </template>
                <template v-else-if="captureReady">
                  Pronto para captura · NSU não editável
                </template>
                <template v-else>
                  Captura indisponível no momento
                </template>
              </p>
            </div>
          </div>
        </UPageCard>

        <UPageCard
          variant="subtle"
          class="cursor-pointer transition-shadow hover:ring-primary/30"
          role="button"
          tabindex="0"
          @click="go('estabelecimentos')"
          @keydown.enter="go('estabelecimentos')"
        >
          <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 ring ring-inset ring-primary/25">
              <UIcon name="i-lucide-map-pin-house" class="size-5 text-primary" />
            </div>
            <div class="min-w-0">
              <p class="font-medium text-highlighted">
                Estabelecimentos
              </p>
              <p class="text-sm text-muted">
                <template v-if="client.matrix_client_id">
                  Filial — ver vínculo com a matriz
                </template>
                <template v-else>
                  {{ branches.length }} filial(is) vinculada(s)
                </template>
              </p>
            </div>
          </div>
        </UPageCard>
      </div>
    </div>

    <UAlert
      v-if="!hasCredential"
      color="primary"
      variant="subtle"
      icon="i-lucide-badge-alert"
      title="Próximo passo: certificado A1"
    >
      <template #actions>
        <UButton
          size="sm"
          color="primary"
          variant="soft"
          label="Ir ao certificado"
          @click="go('certificado')"
        />
      </template>
    </UAlert>
  </div>
</template>
