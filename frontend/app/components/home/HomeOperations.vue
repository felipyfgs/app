<script setup lang="ts">
import type { InboxItem, OperationsSummary } from '~/types/api'

const props = defineProps<{
  summary: OperationsSummary | null
  loading?: boolean
  error?: string | null
  inboxItems?: InboxItem[]
  inboxLoading?: boolean
}>()

const emit = defineEmits<{
  retry: []
}>()

const attentionTotal = computed(() => {
  if (!props.summary) return 0
  if (typeof props.summary.inbox_total === 'number') {
    // Exports pendentes não viram item de inbox; mantém no total de atenção do home.
    return props.summary.inbox_total + (props.summary.exports_pending || 0)
  }
  return props.summary.sync_failures_24h
    + props.summary.sync_blocked
    + props.summary.credentials_expiring_30d
    + props.summary.exports_pending
})

const topItems = computed(() => (props.inboxItems || []).slice(0, 5))

const backup = computed(() => props.summary?.backup)

function severityColor(severity: string): 'error' | 'warning' | 'info' | 'neutral' {
  return inboxSeverityColor(severity)
}

function itemLink(item: InboxItem): string {
  if (String(item.type).startsWith('credential') && item.links?.credential) {
    return item.links.credential
  }
  if (item.links?.sync) return item.links.sync
  if (item.links?.client) return item.links.client
  return '/health'
}
</script>

<template>
  <UCard data-testid="home-operations" class="shrink-0" :ui="{ body: 'pt-3!' }">
    <template #header>
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-xs text-muted uppercase mb-1.5">
            Atenção operacional
          </p>
          <p class="text-3xl text-highlighted font-semibold">
            {{ loading && !summary ? '---' : attentionTotal }}
          </p>
        </div>
        <UButton
          to="/health"
          color="neutral"
          variant="ghost"
          size="sm"
          label="Ver todos"
          trailing-icon="i-lucide-arrow-right"
        />
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

    <UAlert
      v-if="backup?.never"
      color="error"
      variant="subtle"
      icon="i-lucide-database-zap"
      title="Nenhum backup bem-sucedido"
      description="A instância ainda não registrou backup SUCCESS. Execute o backup operacional antes do piloto com dados reais."
      class="mb-4"
      :actions="[{ label: 'Ver saúde', to: '/health', color: 'neutral', variant: 'outline' }]"
    />
    <UAlert
      v-else-if="backup?.stale"
      color="warning"
      variant="subtle"
      icon="i-lucide-database-backup"
      title="Backup da instância atrasado"
      :description="backup.last_success_at
        ? `Último sucesso: ${formatDateTime(backup.last_success_at)}. Mais de 24 horas sem backup OK.`
        : 'Mais de 24 horas sem backup SUCCESS registrado.'"
      class="mb-4"
      :actions="[{ label: 'Ver saúde', to: '/health', color: 'neutral', variant: 'outline' }]"
    />

    <div v-if="topItems.length" class="space-y-2 mb-4">
      <NuxtLink
        v-for="item in topItems"
        :key="item.id"
        :to="itemLink(item)"
        class="flex min-w-0 items-start gap-3 rounded-md border border-default px-3 py-2.5 hover:bg-elevated/50"
      >
        <UBadge
          :color="severityColor(item.severity)"
          variant="subtle"
          class="mt-0.5 shrink-0 capitalize"
        >
          {{ item.severity }}
        </UBadge>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-highlighted">
            {{ item.title }}
          </p>
          <p class="truncate text-xs text-muted">
            {{ item.body }}
          </p>
        </div>
        <UIcon name="i-lucide-chevron-right" class="mt-1 size-4 shrink-0 text-muted" />
      </NuxtLink>
    </div>

    <div v-else-if="summary" class="grid gap-3 sm:grid-cols-2">
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
        v-if="attentionTotal === 0 && !backup?.stale && !backup?.never"
        color="success"
        variant="subtle"
        icon="i-lucide-circle-check"
        title="Operação sem alertas críticos"
        description="Nenhuma falha recente, cursor bloqueado, certificado ou backup exigindo atenção."
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
      v-else-if="loading || inboxLoading"
      class="grid gap-3 sm:grid-cols-2"
      role="status"
      aria-label="Carregando resumo operacional"
    >
      <USkeleton v-for="index in 4" :key="index" class="h-24 w-full" />
    </div>
  </UCard>
</template>
