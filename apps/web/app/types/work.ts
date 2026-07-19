/** Tipos do módulo operacional (Work) — alinhados à API /api/v1/work/* */

export type ProcessOrigin = 'TEMPLATE' | 'MANUAL'
export type ProcessStatus = 'A_FAZER' | 'EM_PROGRESSO' | 'IMPEDIDO' | 'CONCLUIDO' | 'ARQUIVADO'
export type TaskStatus = 'A_FAZER' | 'EM_PROGRESSO' | 'IMPEDIDA' | 'CONCLUIDA' | 'DISPENSADA'
export type DueRuleType
  = | 'FIXED_DAY_OF_COMPETENCE'
    | 'DAYS_AFTER_COMPETENCE_START'
    | 'DAYS_BEFORE_PROCESS_DUE'
export type WorkRisk = 'ATRASADA' | 'EM_MULTA' | 'SEM_PRAZO' | 'SEM_RESPONSAVEL'
export type QueueBucket
  = | 'EM_MULTA'
    | 'ATRASADA'
    | 'VENCE_HOJE'
    | 'VENCE_EM_TRES_DIAS'
    | 'IMPEDIDA'
    | 'SEM_RESPONSAVEL'
    | 'DEMAIS_ABERTAS'
    | 'CONCLUIDAS'

export interface WorkDepartment {
  id: number
  name: string
  code: string
  color?: string | null
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export interface ProcessTemplateTask {
  id?: number
  sort_order: number
  title: string
  description?: string | null
  due_rule_type?: DueRuleType | null
  due_rule_value?: number | null
  default_department_id?: number | null
  default_assignee_membership_id?: number | null
  is_required: boolean
  is_critical: boolean
  requires_evidence: boolean
}

export interface ProcessTemplate {
  id: number
  name: string
  description?: string | null
  default_department_id?: number | null
  default_due_rule_type?: DueRuleType | null
  default_due_rule_value?: number | null
  is_active: boolean
  lock_version: number
  tasks: ProcessTemplateTask[]
  created_at?: string
  updated_at?: string
}

export interface OperationalTaskSummary {
  id: number
  title: string
  status: TaskStatus
  due_date?: string | null
  effective_due_date?: string | null
  is_critical: boolean
  is_required: boolean
  requires_evidence: boolean
  block_reason?: string | null
  lock_version: number
  bucket?: QueueBucket
  risks?: WorkRisk[]
  department?: { id: number, name: string, code: string } | null
  assignee?: { membership_id: number, name: string } | null
  process?: {
    id: number
    title: string
    competence: string
    status: ProcessStatus
    subject_to_fine?: boolean
    client?: { id: number, name: string } | null
  } | null
}

export interface OperationalProcess {
  id: number
  title: string
  description?: string | null
  competence: string
  origin: ProcessOrigin
  status: ProcessStatus
  due_date?: string | null
  target_due_date?: string | null
  subject_to_fine: boolean
  work_department_id?: number | null
  assignee_membership_id?: number | null
  client_id: number
  process_template_id?: number | null
  lock_version: number
  client?: { id: number, name: string } | null
  department?: { id: number, name: string, code: string } | null
  assignee?: { membership_id: number, name: string } | null
  task_count?: number | null
  completed_task_count?: number | null
  open_task_count?: number | null
  progress_percent?: number | null
  risks?: WorkRisk[]
  tasks?: OperationalTaskDetail[]
  comments?: OperationalComment[]
}

export interface OperationalTaskDetail extends OperationalTaskSummary {
  operational_process_id: number
  sort_order: number
  description?: string | null
  assignee_membership_id?: number | null
  work_department_id?: number | null
  started_at?: string | null
  completed_at?: string | null
  evidences?: OperationalEvidence[]
  comments?: OperationalComment[]
}

export interface OperationalEvidence {
  id: number
  original_filename: string
  mime_type: string
  byte_size: number
  sha256: string
  created_at?: string
}

export interface OperationalComment {
  id: number
  body: string
  author_membership_id?: number
  created_at?: string
}

export interface GenerationBatch {
  id: number
  process_template_id: number
  template_lock_version: number
  competence: string
  status: string
  payload_hash: string
  idempotency_key?: string
  preview_summary?: { total: number, blocked: number, ready: number }
  expires_at?: string | null
  items: GenerationItem[]
}

export interface GenerationItem {
  id: number
  client_id: number
  status: string
  is_blocked: boolean
  preview_payload?: Record<string, unknown>
  alerts?: unknown[]
  conflicts?: Array<{ code: string, message: string }>
  created_process_id?: number | null
  error_message?: string | null
}

export interface DepartmentWorkProgress {
  work_department_id: number | null
  open: number
  completed: number
  overdue: number
  fine: number
  unassigned: number
  total_relevant: number
  completed_percent: number
  /** Compat legado = open */
  total: number
}

export interface WorkKpis {
  generated_at: string
  office_timezone: string
  today: string
  kpis: {
    total_open: number
    atrasadas: number
    em_multa: number
    vence_hoje: number
    em_progresso: number
    concluidas: number
    sem_responsavel: number
  }
  by_department: DepartmentWorkProgress[]
  by_assignee: Array<{ assignee_membership_id: number | null, total: number }>
  top_risks: Array<{
    task_id: number
    title: string
    process_id: number
    risks: WorkRisk[]
    effective_due_date?: string | null
  }>
  processes_without_owner: Array<{
    id: number
    title: string
    competence: string
    due_date?: string | null
    client_id: number
  }>
}

export interface OperationalExportJob {
  id: number
  status: string
  filters_snapshot: Record<string, unknown>
  byte_size?: number | null
  row_count: number
  error_message?: string | null
  expires_at?: string | null
  completed_at?: string | null
}
