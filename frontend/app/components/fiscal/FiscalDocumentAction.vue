<script setup lang="ts">
/**
 * Ação de evidência oficial: botão só com available=true e href do servidor.
 * Sem Base64, JSON bruto ou URL inventada no frontend.
 */
import type { FiscalDocumentDescriptor } from '~/types/fiscal-modules'
import {
  documentActionVisible,
  documentUnavailableLabel
} from '~/types/fiscal-modules'

const props = withDefaults(defineProps<{
  document?: FiscalDocumentDescriptor | null
  /**
   * Quando true e o documento está indisponível com motivo público,
   * exibe texto muted (nunca botão). Superfícies NEVER devem deixar false.
   */
  showUnavailable?: boolean
  size?: 'xs' | 'sm' | 'md'
  /** Força ocultar (ex.: submódulo MIT, surface allows_document=false). */
  disabled?: boolean
}>(), {
  document: null,
  showUnavailable: false,
  size: 'xs',
  disabled: false
})

const visible = computed(() =>
  !props.disabled && documentActionVisible(props.document)
)

const label = computed(() => {
  const raw = props.document?.label
  if (typeof raw === 'string' && raw.trim()) return raw.trim()
  return 'Ver/Baixar documento oficial'
})

const unavailableText = computed(() => {
  if (!props.showUnavailable || props.disabled || visible.value) return null
  return documentUnavailableLabel(props.document?.unavailable_reason)
})

const href = computed(() => {
  if (!visible.value) return null
  const h = props.document?.href
  return typeof h === 'string' && h.trim() ? h.trim() : null
})
</script>

<template>
  <UButton
    v-if="visible && href"
    :size="size"
    color="primary"
    variant="ghost"
    icon="i-lucide-file-down"
    :label="label"
    :href="href"
    target="_blank"
    rel="noopener"
    data-testid="fiscal-document-action"
  />
  <span
    v-else-if="unavailableText"
    class="text-xs text-muted"
    data-testid="fiscal-document-unavailable"
  >
    {{ unavailableText }}
  </span>
</template>
