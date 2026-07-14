<script setup lang="ts">
import type { OperationsSummary } from '~/types/api'

const props = defineProps<{
  summary: OperationsSummary | null
  loading?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  retry: []
}>()

const attentionTotal = computed(() => {
  if (!props.summary) return 0
  return props.summary.sync_failures_24h
    + props.summary.sync_blocked
    + props.summary.credentials_expiring_30d
    + props.summary.exports_pending
})
</script>

<template>
  <UCard data-testid="home-operations" class="shrink-0" :ui="{ body: 'pt-3!' }">
    <template #header>
      <div>
        <p class="text-xs text-muted uppercase mb-1.5">
          Atenção operacional
        </p>
        <p class="text-3xl text-highlighted font-semibold">
          {{ loading && !summary ? '---' : attentionTotal }}
        </p>
      </div>
    </template>

    <UAlert
      v-if="error && summary"
      color="warning"
      variant="subtle"
      icon="i-lucide-wifi-off"
      title="Falha ao atualizar"
      :description="error"
      class="mb-4"
      :actions="[{
        label: 'Tentar novamente',
        color: 'neutral',
        variant: 'subtle',
        onClick: () => emit('retry')
      }]"
    />

    <div v-if="summary" class="grid gap-3 sm:grid-cols-2">
      <UAlert
        v-if="summary.sync_failures_24h"
        color="error"
        variant="subtle"
        icon="i-lucide-circle-x"
        title="Falhas de sincronização nas últimas 24 horas"
        :description="`${summary.sync_failures_24h} execução(ões) exigem revisão do histórico.`"
        :actions="[{ label: 'Ver histórico', to: '/syncs', color: 'neutral', variant: 'outline' }]"
      />
      <UAlert
        v-if="summary.sync_blocked"
        color="error"
        variant="subtle"
        icon="i-lucide-ban"
        title="Cursores bloqueados"
        :description="`${summary.sync_blocked} estabelecimento(s) com cursor bloqueado após falhas de decodificação.`"
        :actions="[{ label: 'Ver sincronizações', to: '/syncs', color: 'neutral', variant: 'outline' }]"
      />
      <UAlert
        v-if="summary.credentials_expiring_30d"
        color="warning"
        variant="subtle"
        icon="i-lucide-badge-alert"
        title="Certificados próximos do vencimento"
        :description="`${summary.credentials_expiring_30d} certificado(s) vencem em até 30 dias.`"
        :actions="[{ label: 'Ver clientes', to: '/clients', color: 'neutral', variant: 'outline' }]"
      />
      <UAlert
        v-if="summary.exports_pending"
        color="primary"
        variant="subtle"
        icon="i-lucide-loader-circle"
        title="Exportações em processamento"
        :description="`${summary.exports_pending} solicitação(ões) estão na fila.`"
        :actions="[{ label: 'Acompanhar', to: '/exports', color: 'neutral', variant: 'outline' }]"
      />
      <UAlert
        v-if="attentionTotal === 0"
        color="success"
        variant="subtle"
        icon="i-lucide-circle-check"
        title="Operação sem alertas críticos"
        description="Nenhuma falha recente, cursor bloqueado ou certificado próximo do vencimento."
        class="sm:col-span-2"
      />
    </div>

    <UEmpty
      v-else-if="!loading && error"
      icon="i-lucide-cloud-off"
      title="Resumo indisponível"
      :description="error"
    >
      <UButton color="neutral" variant="subtle" label="Tentar novamente" @click="emit('retry')" />
    </UEmpty>

    <div
      v-else
      class="grid gap-3 sm:grid-cols-2"
      role="status"
      aria-label="Carregando resumo operacional"
    >
      <USkeleton v-for="index in 4" :key="index" class="h-24 w-full" />
    </div>
  </UCard>
</template>
