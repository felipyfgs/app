<script setup lang="ts">
import { VisSingleContainer, VisDonut, VisTooltip } from '@unovis/vue'
import type { MonitoringInsightsSitfis } from '~/types/monitoring-insights'

const props = defineProps<{
  data: MonitoringInsightsSitfis | null
  loading?: boolean
  error?: string | null
}>()

const chartCard = useTemplateRef<HTMLElement | null>('chartCard')
const { width: measuredWidth } = useElementSize(chartCard)

type Slice = { key: string, label: string, value: number, color: string }

const slices = computed((): Slice[] => {
  const c = props.data?.counters
  if (!c) return []
  const other = (c.error ?? 0) + (c.processing ?? 0) + (c.unknown ?? 0) + (c.blocked ?? 0)
  const rows: Slice[] = [
    { key: 'up', label: 'Em dia', value: c.up_to_date ?? 0, color: 'var(--ui-success)' },
    { key: 'pending', label: 'Pendentes', value: c.pending ?? 0, color: 'var(--ui-warning)' },
    { key: 'attention', label: 'Atenção', value: c.attention ?? 0, color: 'var(--ui-error)' }
  ]
  if (other > 0) {
    rows.push({ key: 'other', label: 'Outros', value: other, color: 'var(--ui-neutral)' })
  }
  return rows.filter(r => r.value > 0)
})

const value = (d: Slice) => d.value
const color = (d: Slice) => d.color
const total = computed(() => slices.value.reduce((s, r) => s + r.value, 0))
</script>

<template>
  <UPageCard
    ref="chartCard"
    variant="subtle"
    data-testid="insights-sitfis-donut-card"
  >
    <template #header>
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-xs uppercase text-muted">
            Situação fiscal
          </p>
          <p class="mt-1 text-sm text-muted">
            Distribuição da carteira SITFIS.
          </p>
        </div>
        <UButton
          size="xs"
          color="neutral"
          variant="ghost"
          label="Abrir"
          to="/monitoring/sitfis"
        />
      </div>
    </template>

    <p
      v-if="error"
      class="text-sm text-error"
    >
      {{ error }}
    </p>
    <div
      v-else-if="loading && !data"
      class="py-8 text-center text-sm text-muted"
    >
      Carregando…
    </div>
    <div
      v-else
      class="flex flex-wrap items-center gap-4"
    >
      <ClientOnly>
        <VisSingleContainer
          v-if="slices.length && measuredWidth > 40"
          :data="slices"
          class="size-36"
          :width="144"
          :height="144"
        >
          <VisDonut
            :value="value"
            :color="color"
            :arc-width="18"
          />
          <VisTooltip />
        </VisSingleContainer>
        <div
          v-else
          class="flex size-36 items-center justify-center text-sm text-muted"
        >
          Sem dados
        </div>
      </ClientOnly>
      <ul class="min-w-0 flex-1 space-y-2 text-sm">
        <li
          v-for="row in slices"
          :key="row.key"
          class="flex items-center justify-between gap-3"
        >
          <span class="inline-flex items-center gap-2 text-muted">
            <span
              class="size-2 rounded-full"
              :style="{ background: row.color }"
            />
            {{ row.label }}
          </span>
          <span class="tabular-nums font-medium text-highlighted">{{ row.value }}</span>
        </li>
        <li
          v-if="total"
          class="border-t border-default pt-2 text-xs text-muted"
        >
          Total: {{ total }}
        </li>
      </ul>
    </div>
  </UPageCard>
</template>
