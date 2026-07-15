<script setup lang="ts">
/**
 * Dashboard de clientes — arquétipo Home do template Nuxt UI Dashboard.
 * Fonte: .reference/nuxt-dashboard-template/app/pages/index.vue
 *   → HomeStats + HomeChart + HomeSales
 * Demo: https://dashboard-template.nuxt.dev/
 *
 * Dados reais do escritório (sem %/séries inventadas do mock).
 */
import type { TableColumn } from '@nuxt/ui'
import type { Client, ClientListStats } from '~/types/api'
import { COMPACT_DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import {
  eachMonthOfInterval,
  format,
  isValid,
  parseISO,
  startOfMonth,
  subMonths
} from 'date-fns'
import { ptBR } from 'date-fns/locale'
import {
  VisXYContainer,
  VisLine,
  VisArea,
  VisAxis,
  VisCrosshair,
  VisTooltip
} from '@unovis/vue'

const props = defineProps<{
  clients: Client[]
  stats: ClientListStats
  loading?: boolean
}>()

const UBadge = resolveComponent('UBadge')

// ─── Stats (HomeStats) ───────────────────────────────────────────────────────

const statsCards = computed(() => {
  const ok = props.clients.filter((c) => {
    const s = c.credential_summary
    if (!s) return false
    const expired = s.status === 'EXPIRED'
      || !!(s.valid_to && new Date(s.valid_to) < new Date())
    return !expired
      && !s.expires_alert_1
      && !s.expires_alert_7
      && !s.expires_alert_30
  }).length

  return [
    {
      title: 'Clientes',
      icon: 'i-lucide-users',
      value: props.stats.total || props.clients.length
    },
    {
      title: 'Ativos',
      icon: 'i-lucide-circle-check',
      value: props.stats.active
    },
    {
      title: 'A1 OK',
      icon: 'i-lucide-badge-check',
      value: ok
    },
    {
      title: 'A vencer / sem A1',
      icon: 'i-lucide-badge-alert',
      value: (props.stats.credential_expiring_30d || 0)
        + (props.stats.without_credential || 0)
    }
  ]
})

// ─── Chart (HomeChart) — acumulado mensal real ───────────────────────────────

type DataRecord = { date: Date, amount: number }

const chartCard = useTemplateRef<HTMLElement | null>('chartCard')
const { width: measuredWidth } = useElementSize(chartCard)
/** Evita gráfico branco: Unovis com width=0 não desenha. */
const chartWidth = computed(() => Math.max(measuredWidth.value || 0, 320))
const chartReady = computed(() => (measuredWidth.value || 0) > 40)

const chartData = computed((): DataRecord[] => {
  const dates = props.clients
    .map((c) => {
      if (!c.created_at) return null
      const d = parseISO(c.created_at)
      return isValid(d) ? d : null
    })
    .filter((d): d is Date => !!d)
    .sort((a, b) => a.getTime() - b.getTime())

  const end = startOfMonth(new Date())
  // Sempre 12 meses para a linha ter extensão (demo costuma nascer no mesmo dia).
  const start = startOfMonth(subMonths(end, 11))
  const months = eachMonthOfInterval({ start, end })

  const byMonth = new Map<string, number>()
  for (const d of dates) {
    const key = format(startOfMonth(d), 'yyyy-MM')
    byMonth.set(key, (byMonth.get(key) || 0) + 1)
  }

  // Conta cadastros anteriores à janela para o acumulado não “começar do zero” no meio.
  let cumulative = 0
  for (const d of dates) {
    if (d < start) cumulative += 1
  }

  return months.map((m) => {
    cumulative += byMonth.get(format(m, 'yyyy-MM')) || 0
    return { date: m, amount: cumulative }
  })
})

const chartTotal = computed(() =>
  props.stats.total || props.clients.length
    || (chartData.value.length ? chartData.value[chartData.value.length - 1]!.amount : 0)
)

const x = (_: DataRecord, i: number) => i
const y = (d: DataRecord) => d.amount

const formatDate = (date: Date) => format(date, 'MMM yy', { locale: ptBR })

const xTicks = (i: number) => {
  if (!chartData.value[i]) return ''
  const step = Math.max(1, Math.floor((chartData.value.length - 1) / 5))
  if (i === 0 || i === chartData.value.length - 1 || i % step === 0) {
    return formatDate(chartData.value[i]!.date)
  }
  return ''
}

const template = (d: DataRecord) =>
  `${formatDate(d.date)}: ${d.amount} cliente(s)`

// ─── Sales table → últimos clientes (HomeSales) ──────────────────────────────

type RecentRow = {
  id: number
  name: string
  cnpj: string
  status: 'active' | 'inactive'
  date: string | null
  a1: string
}

const recentRows = computed((): RecentRow[] => {
  return [...props.clients]
    .sort((a, b) => {
      const ta = a.created_at ? new Date(a.created_at).getTime() : 0
      const tb = b.created_at ? new Date(b.created_at).getTime() : 0
      return tb - ta
    })
    .slice(0, 8)
    .map((c) => {
      const s = c.credential_summary
      let a1 = 'Sem A1'
      if (s) {
        const expired = s.status === 'EXPIRED'
          || !!(s.valid_to && new Date(s.valid_to) < new Date())
        if (expired) a1 = 'Vencido'
        else if (s.expires_alert_1 || s.expires_alert_7 || s.expires_alert_30) a1 = 'A vencer'
        else a1 = 'OK'
      }
      return {
        id: c.id,
        name: c.legal_name || c.name,
        cnpj: formatCnpj(c.cnpj || c.root_cnpj),
        status: c.is_active ? 'active' : 'inactive',
        date: c.created_at || null,
        a1
      }
    })
})

const columns: TableColumn<RecentRow>[] = [
  {
    accessorKey: 'id',
    header: 'ID',
    cell: ({ row }) => `#${row.original.id}`
  },
  {
    accessorKey: 'date',
    header: 'Cadastro',
    cell: ({ row }) => {
      if (!row.original.date) return '—'
      return formatDateTime(row.original.date)
    }
  },
  {
    accessorKey: 'status',
    header: 'Estado',
    cell: ({ row }) => {
      const active = row.original.status === 'active'
      return h(UBadge, {
        class: 'capitalize',
        variant: 'subtle',
        color: active ? 'success' : 'neutral'
      }, () => (active ? 'Ativo' : 'Inativo'))
    }
  },
  {
    accessorKey: 'name',
    header: 'Cliente'
  },
  {
    accessorKey: 'cnpj',
    header: 'CNPJ',
    cell: ({ row }) => h('span', { class: 'font-mono text-sm' }, row.original.cnpj)
  },
  {
    accessorKey: 'a1',
    header: () => h('div', { class: 'text-right' }, 'A1'),
    cell: ({ row }) => {
      const color = {
        OK: 'success' as const,
        'A vencer': 'warning' as const,
        Vencido: 'error' as const,
        'Sem A1': 'neutral' as const
      }[row.original.a1] || 'neutral' as const
      return h('div', { class: 'text-right' }, [
        h(UBadge, { variant: 'subtle', color }, () => row.original.a1)
      ])
    }
  }
]
</script>

<template>
  <div
    class="flex w-full flex-col gap-4 sm:gap-6 lg:gap-8"
    data-testid="clients-dashboard"
  >
    <!-- HomeStats -->
    <UPageGrid class="lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-px">
      <UPageCard
        v-for="(stat, index) in statsCards"
        :key="index"
        :icon="stat.icon"
        :title="stat.title"
        variant="subtle"
        :ui="{
          container: 'gap-y-1.5',
          wrapper: 'items-start',
          leading: 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col',
          title: 'font-normal text-muted text-xs uppercase'
        }"
        class="lg:rounded-none first:rounded-l-lg last:rounded-r-lg"
      >
        <div class="flex items-center gap-2">
          <span class="text-2xl font-semibold text-highlighted tabular-nums">
            {{ loading && !clients.length ? '…' : stat.value }}
          </span>
        </div>
      </UPageCard>
    </UPageGrid>

    <!-- HomeChart (Unovis) -->
    <UCard
      ref="chartCard"
      class="shrink-0"
      :ui="{ root: 'overflow-visible', body: 'px-0! pt-0! pb-3!' }"
    >
      <template #header>
        <div>
          <p class="text-xs text-muted uppercase mb-1.5">
            Base de clientes
          </p>
          <p class="text-3xl text-highlighted font-semibold tabular-nums">
            {{ loading && !clients.length ? '…' : chartTotal }}
          </p>
        </div>
      </template>

      <ClientOnly>
        <VisXYContainer
          v-if="chartReady && chartData.length"
          :data="chartData"
          :padding="{ top: 40, left: 16, right: 16 }"
          class="h-96 w-full"
          :width="chartWidth"
        >
          <VisLine
            :x="x"
            :y="y"
            color="var(--ui-primary)"
          />
          <VisArea
            :x="x"
            :y="y"
            color="var(--ui-primary)"
            :opacity="0.12"
          />
          <VisAxis
            type="x"
            :x="x"
            :tick-format="xTicks"
          />
          <VisAxis type="y" />
          <VisCrosshair
            color="var(--ui-primary)"
            :template="template"
          />
          <VisTooltip />
        </VisXYContainer>
        <div
          v-else
          class="flex h-96 items-center justify-center text-sm text-muted"
        >
          {{ loading ? 'Carregando…' : (chartData.length ? 'Montando gráfico…' : 'Sem datas de cadastro para o gráfico.') }}
        </div>
        <template #fallback>
          <div class="h-96" />
        </template>
      </ClientOnly>
    </UCard>

    <!-- HomeSales → últimos clientes -->
    <UTable
      :data="recentRows"
      :columns="columns"
      :loading="loading"
      class="shrink-0"
      :ui="COMPACT_DASHBOARD_TABLE_UI"
    />
  </div>
</template>

<style scoped>
.unovis-xy-container {
  --vis-crosshair-line-stroke-color: var(--ui-primary);
  --vis-crosshair-circle-stroke-color: var(--ui-bg);

  --vis-axis-grid-color: var(--ui-border);
  --vis-axis-tick-color: var(--ui-border);
  --vis-axis-tick-label-color: var(--ui-text-dimmed);

  --vis-tooltip-background-color: var(--ui-bg);
  --vis-tooltip-border-color: var(--ui-border);
  --vis-tooltip-text-color: var(--ui-text-highlighted);
}
</style>
