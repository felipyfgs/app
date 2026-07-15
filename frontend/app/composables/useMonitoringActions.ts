/**
 * Ações reais da carteira fiscal (8.1–8.6).
 * Conecta UI a endpoints existentes; nunca simula sucesso visual sem backend.
 */
import type { ExportFilters, FiscalCategory, FiscalMonitoringRun } from '~/types/api'
import type { FiscalModuleKey } from '~/types/fiscal-modules'
import { defaultReadCodesForModule } from '~/utils/fiscal-high-risk'
import {
  moduleSupportsEnqueueRead,
  moduleSupportsPortfolioExport
} from '~/utils/monitoring-actions'
import { apiErrorMessage } from '~/utils/api-error'

export interface PortfolioExportFilters {
  situation?: string
  competence?: string
  q?: string
  submodule?: string
  client_id?: number
}

export function useMonitoringActions(moduleKey: MaybeRefOrGetter<FiscalModuleKey | string>) {
  const api = useApi()
  const toast = useToast()
  const router = useRouter()
  const {
    me,
    canManageClients,
    canAssociateCategories,
    canTriggerSync,
    canCreateExport,
    canTriageMailbox,
    canExecuteHighRiskMutation,
    openClientCreate
  } = useDashboard()

  const module = computed(() => toValue(moduleKey))

  const enqueueing = ref(false)
  const exporting = ref(false)
  const associating = ref(false)
  const lastRun = ref<FiscalMonitoringRun | null>(null)

  async function addClient() {
    if (!canManageClients.value) {
      toast.add({ title: 'Sem permissão para cadastrar clientes.', color: 'warning' })
      return
    }
    await openClientCreate()
  }

  /**
   * POST /fiscal/category-links/batch — associa clientes a uma categoria.
   * Retorna true se a API aceitou; caller deve refresh da carteira.
   */
  async function associateCategoriesBatch(input: {
    fiscal_category_id: number
    client_ids: number[]
    coverage?: string
  }): Promise<boolean> {
    if (!canAssociateCategories.value) {
      toast.add({ title: 'Sem permissão para associar categorias.', color: 'warning' })
      return false
    }
    if (!input.fiscal_category_id || !input.client_ids.length) {
      toast.add({ title: 'Informe categoria e ao menos um cliente.', color: 'warning' })
      return false
    }
    associating.value = true
    try {
      const res = await api.fiscal.categoryLinks.associateBatch({
        fiscal_category_id: input.fiscal_category_id,
        client_ids: input.client_ids,
        coverage: input.coverage
      })
      const created = Number(res.data?.created ?? 0)
      const errors = Array.isArray(res.data?.errors) ? res.data.errors.length : 0
      toast.add({
        title: 'Associação concluída',
        description: `${created} vínculo(s)${errors ? ` · ${errors} erro(s)` : ''}`,
        color: errors ? 'warning' : 'success'
      })
      return true
    } catch (caught) {
      toast.add({
        title: apiErrorMessage(caught, 'Falha ao associar categorias.'),
        color: 'error'
      })
      return false
    } finally {
      associating.value = false
    }
  }

  async function loadCategories(): Promise<FiscalCategory[]> {
    try {
      const res = await api.fiscal.categories()
      return res.data || []
    } catch {
      return []
    }
  }

  /**
   * Enfileira atualização de leitura:
   * - sitfis → POST sitfis/refresh
   * - fgts → POST fgts/sync (exige competência)
   * - demais → POST fiscal/runs com códigos oficiais do módulo
   * Demo: backend bloqueia integração externa (DEMO_MODE) sem sucesso fictício.
   */
  async function enqueueReadUpdate(input: {
    client_id: number
    competence?: string
    force?: boolean
    system_code?: string
    service_code?: string
    operation_code?: string
  }): Promise<FiscalMonitoringRun | Record<string, unknown> | null> {
    if (!canTriggerSync.value) {
      toast.add({ title: 'Sem permissão para enfileirar consultas.', color: 'warning' })
      return null
    }
    if (!input.client_id || input.client_id < 1) {
      toast.add({ title: 'Informe o cliente para solicitar atualização.', color: 'warning' })
      return null
    }
    if (!moduleSupportsEnqueueRead(module.value)) {
      toast.add({
        title: 'Atualização de leitura não disponível neste módulo.',
        color: 'neutral'
      })
      return null
    }

    enqueueing.value = true
    try {
      const key = module.value

      if (key === 'sitfis') {
        const res = await api.fiscal.sitfis.refresh({
          client_id: input.client_id,
          force: input.force
        })
        toast.add({
          title: 'Atualização SITFIS enfileirada',
          description: 'Resultado reflete o job; em demo a integração externa é bloqueada.',
          color: 'success'
        })
        return res.data
      }

      if (key === 'fgts') {
        if (!input.competence) {
          toast.add({
            title: 'Informe a competência (AAAA-MM) para sync eSocial.',
            color: 'warning'
          })
          return null
        }
        const res = await api.fiscal.fgts.sync({
          client_id: input.client_id,
          competence_period_key: input.competence,
          dispatch_job: true
        })
        toast.add({
          title: 'Sincronização eSocial enfileirada',
          color: 'success'
        })
        return res.data as Record<string, unknown>
      }

      const defaults = defaultReadCodesForModule(key)
      const system = input.system_code || defaults?.system_code
      const service = input.service_code || defaults?.service_code
      const operation = input.operation_code || defaults?.operation_code || 'MONITOR'

      if (!system || !service) {
        toast.add({
          title: 'Códigos de serviço indisponíveis para este módulo.',
          color: 'error'
        })
        return null
      }

      const res = await api.fiscal.runs.create({
        client_id: input.client_id,
        system_code: system,
        service_code: service,
        operation_code: operation
      })
      lastRun.value = res.data
      toast.add({
        title: 'Consulta enfileirada',
        description: `Run #${res.data.id} · ${res.data.status || 'QUEUED'}`,
        color: 'success'
      })
      return res.data
    } catch (caught) {
      toast.add({
        title: apiErrorMessage(caught, 'Falha ao enfileirar atualização.'),
        color: 'error'
      })
      return null
    } finally {
      enqueueing.value = false
    }
  }

  /**
   * Export assíncrono da carteira via POST /exports (export_scope=fiscal_portfolio).
   * Campos sanitizados + proveniência + marcação demo no job.
   */
  async function exportPortfolio(filters: PortfolioExportFilters = {}): Promise<boolean> {
    if (!canCreateExport.value) {
      toast.add({ title: 'Sem permissão para exportar.', color: 'warning' })
      return false
    }
    if (!moduleSupportsPortfolioExport(module.value)) {
      toast.add({ title: 'Exportação de carteira não se aplica a este módulo.', color: 'neutral' })
      return false
    }

    exporting.value = true
    try {
      const body: { filters: ExportFilters } = {
        filters: {
          export_scope: 'fiscal_portfolio',
          module_key: String(module.value),
          situation: filters.situation && filters.situation !== 'all'
            ? filters.situation
            : undefined,
          competence: filters.competence || undefined,
          q: filters.q || undefined,
          submodule: filters.submodule || undefined,
          client_id: filters.client_id || undefined
        }
      }
      const res = await api.exports.create(body)
      toast.add({
        title: 'Exportação da carteira pedida',
        description: `Job #${res.data.id} · veja em Exportações quando READY. Dados demo são marcados.`,
        color: 'success'
      })
      await router.push('/exports')
      return true
    } catch (caught) {
      toast.add({
        title: apiErrorMessage(caught, 'Falha ao solicitar exportação da carteira.'),
        color: 'error'
      })
      return false
    } finally {
      exporting.value = false
    }
  }

  return {
    me,
    canManageClients,
    canAssociateCategories,
    canTriggerSync,
    canCreateExport,
    canTriageMailbox,
    canExecuteHighRiskMutation,
    enqueueing,
    exporting,
    associating,
    lastRun,
    addClient,
    associateCategoriesBatch,
    loadCategories,
    enqueueReadUpdate,
    exportPortfolio,
    moduleSupportsEnqueueRead: computed(() => moduleSupportsEnqueueRead(module.value)),
    moduleSupportsPortfolioExport: computed(() => moduleSupportsPortfolioExport(module.value))
  }
}
