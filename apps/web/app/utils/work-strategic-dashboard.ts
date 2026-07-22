import type { WorkDepartment, WorkKpis } from '~/types/work'
import type { DashboardKpiItem } from '~/utils/kpi-ui'

export interface WorkDepartmentDashboardRow {
  id: number | null
  name: string
  open: number
  completed: number
  overdue: number
  fine: number
  unassigned: number
  completedPercent: number
  to: string
  overdueTo: string
}

export interface WorkQueueLegacyTarget {
  path: string
  query: Record<string, unknown>
}

const WORK_QUEUE_QUERY_KEYS = new Set([
  'tab',
  'q',
  'department_id',
  'assignee_membership_id',
  'client_id',
  'scope',
  'page',
  'per_page',
  'view',
  'sort',
  'direction',
  'task'
])

export function buildWorkDashboardKpis(data: WorkKpis): DashboardKpiItem[] {
  const kpis = data.kpis

  return [
    {
      key: 'open',
      title: 'Tarefas abertas',
      value: kpis.total_open,
      to: '/work/tasks',
      icon: 'i-lucide-inbox'
    },
    {
      key: 'overdue',
      title: 'Atrasadas',
      value: kpis.atrasadas,
      to: '/work/tasks?tab=atrasadas',
      icon: 'i-lucide-clock-alert',
      tone: 'warning',
      critical: kpis.atrasadas > 0
    },
    {
      key: 'fine',
      title: 'Em multa',
      value: kpis.em_multa,
      to: '/work/tasks?tab=atrasadas',
      icon: 'i-lucide-siren',
      tone: 'error',
      critical: kpis.em_multa > 0
    },
    {
      key: 'today',
      title: 'Vencem hoje',
      value: kpis.vence_hoje,
      to: '/work/tasks?tab=hoje',
      icon: 'i-lucide-calendar-days',
      tone: kpis.vence_hoje > 0 ? 'warning' : 'default'
    },
    {
      key: 'progress',
      title: 'Em progresso',
      value: kpis.em_progresso,
      to: '/work/tasks',
      icon: 'i-lucide-loader-circle',
      tone: 'info'
    },
    {
      key: 'unassigned',
      title: 'Sem responsável',
      value: kpis.sem_responsavel,
      to: '/work/tasks',
      icon: 'i-lucide-user-x',
      tone: kpis.sem_responsavel > 0 ? 'warning' : 'default'
    }
  ]
}

export function workCompletionPercent(data: WorkKpis): number {
  const total = data.kpis.total_open + data.kpis.concluidas
  if (total <= 0) return 0
  return Math.round((data.kpis.concluidas / total) * 100)
}

export function buildWorkDepartmentRows(
  data: WorkKpis,
  departments: WorkDepartment[]
): WorkDepartmentDashboardRow[] {
  const names = new Map(departments.map(department => [department.id, department.name]))

  return data.by_department
    .map(row => ({
      id: row.work_department_id,
      name: row.work_department_id == null
        ? 'Sem departamento'
        : (names.get(row.work_department_id) || `Departamento #${row.work_department_id}`),
      open: row.open,
      completed: row.completed,
      overdue: row.overdue,
      fine: row.fine,
      unassigned: row.unassigned,
      completedPercent: row.completed_percent,
      to: row.work_department_id == null
        ? '/work/tasks'
        : `/work/tasks?department_id=${row.work_department_id}`,
      overdueTo: row.work_department_id == null
        ? '/work/tasks?tab=atrasadas'
        : `/work/tasks?tab=atrasadas&department_id=${row.work_department_id}`
    }))
    .sort((a, b) => b.open - a.open || b.overdue - a.overdue || a.name.localeCompare(b.name, 'pt-BR'))
}

function positiveInteger(value: unknown): number | null {
  const raw = Array.isArray(value) ? value[0] : value
  if (raw === undefined || raw === null || String(raw).trim() === '') return null
  const parsed = Number(raw)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

export function workQueueLegacyTarget(
  query: Record<string, unknown>
): WorkQueueLegacyTarget | null {
  if (!Object.keys(query).some(key => WORK_QUEUE_QUERY_KEYS.has(key))) return null

  const nextQuery = { ...query }
  const taskId = positiveInteger(nextQuery.task)
  delete nextQuery.task

  return {
    path: taskId == null ? '/work/tasks' : `/work/tasks/${taskId}`,
    query: nextQuery
  }
}
