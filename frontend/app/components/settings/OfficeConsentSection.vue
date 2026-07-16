<script setup lang="ts">
/**
 * Consentimento técnico versionado — checkbox + finalidades.
 */
import type { OfficeTechnicalConsent } from '~/types/api'

const props = defineProps<{
  consent: OfficeTechnicalConsent | null
  loading?: boolean
  saving?: boolean
  readonly?: boolean
}>()

const emit = defineEmits<{
  accept: []
  revoke: []
}>()

const accepted = ref(false)

watch(
  () => props.consent,
  (c) => {
    accepted.value = Boolean(c?.accepted) && !c?.requires_reacceptance
  },
  { immediate: true }
)

const purposes = computed(() => props.consent?.purposes || [
  { code: 'SERPRO_TERM_SIGNING', label: 'Assinatura do Termo de autorização (automatizada)' },
  { code: 'NFE_AUTXML_DISTDFE', label: 'autXML DistDFe (NFe/CTe) do escritório' }
])

const needsAction = computed(() =>
  !props.consent?.accepted || props.consent?.requires_reacceptance
)
</script>

<template>
  <div data-testid="settings-consent-section">
    <UPageCard
      title="Consentimento técnico"
      description="Autorização explícita para uso do A1 nas finalidades do painel. Versão registrada com ator e instante."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    />

    <UPageCard variant="subtle">
      <div
        v-if="loading && !consent"
        class="space-y-2"
        role="status"
        aria-label="Carregando consentimento"
      >
        <USkeleton class="h-4 w-1/2" />
        <USkeleton class="h-4 w-2/3" />
      </div>
      <div
        v-else
        class="space-y-4"
      >
        <UAlert
          v-if="needsAction"
          color="warning"
          icon="i-lucide-file-check"
          title="Concordância necessária"
          description="Uma nova finalidade ou versão exige nova aceitação antes do uso do certificado."
        />
        <UAlert
          v-else-if="consent?.accepted"
          color="success"
          icon="i-lucide-check"
          :title="`Consentimento aceito (versão ${consent.version})`"
          :description="consent.accepted_at
            ? `Em ${formatDateTime(consent.accepted_at)}${consent.accepted_by_name ? ` · ${consent.accepted_by_name}` : ''}`
            : undefined"
        />

        <div>
          <p class="mb-2 text-sm font-medium text-highlighted">
            Finalidades apresentadas
          </p>
          <ul class="list-disc space-y-1 ps-5 text-sm text-muted">
            <li
              v-for="p in purposes"
              :key="p.code"
            >
              <span class="text-highlighted">{{ p.label }}</span>
              <span
                v-if="p.description"
                class="block text-xs"
              >{{ p.description }}</span>
            </li>
          </ul>
        </div>

        <p
          v-if="consent?.text_summary"
          class="text-sm text-muted"
        >
          {{ consent.text_summary }}
        </p>

        <UCheckbox
          v-if="!readonly && needsAction"
          v-model="accepted"
          label="Li e autorizo o uso cifrado do certificado A1 do escritório nas finalidades listadas."
          data-testid="settings-consent-checkbox"
        />

        <div
          v-if="!readonly"
          class="flex flex-wrap justify-end gap-2 border-t border-default pt-4"
        >
          <UButton
            v-if="consent?.accepted && !consent.requires_reacceptance"
            color="neutral"
            variant="ghost"
            label="Revogar consentimento"
            icon="i-lucide-ban"
            :loading="saving"
            data-testid="settings-consent-revoke"
            @click="emit('revoke')"
          />
          <UButton
            v-if="needsAction"
            color="primary"
            label="Aceitar consentimento"
            icon="i-lucide-check"
            :loading="saving"
            :disabled="!accepted"
            data-testid="settings-consent-accept"
            @click="emit('accept')"
          />
        </div>
      </div>
    </UPageCard>
  </div>
</template>
