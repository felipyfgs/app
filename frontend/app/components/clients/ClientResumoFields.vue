<script setup lang="ts">
/**
 * Grade de campos somente leitura (estilo detalhe HubStrom / form do template).
 */
import type { Client, Establishment } from '~/types/api'
import {
  formatSourceDate,
  registrationSourceLabel,
  registrationStatusLabel
} from '~/utils/registrationLabels'

const props = defineProps<{
  client: Client
  establishments: Establishment[]
}>()

const matrix = computed(() =>
  props.establishments.find(e => e.is_matrix) || props.establishments[0] || null
)

function field(value?: string | number | null): string {
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}
</script>

<template>
  <div class="space-y-6" data-testid="client-resumo-fields">
    <div>
      <h2 class="text-base font-semibold text-highlighted">
        Dados cadastrais
      </h2>
      <p class="mt-1 text-sm text-muted">
        Informações essenciais da raiz e da matriz, de forma clara e organizada.
      </p>
    </div>

    <!-- Dados da empresa -->
    <div class="space-y-3">
      <h3 class="text-sm font-medium text-highlighted">
        Dados da empresa
      </h3>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <UFormField label="CNPJ">
          <UInput :model-value="field(client.cnpj || matrix?.cnpj)" readonly class="w-full font-mono" />
        </UFormField>
        <UFormField label="Raiz CNPJ">
          <UInput :model-value="field(client.root_cnpj)" readonly class="w-full font-mono" />
        </UFormField>
        <UFormField label="Razão social" class="sm:col-span-2 xl:col-span-2">
          <UInput :model-value="field(client.legal_name || client.name)" readonly class="w-full" />
        </UFormField>
        <UFormField label="Nome interno">
          <UInput :model-value="field(client.display_name)" readonly class="w-full" />
        </UFormField>
        <UFormField label="Nome fantasia">
          <UInput :model-value="field(client.trade_name || matrix?.trade_name)" readonly class="w-full" />
        </UFormField>
        <UFormField label="Início de atividade">
          <UInput :model-value="field(formatSourceDate(matrix?.activity_started_at))" readonly class="w-full" />
        </UFormField>
        <UFormField label="Situação cadastral">
          <UInput
            :model-value="field(matrix ? registrationStatusLabel(matrix.registration_status) : null)"
            readonly
            class="w-full"
          />
        </UFormField>
        <UFormField label="Estado no escritório">
          <UInput :model-value="client.is_active ? 'Ativo' : 'Inativo'" readonly class="w-full" />
        </UFormField>
      </div>
    </div>

    <USeparator />

    <!-- Informações fiscais / cadastrais -->
    <div class="space-y-3">
      <h3 class="text-sm font-medium text-highlighted">
        Informações cadastrais
      </h3>
      <div class="grid gap-3 sm:grid-cols-2">
        <UFormField label="Natureza jurídica (cód.)">
          <UInput :model-value="field(client.legal_nature_code)" readonly class="w-full" />
        </UFormField>
        <UFormField label="Natureza jurídica">
          <UInput :model-value="field(client.legal_nature_name)" readonly class="w-full" />
        </UFormField>
        <UFormField label="Porte (cód.)">
          <UInput :model-value="field(client.company_size_code)" readonly class="w-full" />
        </UFormField>
        <UFormField label="Porte">
          <UInput :model-value="field(client.company_size_name)" readonly class="w-full" />
        </UFormField>
        <UFormField label="CNAE principal">
          <UInput :model-value="field(matrix?.main_cnae_code)" readonly class="w-full font-mono" />
        </UFormField>
        <UFormField label="Descrição CNAE">
          <UInput :model-value="field(matrix?.main_cnae_name)" readonly class="w-full" />
        </UFormField>
        <UFormField label="Fonte cadastral">
          <UInput :model-value="field(registrationSourceLabel(client.registration_source))" readonly class="w-full" />
        </UFormField>
        <UFormField label="Atualizado em">
          <UInput :model-value="field(formatSourceDate(client.registration_refreshed_at))" readonly class="w-full" />
        </UFormField>
      </div>
    </div>

    <template v-if="client.notes || (!client.is_active && client.inactive_reason)">
      <USeparator />
      <div class="space-y-3">
        <h3 class="text-sm font-medium text-highlighted">
          Observações
        </h3>
        <UFormField v-if="client.notes" label="Notas internas">
          <UTextarea
            :model-value="client.notes"
            readonly
            class="w-full"
            :rows="3"
          />
        </UFormField>
        <UFormField v-if="!client.is_active && client.inactive_reason" label="Motivo de inativação">
          <UTextarea
            :model-value="client.inactive_reason"
            readonly
            class="w-full"
            :rows="2"
          />
        </UFormField>
      </div>
    </template>
  </div>
</template>
