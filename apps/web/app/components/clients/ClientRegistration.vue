<script setup lang="ts">
/**
 * Cadastro do cliente — dados / proveniência / sócios / filiais.
 * Contatos e custom fields migraram para páginas Contato / Configuração.
 */
import type { AccordionItem } from '@nuxt/ui'
import type { Client, ShareholderPayload } from '~/types/api'
import { formatSourceDate, registrationSourceLabel } from '~/utils/registration-labels'

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

const accordionItems = computed((): AccordionItem[] => [
  {
    label: 'Dados cadastrais',
    icon: 'i-lucide-building-2',
    value: 'dados',
    slot: 'dados' as const
  },
  {
    label: 'Proveniência',
    icon: 'i-lucide-database',
    value: 'proveniencia',
    slot: 'proveniencia' as const
  },
  {
    label: 'Sócios e representante',
    icon: 'i-lucide-users',
    value: 'socios',
    slot: 'socios' as const
  },
  {
    label: 'Filiais',
    icon: 'i-lucide-map-pin-house',
    value: 'filiais',
    slot: 'filiais' as const
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

    <ShellPanelAccordion
      v-else-if="useAccordion"
      :items="accordionItems"
      type="multiple"
      :default-value="['dados']"
      test-id="client-registration-accordion"
    >
      <template #dados-body>
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

      <template #proveniencia-body>
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
              {{ socio.qualification_name || socio.qualification_code || '—' }}
            </p>
          </div>
        </div>
        <UEmpty
          v-else
          icon="i-lucide-users"
          title="Sem sócios no cadastro"
          description="QSA aparece após consulta/atualização cadastral."
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
            :to="`/clients/${branch.id}/cadastro`"
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
  </div>
</template>
