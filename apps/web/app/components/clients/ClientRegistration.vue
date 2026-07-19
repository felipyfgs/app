<script setup lang="ts">
/**
 * Cadastro do cliente — dossiê RFB (Cartão CNPJ / QSA / filiais / origem).
 * Arquétipo settings + ShellPanelAccordion.
 */
import type { AccordionItem } from '@nuxt/ui'
import type { Client, ShareholderPayload } from '~/types/api'
import { clientCrmHref, clientFiscalHref } from '~/utils/client-cross-links'
import { formatCnpj } from '~/utils/format'
import {
  formatSourceDate,
  registrationSourceLabel,
  registrationStatusColor,
  registrationStatusLabel
} from '~/utils/registration-labels'

const props = withDefaults(defineProps<{
  client: Client
  canManageClients: boolean
  startEditing?: boolean
  /** Painel: dados | contatos | all (modal / legado). */
  panel?: 'dados' | 'contatos' | 'all'
}>(), {
  startEditing: false,
  panel: 'all'
})

const useAccordion = computed(() => props.panel === 'all')
const showDados = computed(() => props.panel === 'all' || props.panel === 'dados')
const showContatos = computed(() => props.panel === 'contatos')
const showSummary = computed(() => showDados.value)

const emit = defineEmits<{
  updated: []
  editingChange: [value: boolean]
}>()

const formRef = ref<{ reset: () => void, saving: { value: boolean } } | null>(null)
const editing = ref(false)

const primaryEstablishment = computed(() =>
  props.client.establishments?.find(e => e.is_matrix)
  || props.client.establishments?.[0]
  || null
)

const shareholders = computed((): ShareholderPayload[] =>
  (primaryEstablishment.value?.shareholders || []) as ShareholderPayload[]
)

const branchRows = computed(() => {
  const branches = props.client.branches || []
  return branches.map(b => ({
    id: b.id,
    label: b.display_name || b.legal_name || b.name,
    cnpj: b.cnpj || '—'
  }))
})

const cnpjLabel = computed(() => {
  const raw = props.client.cnpj || primaryEstablishment.value?.cnpj || props.client.root_cnpj
  return raw ? formatCnpj(raw) : '—'
})

const registrationStatus = computed(() => primaryEstablishment.value?.registration_status || null)
const mainCnae = computed(() => {
  const est = primaryEstablishment.value
  if (!est?.main_cnae_code) return null
  return est.main_cnae_name
    ? `${est.main_cnae_code} — ${est.main_cnae_name}`
    : est.main_cnae_code
})

const fiscalHref = computed(() => clientFiscalHref(props.client.id))
const configHref = computed(() => clientCrmHref(props.client.id, 'configuracao'))

const accordionItems = computed((): AccordionItem[] => [
  {
    label: 'Cadastro RFB',
    icon: 'i-lucide-building-2',
    value: 'identificacao',
    slot: 'identificacao' as const
  },
  {
    label: 'Quadro societário',
    icon: 'i-lucide-users',
    value: 'socios',
    slot: 'socios' as const
  },
  {
    label: 'Filiais',
    icon: 'i-lucide-map-pin-house',
    value: 'filiais',
    slot: 'filiais' as const
  },
  {
    label: 'Origem dos dados',
    icon: 'i-lucide-database',
    value: 'origem',
    slot: 'origem' as const
  }
])

function startEdit() {
  if (!props.canManageClients) return
  editing.value = true
}

function cancelEdit() {
  editing.value = false
  formRef.value?.reset()
}

function onSaved() {
  editing.value = false
  emit('updated')
}

watch(
  () => props.startEditing,
  (start) => {
    if (props.canManageClients && start) {
      editing.value = true
    }
  },
  { immediate: true }
)

watch(editing, value => emit('editingChange', value))

watch(
  () => props.client,
  () => {
    if (!editing.value) {
      nextTick(() => formRef.value?.reset())
    }
  },
  { deep: true }
)

defineExpose({ startEdit, cancelEdit, editing })
</script>

<template>
  <div
    class="space-y-4"
    data-testid="client-registration"
  >
    <ClientsClientContactsSection
      v-if="showContatos"
      :client="client"
      :can-manage-clients="canManageClients"
      @updated="emit('updated')"
    />

    <template v-else>
      <UPageCard
        v-if="showSummary"
        variant="subtle"
        :title="client.legal_name || client.name"
        :description="client.trade_name || client.display_name || undefined"
        data-testid="client-registration-summary"
      >
        <div class="flex flex-col gap-3">
          <p class="font-mono text-sm text-highlighted">
            {{ cnpjLabel }}
          </p>
          <div class="flex flex-wrap gap-2">
            <UBadge
              :color="client.is_active ? 'success' : 'neutral'"
              variant="subtle"
            >
              {{ client.is_active ? 'Ativo' : 'Inativo' }}
            </UBadge>
            <UBadge
              :color="registrationStatusColor(registrationStatus)"
              variant="subtle"
            >
              {{ registrationStatusLabel(registrationStatus) }}
            </UBadge>
            <UBadge
              v-if="client.tax_regime_label || client.tax_regime"
              color="primary"
              variant="subtle"
            >
              {{ client.tax_regime_label || client.tax_regime }}
            </UBadge>
            <UBadge
              v-if="primaryEstablishment?.simples_optant === true"
              color="info"
              variant="subtle"
            >
              Simples Nacional
            </UBadge>
            <UBadge
              v-if="primaryEstablishment?.mei_optant === true"
              color="info"
              variant="subtle"
            >
              MEI
            </UBadge>
          </div>
          <p
            v-if="mainCnae"
            class="text-sm text-muted"
          >
            <span class="font-medium text-default">CNAE:</span>
            {{ mainCnae }}
          </p>
          <div
            v-if="useAccordion"
            class="flex flex-wrap gap-2"
          >
            <UButton
              :to="fiscalHref"
              color="primary"
              variant="soft"
              icon="i-lucide-radar"
              label="Monitoramento fiscal"
              data-testid="client-registration-to-fiscal"
            />
            <UButton
              :to="configHref"
              color="neutral"
              variant="soft"
              icon="i-lucide-sliders-horizontal"
              label="Configuração"
              data-testid="client-registration-to-config"
            />
          </div>
        </div>
      </UPageCard>

      <ShellPanelAccordion
        v-if="useAccordion"
        :items="accordionItems"
        type="multiple"
        :default-value="['identificacao']"
        test-id="client-registration-accordion"
      >
        <template #identificacao-body>
          <div class="space-y-3">
            <div
              v-if="canManageClients"
              class="flex flex-wrap justify-end gap-2"
            >
              <template v-if="!editing">
                <UButton
                  icon="i-lucide-pencil"
                  label="Editar"
                  color="primary"
                  variant="soft"
                  data-testid="client-registration-edit"
                  @click="startEdit"
                />
              </template>
              <template v-else>
                <UButton
                  color="neutral"
                  variant="subtle"
                  label="Cancelar"
                  @click="cancelEdit"
                />
                <UButton
                  form="client-registration-form"
                  type="submit"
                  color="primary"
                  icon="i-lucide-save"
                  label="Salvar alterações"
                />
              </template>
            </div>
            <ClientsClientForm
              ref="formRef"
              form-id="client-registration-form"
              :client="client"
              :can-manage-clients="canManageClients"
              :can-manage-credentials="false"
              :locked="!editing"
              hide-actions
              @saved="onSaved"
              @cancel="cancelEdit"
            />
          </div>
        </template>

        <template #socios-body>
          <div
            v-if="client.responsible_qualification_name"
            class="mb-3 rounded-lg bg-elevated/50 px-3 py-3 ring ring-inset ring-default"
          >
            <p class="text-xs font-medium text-muted">
              Qualificação do responsável
            </p>
            <p class="font-medium text-highlighted">
              {{ client.responsible_qualification_name }}
              <span
                v-if="client.responsible_qualification_code"
                class="text-sm text-muted"
              >
                ({{ client.responsible_qualification_code }})
              </span>
            </p>
          </div>
          <div
            v-if="shareholders.length"
            class="grid gap-3 sm:grid-cols-2"
          >
            <div
              v-for="(socio, index) in shareholders"
              :key="`${socio.name}-${index}`"
              class="rounded-lg bg-elevated/50 px-3 py-3 ring ring-inset ring-default"
            >
              <p class="font-medium text-highlighted">
                {{ socio.name || 'Sócio' }}
              </p>
              <p class="mt-1 text-sm text-muted">
                {{ socio.qualification_name || socio.qualification_code || socio.type || '—' }}
              </p>
              <p
                v-if="socio.document_masked"
                class="mt-1 font-mono text-xs text-muted"
              >
                {{ socio.document_masked }}
              </p>
              <p
                v-if="socio.entered_at"
                class="mt-1 text-xs text-muted"
              >
                Desde {{ socio.entered_at }}
              </p>
            </div>
          </div>
          <UEmpty
            v-else
            icon="i-lucide-users"
            title="Sem sócios no cadastro"
            description="QSA aparece após consulta ou atualização cadastral RFB."
          />
        </template>

        <template #filiais-body>
          <div
            v-if="branchRows.length"
            class="grid gap-3 sm:grid-cols-2"
          >
            <UPageCard
              v-for="branch in branchRows"
              :key="branch.id"
              :to="clientCrmHref(branch.id, 'cadastro')"
              :title="branch.label"
              :description="branch.cnpj"
              icon="i-lucide-building-2"
              variant="subtle"
            />
          </div>
          <UEmpty
            v-else
            icon="i-lucide-map-pin-house"
            title="Sem filiais vinculadas"
            description="Matriz e filiais aparecem aqui quando houver vínculo."
          />
        </template>

        <template #origem-body>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <p class="text-xs font-medium text-muted">
                Fonte
              </p>
              <p class="font-medium">
                {{ registrationSourceLabel(client.registration_source) }}
              </p>
            </div>
            <div>
              <p class="text-xs font-medium text-muted">
                Atualizado em
              </p>
              <p class="font-medium">
                {{ formatSourceDate(client.registration_refreshed_at) }}
              </p>
            </div>
          </div>
          <p class="mt-3 text-sm text-muted">
            Certificado A1 e canais de captura ficam em
            <NuxtLink
              :to="configHref"
              class="font-medium text-primary"
            >
              Configuração
            </NuxtLink>.
            Não armazenamos senha de PFX nesta tela.
          </p>
        </template>
      </ShellPanelAccordion>

      <template v-else-if="showDados">
        <div class="flex flex-wrap justify-end gap-2">
          <template v-if="canManageClients && !editing">
            <UButton
              icon="i-lucide-pencil"
              label="Editar"
              color="primary"
              variant="soft"
              data-testid="client-registration-edit"
              @click="startEdit"
            />
          </template>
          <template v-else-if="canManageClients && editing">
            <UButton
              color="neutral"
              variant="subtle"
              label="Cancelar"
              @click="cancelEdit"
            />
            <UButton
              form="client-registration-form"
              type="submit"
              color="primary"
              icon="i-lucide-save"
              label="Salvar alterações"
            />
          </template>
        </div>
        <UPageCard
          variant="subtle"
          :ui="{ body: 'sm:p-5' }"
        >
          <ClientsClientForm
            ref="formRef"
            form-id="client-registration-form"
            :client="client"
            :can-manage-clients="canManageClients"
            :can-manage-credentials="false"
            :locked="!editing"
            hide-actions
            @saved="onSaved"
            @cancel="cancelEdit"
          />
        </UPageCard>
      </template>
    </template>
  </div>
</template>
