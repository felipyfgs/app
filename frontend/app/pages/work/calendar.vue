<script setup lang="ts">
/**
 * Calendário operacional — Mês / Semana / Dia.
 * Shell Home (navbar + toolbar); UCalendar só como minicalendário.
 * Sem grade horária nem compromissos fictícios.
 */
import { CalendarDate, type DateValue } from '@internationalized/date'
import { breakpointsTailwind } from '@vueuse/core'
import type { OperationalTaskSummary } from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import {
  formatDueDate,
  highestRiskColor,
  taskStatusLabel,
  workRiskLabel
} from '~/utils/work-labels'
import { useWorkCalendarRange } from '~/composables/useWorkCalendarRange'

interface DayAgg {
  date: string
  total: number
  overdue?: number
  fine?: number
  completed?: number
  open?: number
  max_severity?: number
  items?: OperationalTaskSummary[]
}

const api = useApi()
const route = useRoute()
const toast = useToast()
const { sessionEpoch } = useDashboard()
const {
  view, date, range, label, setView, setDate, navigate, monthGrid, weekDates, parseYmd
} = useWorkCalendarRange()

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const days = ref<DayAgg[]>([])
const dayItems = ref<OperationalTaskSummary[]>([])
const loading = ref(false)
const dayLoading = ref(false)
const loadError = ref<string | null>(null)
const lastGoodDays = ref<DayAgg[]>([])
const railOpen = ref(false)
const railTab = ref<'tarefas' | 'atrasadas' | 'concluidas'>('tarefas')

const viewItems = [
  { label: 'Mês', value: 'month' },
  { label: 'Semana', value: 'week' },
  { label: 'Dia', value: 'day' }
]

const selectedView = computed({
  get: () => view.value,
  set: (v: string) => { void setView(v as 'month' | 'week' | 'day') }
})

const dayMap = computed(() => {
  const m = new Map<string, DayAgg>()
  for (const d of days.value) m.set(d.date, d)
  return m
})

const calendarModel = computed({
  get: () => {
    const { y, m, d } = parseYmd(date.value)
    return new CalendarDate(y, m, d)
  },
  set: (value: DateValue | undefined | null) => {
    if (!value) return
    void setDate(`${value.year}-${String(value.month).padStart(2, '0')}-${String(value.day).padStart(2, '0')}`)
  }
})

const filterParams = computed(() => {
  const q = route.query
  const out: Record<string, string | number> = {}
  if (q.department_id) out.department_id = Number(q.department_id)
  if (q.assignee_membership_id) out.assignee_membership_id = Number(q.assignee_membership_id)
  if (q.client_id) out.client_id = Number(q.client_id)
  if (q.status) out.status = String(q.status)
  if (q.risk) out.risk = String(q.risk)
  return out
})

async function loadInterval() {
  const epoch = sessionEpoch.value
  loading.value = true
  if (!lastGoodDays.value.length) loadError.value = null
  try {
    const res = await api.work.calendar(range.value.from, range.value.to, filterParams.value)
    if (epoch !== sessionEpoch.value) return
    days.value = res.data.days as DayAgg[]
    lastGoodDays.value = days.value
    loadError.value = null
  } catch (e) {
    if (epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(e, 'Falha ao carregar calendário.')
    if (lastGoodDays.value.length) {
      days.value = lastGoodDays.value
      toast.add({ title: loadError.value + ' Exibindo última carga válida.', color: 'warning' })
    } else {
      toast.add({ title: loadError.value, color: 'error' })
    }
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

async function loadDay() {
  const epoch = sessionEpoch.value
  dayLoading.value = true
  try {
    const res = await api.work.calendarDay(date.value, {
      per_page: 50,
      ...filterParams.value
    })
    if (epoch !== sessionEpoch.value) return
    dayItems.value = res.data as OperationalTaskSummary[]
  } catch (e) {
    if (epoch !== sessionEpoch.value) return
    toast.add({ title: apiErrorMessage(e, 'Falha ao carregar o dia.'), color: 'error' })
    dayItems.value = []
  } finally {
    if (epoch === sessionEpoch.value) dayLoading.value = false
  }
}

async function openDay(d: string) {
  await setDate(d)
  if (isMobile.value) railOpen.value = true
  await loadDay()
}

const weekLanes = computed(() => weekDates(date.value).map(d => ({
  date: d,
  agg: dayMap.value.get(d),
  items: (dayMap.value.get(d)?.items || []) as OperationalTaskSummary[]
})))

const monthCells = computed(() => {
  const { y, m } = parseYmd(date.value)
  return monthGrid(y, m).map(cell => ({
    ...cell,
    agg: dayMap.value.get(cell.date)
  }))
})

const railItems = computed(() => {
  const list = dayItems.value
  if (railTab.value === 'atrasadas') {
    return list.filter(i => i.risks?.includes('ATRASADA') || i.risks?.includes('EM_MULTA'))
  }
  if (railTab.value === 'concluidas') {
    return list.filter(i => i.status === 'CONCLUIDA' || i.status === 'DISPENSADA')
  }
  return list
})

const severityClass = (agg?: DayAgg) => {
  if (!agg?.total) return ''
  if ((agg.fine || 0) > 0 || (agg.max_severity || 0) >= 3) return 'bg-error/15 text-error'
  if ((agg.overdue || 0) > 0 || (agg.max_severity || 0) >= 2) return 'bg-warning/15 text-warning'
  return 'bg-primary/10 text-primary'
}

watch(
  [range, filterParams, sessionEpoch],
  () => {
    void loadInterval()
    void loadDay()
  },
  { immediate: true, deep: true }
)

watch(sessionEpoch, () => {
  days.value = []
  dayItems.value = []
  lastGoodDays.value = []
  loadError.value = null
})
</script>

<template>
  <UDashboardPanel id="work-calendar-main" class="min-w-0" data-testid="work-calendar">
    <template #header>
      <UDashboardNavbar title="Calendário operacional">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <div class="flex items-center gap-1">
            <UButton
              icon="i-lucide-chevron-left"
              color="neutral"
              variant="ghost"
              aria-label="Período anterior"
              @click="() => { void navigate(-1) }"
            />
            <UButton
              color="neutral"
              variant="ghost"
              size="sm"
              label="Hoje"
              @click="() => { void navigate(0) }"
            />
            <UButton
              icon="i-lucide-chevron-right"
              color="neutral"
              variant="ghost"
              aria-label="Próximo período"
              @click="() => { void navigate(1) }"
            />
          </div>
          <span class="ms-2 hidden text-sm font-medium sm:inline" aria-live="polite">
            {{ label }}
          </span>
          <UTabs
            v-model="selectedView"
            :items="viewItems"
            :content="false"
            size="xs"
            class="ms-2"
          />
          <UButton
            class="lg:hidden"
            icon="i-lucide-panel-right"
            color="neutral"
            variant="ghost"
            aria-label="Abrir painel do dia"
            @click="() => { railOpen = true }"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <h1 class="sr-only">
        Calendário operacional
      </h1>

      <div v-if="loadError && !days.length" class="p-4">
        <UAlert color="error" :title="loadError">
          <template #actions>
            <UButton size="xs" label="Tentar de novo" @click="loadInterval" />
          </template>
        </UAlert>
      </div>

      <div v-else-if="loading && !days.length" class="p-4 space-y-3">
        <USkeleton class="h-64 w-full" />
      </div>

      <!-- Mês -->
      <div v-else-if="view === 'month'" class="p-2 sm:p-4" data-testid="work-calendar-month">
        <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-medium text-muted">
          <span v-for="wd in ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']" :key="wd">{{ wd }}</span>
        </div>
        <div class="grid grid-cols-7 gap-1">
          <button
            v-for="cell in monthCells"
            :key="cell.date"
            type="button"
            class="min-h-16 rounded-md border border-default p-1 text-left transition-colors hover:bg-elevated/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :class="[
              !cell.inMonth && 'opacity-40',
              cell.date === date && 'ring-2 ring-primary',
              severityClass(cell.agg)
            ]"
            :aria-label="`${cell.date}${cell.agg?.total ? `, ${cell.agg.total} tarefas` : ''}`"
            @click="openDay(cell.date)"
          >
            <span class="text-xs font-medium">{{ parseYmd(cell.date).d }}</span>
            <span v-if="cell.agg?.total" class="mt-1 block text-xs font-semibold">
              {{ cell.agg.total }}
            </span>
          </button>
        </div>
      </div>

      <!-- Semana: 7 lanes por data, sem eixo de horas -->
      <div v-else-if="view === 'week'" class="overflow-x-auto p-2 sm:p-4" data-testid="work-calendar-week">
        <div class="grid min-w-[640px] grid-cols-7 gap-2">
          <div
            v-for="lane in weekLanes"
            :key="lane.date"
            class="min-h-48 rounded-md border border-default p-2"
            :class="lane.date === date ? 'ring-2 ring-primary' : ''"
          >
            <button
              type="button"
              class="mb-2 w-full text-left text-sm font-medium hover:underline"
              @click="openDay(lane.date)"
            >
              {{ formatDueDate(lane.date) }}
              <UBadge
                v-if="lane.agg?.total"
                size="sm"
                variant="subtle"
                class="ms-1"
                :label="String(lane.agg.total)"
              />
            </button>
            <div class="space-y-1">
              <button
                v-for="item in lane.items"
                :key="item.id"
                type="button"
                class="w-full rounded border border-default p-1.5 text-left text-xs hover:bg-elevated/50"
                @click="navigateTo(`/work?task=${item.id}`)"
              >
                <p class="truncate font-medium">
                  {{ item.title }}
                </p>
                <p class="truncate text-muted">
                  {{ item.process?.client?.name }}
                </p>
              </button>
              <p v-if="!lane.items.length" class="text-xs text-muted">
                —
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Dia: fila detalhada -->
      <div v-else class="p-2 sm:p-4" data-testid="work-calendar-day">
        <div v-if="dayLoading" class="space-y-2">
          <USkeleton v-for="i in 5" :key="i" class="h-14 w-full" />
        </div>
        <div v-else-if="!dayItems.length" class="text-sm text-muted">
          Nenhuma tarefa com prazo em {{ formatDueDate(date) }}.
        </div>
        <ul v-else class="divide-y divide-default rounded-md border border-default">
          <li
            v-for="item in dayItems"
            :key="item.id"
            class="cursor-pointer p-3 hover:bg-elevated/50"
            @click="navigateTo(`/work?task=${item.id}`)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="truncate font-medium">
                  {{ item.title }}
                </p>
                <p class="truncate text-xs text-muted">
                  {{ item.process?.client?.name }} · {{ item.process?.title }}
                </p>
              </div>
              <UBadge
                size="sm"
                variant="subtle"
                :color="highestRiskColor(item.risks)"
                :label="item.risks?.[0] ? workRiskLabel(item.risks[0]) : taskStatusLabel(item.status)"
              />
            </div>
          </li>
        </ul>
      </div>
    </template>
  </UDashboardPanel>

  <!-- Rail desktop -->
  <UDashboardPanel
    id="work-calendar-rail"
    class="hidden lg:flex"
    :default-size="22"
    :min-size="18"
    :max-size="28"
    resizable
  >
    <template #header>
      <UDashboardNavbar title="Dia selecionado" :toggle="false" />
    </template>
    <template #body>
      <div class="flex flex-col gap-4 p-3">
        <UCalendar v-model="calendarModel" class="w-full" />
        <UTabs
          v-model="railTab"
          :items="[
            { label: 'Tarefas', value: 'tarefas' },
            { label: 'Atrasadas', value: 'atrasadas' },
            { label: 'Concluídas', value: 'concluidas' }
          ]"
          :content="false"
          size="xs"
        />
        <div v-if="dayLoading" class="space-y-2">
          <USkeleton v-for="i in 4" :key="i" class="h-10 w-full" />
        </div>
        <ul v-else class="space-y-1">
          <li
            v-for="item in railItems"
            :key="item.id"
            class="cursor-pointer rounded-md border border-default p-2 text-sm hover:bg-elevated/50"
            @click="navigateTo(`/work?task=${item.id}`)"
          >
            <p class="truncate font-medium">
              {{ item.title }}
            </p>
            <p class="truncate text-xs text-muted">
              {{ taskStatusLabel(item.status) }}
            </p>
          </li>
          <li v-if="!railItems.length" class="text-xs text-muted">
            Nenhuma tarefa nesta lista.
          </li>
        </ul>
      </div>
    </template>
  </UDashboardPanel>

  <!-- Rail mobile -->
  <USlideover v-model:open="railOpen" title="Dia selecionado" class="lg:hidden">
    <template #body>
      <div class="flex flex-col gap-4 p-3">
        <UCalendar v-model="calendarModel" class="w-full" />
        <ul class="space-y-1">
          <li
            v-for="item in dayItems"
            :key="item.id"
            class="cursor-pointer rounded-md border border-default p-2 text-sm"
            @click="navigateTo(`/work?task=${item.id}`)"
          >
            {{ item.title }}
          </li>
          <li v-if="!dayItems.length" class="text-xs text-muted">
            Nenhuma tarefa.
          </li>
        </ul>
      </div>
    </template>
  </USlideover>
</template>
