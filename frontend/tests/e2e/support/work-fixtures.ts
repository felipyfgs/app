import type { Route } from '@playwright/test'
import type { OfficeRole } from '../../../app/types/api'

const FIXED_NOW = '2026-07-14T15:00:00.000Z'

const pageMeta = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function departments(officeId: number) {
  return [{
    id: officeId * 10 + 1,
    name: 'Fiscal',
    code: 'FIS',
    color: '#2563eb',
    is_active: true,
    created_at: FIXED_NOW,
    updated_at: FIXED_NOW
  }]
}

function templates(officeId: number) {
  return [{
    id: officeId * 100 + 1,
    name: 'DAS mensal',
    description: 'Modelo fixture',
    default_department_id: officeId * 10 + 1,
    default_due_rule_type: 'FIXED_DAY_OF_COMPETENCE',
    default_due_rule_value: 20,
    is_active: true,
    lock_version: 1,
    tasks: [
      {
        id: 1,
        sort_order: 1,
        title: 'Apurar',
        is_required: true,
        is_critical: true,
        requires_evidence: true,
        due_rule_type: 'DAYS_BEFORE_PROCESS_DUE',
        due_rule_value: 5
      },
      {
        id: 2,
        sort_order: 2,
        title: 'Transmitir',
        is_required: true,
        is_critical: false,
        requires_evidence: false,
        due_rule_type: 'DAYS_BEFORE_PROCESS_DUE',
        due_rule_value: 0
      }
    ],
    created_at: FIXED_NOW,
    updated_at: FIXED_NOW
  }]
}

function queueItems(officeId: number, role: OfficeRole) {
  const assignee = role === 'VIEWER' ? null : { membership_id: 2, name: 'Olívia Operadora' }
  return [{
    id: officeId * 1000 + 1,
    title: 'Apurar DAS',
    status: 'A_FAZER',
    due_date: '2026-07-10',
    effective_due_date: '2026-07-10',
    is_critical: true,
    is_required: true,
    requires_evidence: true,
    block_reason: null,
    lock_version: 1,
    bucket: 'ATRASADA',
    risks: ['ATRASADA', 'EM_MULTA'],
    department: { id: officeId * 10 + 1, name: 'Fiscal', code: 'FIS' },
    assignee,
    process: {
      id: officeId * 100 + 5,
      title: 'DAS 2026-06',
      competence: '2026-06',
      status: 'EM_PROGRESSO',
      subject_to_fine: true,
      client: {
        id: officeId,
        name: officeId === 1 ? 'Cliente Demonstração Segura' : 'Cliente Tenant Sentinela'
      }
    }
  }]
}

function taskDetail(officeId: number, taskId: number, role: OfficeRole) {
  const base = queueItems(officeId, role)[0]
  return {
    ...base,
    id: taskId,
    operational_process_id: officeId * 100 + 5,
    sort_order: 1,
    description: 'Fixture de tarefa operacional',
    assignee_membership_id: role === 'VIEWER' ? null : 2,
    work_department_id: officeId * 10 + 1,
    evidences: [],
    comments: []
  }
}

function processes(officeId: number) {
  return [{
    id: officeId * 100 + 5,
    title: 'DAS 2026-06',
    description: null,
    competence: '2026-06',
    origin: 'MANUAL',
    status: 'EM_PROGRESSO',
    due_date: '2026-07-20',
    target_due_date: null,
    subject_to_fine: true,
    work_department_id: officeId * 10 + 1,
    assignee_membership_id: 2,
    client_id: officeId,
    process_template_id: null,
    lock_version: 1,
    client: {
      id: officeId,
      name: officeId === 1 ? 'Cliente Demonstração Segura' : 'Cliente Tenant Sentinela'
    },
    department: { id: officeId * 10 + 1, name: 'Fiscal', code: 'FIS' },
    assignee: { membership_id: 2, name: 'Olívia Operadora' },
    task_count: 2,
    completed_task_count: 0,
    open_task_count: 2,
    progress_percent: 0,
    risks: ['ATRASADA', 'EM_MULTA'] as string[],
    comments: [{
      id: 1,
      body: 'Comentário fixture de processo',
      author_membership_id: 2,
      created_at: FIXED_NOW
    }],
    tasks: [
      {
        id: officeId * 1000 + 1,
        sort_order: 1,
        title: 'Apurar DAS',
        status: 'A_FAZER',
        due_date: '2026-07-10',
        is_required: true,
        is_critical: true,
        requires_evidence: true,
        lock_version: 1,
        risks: ['ATRASADA', 'EM_MULTA'],
        assignee: { membership_id: 2, name: 'Olívia Operadora' },
        evidence_count: 0
      },
      {
        id: officeId * 1000 + 2,
        sort_order: 2,
        title: 'Transmitir',
        status: 'A_FAZER',
        due_date: '2026-07-20',
        is_required: true,
        is_critical: false,
        requires_evidence: false,
        lock_version: 1,
        risks: [],
        evidence_count: 0
      }
    ]
  }]
}

function kpis(officeId: number) {
  return {
    generated_at: FIXED_NOW,
    office_timezone: 'America/Sao_Paulo',
    today: '2026-07-14',
    kpis: {
      total_open: 3,
      atrasadas: 1,
      em_multa: 1,
      vence_hoje: 0,
      em_progresso: 1,
      concluidas: 2,
      sem_responsavel: officeId === 1 ? 1 : 0
    },
    by_department: [{
      work_department_id: officeId * 10 + 1,
      open: 3,
      completed: 2,
      overdue: 1,
      fine: 1,
      unassigned: officeId === 1 ? 1 : 0,
      total_relevant: 5,
      completed_percent: 40,
      total: 3
    }],
    by_assignee: [{ assignee_membership_id: 2, total: 2 }],
    top_risks: [{
      task_id: officeId * 1000 + 1,
      title: 'Apurar DAS',
      process_id: officeId * 100 + 5,
      risks: ['ATRASADA', 'EM_MULTA'],
      effective_due_date: '2026-07-10'
    }],
    processes_without_owner: [],
    filters_effective: { office_id: officeId }
  }
}

async function fulfill(route: Route, body: unknown, status = 200) {
  await route.fulfill({
    status,
    contentType: 'application/json; charset=utf-8',
    body: JSON.stringify(body)
  })
}

/**
 * Handlers /api/v1/work/* — isolamento por activeOfficeId da fixture.
 * Retorna true se a rota foi atendida.
 */
export async function tryFulfillWorkApi(
  route: Route,
  pathname: string,
  method: string,
  role: OfficeRole,
  officeId: number,
  listScenario: 'ready' | 'empty' | 'error' | 'slow' = 'ready'
): Promise<boolean> {
  if (!pathname.includes('/api/v1/work')) {
    return false
  }

  if (listScenario === 'error' && method === 'GET') {
    await fulfill(route, { message: 'Falha sintética sanitizada (work).' }, 503)
    return true
  }

  const empty = listScenario === 'empty'

  // Departments
  if (pathname.endsWith('/api/v1/work/departments') && method === 'GET') {
    await fulfill(route, {
      data: empty ? [] : departments(officeId),
      meta: { ...pageMeta, total: empty ? 0 : 1 }
    })
    return true
  }
  if (pathname.endsWith('/api/v1/work/departments') && method === 'POST') {
    if (role !== 'ADMIN') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    const body = route.request().postDataJSON() as { name?: string, code?: string }
    await fulfill(route, {
      data: {
        id: 99,
        name: body.name || 'Novo',
        code: (body.code || 'NOV').toUpperCase(),
        color: null,
        is_active: true
      }
    }, 201)
    return true
  }
  if (/\/api\/v1\/work\/departments\/\d+$/.test(pathname) && method === 'PATCH') {
    if (role !== 'ADMIN') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, { data: departments(officeId)[0] })
    return true
  }
  if (/\/api\/v1\/work\/departments\/\d+\/assign-membership$/.test(pathname) && method === 'POST') {
    if (role !== 'ADMIN') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, { data: { membership_id: 2, work_department_id: officeId * 10 + 1 } })
    return true
  }

  // Templates
  if (pathname.endsWith('/api/v1/work/templates') && method === 'GET') {
    await fulfill(route, {
      data: empty ? [] : templates(officeId),
      meta: { ...pageMeta, total: empty ? 0 : 1 }
    })
    return true
  }
  if (pathname.endsWith('/api/v1/work/templates') && method === 'POST') {
    if (role !== 'ADMIN') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, { data: templates(officeId)[0] }, 201)
    return true
  }
  if (/\/api\/v1\/work\/templates\/\d+$/.test(pathname) && method === 'GET') {
    await fulfill(route, { data: templates(officeId)[0] })
    return true
  }
  if (/\/api\/v1\/work\/templates\/\d+\/preview$/.test(pathname) && method === 'POST') {
    if (role === 'VIEWER') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, {
      data: {
        id: 501,
        process_template_id: officeId * 100 + 1,
        template_lock_version: 1,
        competence: '2026-06',
        status: 'PREVIEWED',
        payload_hash: 'abc',
        idempotency_key: 'fix-key',
        preview_summary: { total: 1, blocked: 0, ready: 1 },
        expires_at: FIXED_NOW,
        items: [{
          id: 1,
          client_id: officeId,
          status: 'PREVIEWED',
          is_blocked: false,
          preview_payload: { title: 'DAS 2026-06', tasks: [] },
          alerts: [],
          conflicts: []
        }]
      }
    }, 201)
    return true
  }
  if (/\/api\/v1\/work\/generation-batches\/\d+\/confirm$/.test(pathname) && method === 'POST') {
    if (role === 'VIEWER') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, {
      data: {
        id: 501,
        process_template_id: officeId * 100 + 1,
        template_lock_version: 1,
        competence: '2026-06',
        status: 'COMPLETED',
        payload_hash: 'abc',
        preview_summary: { total: 1, blocked: 0, ready: 1 },
        items: [{
          id: 1,
          client_id: officeId,
          status: 'CREATED',
          is_blocked: false,
          created_process_id: officeId * 100 + 5
        }]
      }
    })
    return true
  }
  if (/\/api\/v1\/work\/generation-batches\/\d+$/.test(pathname) && method === 'GET') {
    await fulfill(route, {
      data: {
        id: 501,
        status: 'COMPLETED',
        competence: '2026-06',
        items: []
      }
    })
    return true
  }

  // Queue / tasks
  if (pathname.endsWith('/api/v1/work/queue') && method === 'GET') {
    await fulfill(route, {
      data: empty ? [] : queueItems(officeId, role),
      meta: { ...pageMeta, total: empty ? 0 : 1 }
    })
    return true
  }
  if (/\/api\/v1\/work\/tasks\/\d+$/.test(pathname) && method === 'GET') {
    const id = Number(pathname.split('/').pop())
    await fulfill(route, { data: taskDetail(officeId, id, role) })
    return true
  }
  if (/\/api\/v1\/work\/tasks\/\d+\/(start|complete|resume|claim|block|dispense|reopen)$/.test(pathname) && method === 'POST') {
    if (role === 'VIEWER') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    const id = Number(pathname.split('/')[5])
    const action = pathname.split('/').pop()!
    const statusMap: Record<string, string> = {
      start: 'EM_PROGRESSO',
      complete: 'CONCLUIDA',
      resume: 'EM_PROGRESSO',
      claim: 'A_FAZER',
      block: 'IMPEDIDA',
      dispense: 'DISPENSADA',
      reopen: 'A_FAZER'
    }
    const detail = taskDetail(officeId, id, role)
    detail.status = statusMap[action] || detail.status
    detail.lock_version = 2
    if (action === 'claim') {
      detail.assignee_membership_id = 2
      detail.assignee = { membership_id: 2, name: 'Olívia Operadora' }
    }
    await fulfill(route, { data: detail })
    return true
  }
  if (/\/api\/v1\/work\/tasks\/\d+\/comments$/.test(pathname) && method === 'POST') {
    if (role === 'VIEWER') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, {
      data: { id: 1, body: 'ok', created_at: FIXED_NOW }
    }, 201)
    return true
  }
  if (/\/api\/v1\/work\/tasks\/\d+\/evidences$/.test(pathname) && method === 'POST') {
    if (role === 'VIEWER') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, {
      data: {
        id: 1,
        original_filename: 'comp.pdf',
        mime_type: 'application/pdf',
        byte_size: 100,
        sha256: 'a'.repeat(64),
        created_at: FIXED_NOW
      }
    }, 201)
    return true
  }

  // Processes
  if (pathname.endsWith('/api/v1/work/processes') && method === 'GET') {
    await fulfill(route, {
      data: empty ? [] : processes(officeId),
      meta: { ...pageMeta, total: empty ? 0 : 1 }
    })
    return true
  }
  if (pathname.endsWith('/api/v1/work/processes') && method === 'POST') {
    if (role === 'VIEWER') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, { data: processes(officeId)[0] }, 201)
    return true
  }
  if (/\/api\/v1\/work\/processes\/\d+$/.test(pathname) && method === 'GET') {
    const id = Number(pathname.split('/').pop())
    // Cross-tenant: IDs do office 2 não existem no office 1
    if (officeId === 1 && id >= 200) {
      await fulfill(route, { message: 'Not found' }, 404)
      return true
    }
    if (officeId === 2 && id < 200) {
      await fulfill(route, { message: 'Not found' }, 404)
      return true
    }
    await fulfill(route, { data: processes(officeId)[0] })
    return true
  }
  if (/\/api\/v1\/work\/processes\/\d+\/timeline$/.test(pathname) && method === 'GET') {
    await fulfill(route, { data: [] })
    return true
  }

  // KPIs / calendar / export
  if (pathname.endsWith('/api/v1/work/kpis') && method === 'GET') {
    await fulfill(route, { data: kpis(officeId) })
    return true
  }
  if (pathname.endsWith('/api/v1/work/calendar') && method === 'GET') {
    const items = empty ? [] : queueItems(officeId, role)
    await fulfill(route, {
      data: {
        office_timezone: 'America/Sao_Paulo',
        today: '2026-07-14',
        from: '2026-07-01',
        to: '2026-07-31',
        days: empty
          ? []
          : [
              {
                date: '2026-07-10',
                total: 2,
                overdue: 1,
                fine: 1,
                completed: 0,
                open: 2,
                max_severity: 3,
                items
              },
              {
                date: '2026-07-14',
                total: 1,
                overdue: 0,
                fine: 0,
                completed: 0,
                open: 1,
                max_severity: 1,
                items: items.map(i => ({ ...i, due_date: '2026-07-14', effective_due_date: '2026-07-14' }))
              },
              {
                date: '2026-07-20',
                total: 1,
                overdue: 0,
                fine: 0,
                completed: 0,
                open: 1,
                max_severity: 1,
                items
              }
            ]
      }
    })
    return true
  }
  if (pathname.endsWith('/api/v1/work/calendar/day') && method === 'GET') {
    await fulfill(route, {
      data: empty ? [] : queueItems(officeId, role),
      meta: pageMeta
    })
    return true
  }
  if (pathname.endsWith('/api/v1/work/exports') && method === 'POST') {
    if (role === 'VIEWER') {
      await fulfill(route, { message: 'Forbidden' }, 403)
      return true
    }
    await fulfill(route, {
      data: {
        id: 1,
        status: 'READY',
        filters_snapshot: {},
        byte_size: 120,
        row_count: 3,
        error_message: null,
        expires_at: FIXED_NOW,
        completed_at: FIXED_NOW
      }
    }, 201)
    return true
  }
  if (/\/api\/v1\/work\/exports\/\d+$/.test(pathname) && method === 'GET') {
    await fulfill(route, {
      data: {
        id: 1,
        status: 'READY',
        filters_snapshot: {},
        byte_size: 120,
        row_count: 3
      }
    })
    return true
  }

  await fulfill(route, { message: 'Work endpoint fixture não mapeado.' }, 404)
  return true
}
