<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { ClientCategory } from '~/types/api'
import type {
  GenerationBatch,
  ProcessAudienceRules,
  ProcessTemplate,
  ProcessTemplateCatalogItem,
  ProcessTemplateTask,
  WorkDepartment,
  WorkMonitoringModuleKey
} from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import { truncateText } from '~/utils/format'
import { canManageWorkCatalog } from '~/utils/permissions'
import {
  TABLE_CELL_BADGE_CLASS,
  TABLE_CELL_BADGE_UI
} from '~/utils/table-ui'
import {
  buildGenerationSelection,
  cloneProcessAudienceRules,
  emptyProcessAudienceRules,
  generationItemClientLabel,
  generationItemClientMeta,
  monitoringModuleLabel,
  WORK_MONITORING_MODULES,
  WORK_TAX_REGIMES
} from '~/utils/work-orchestration'
import ShellDataTable from '~/components/shell/DataTable.vue'

type ViewMode = 'library' | 'office'

interface TemplateFormState {
  id: number | null
  lockVersion: number | null
  name: string
  description: string
  defaultDepartmentId: number | null
  dueDay: number
  monitoringModuleKey: WorkMonitoringModuleKey | null
  audienceRules: ProcessAudienceRules
  isActive: boolean
  tasks: ProcessTemplateTask[]
}

const api = useApi()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { me, sessionEpoch } = useDashboard()

const view = ref<ViewMode>(String(route.query.view) === 'office' ? 'office' : 'library')
const query = ref(String(route.query.q || ''))
const page = ref(Math.max(1, Number(route.query.page) || 1))
const perPage = ref(20)
const total = ref(0)
const loading = ref(false)
const catalogLoading = ref(false)
const catalog = ref<ProcessTemplateCatalogItem[]>([])
const templates = ref<ProcessTemplate[]>([])
const departments = ref<WorkDepartment[]>([])
const categories = ref<ClientCategory[]>([])
const installingKey = ref<string | null>(null)

const editorOpen = ref(false)
const editorBusy = ref(false)
const editor = reactive<TemplateFormState>(emptyTemplateForm())

const generationOpen = ref(false)
const generationStep = ref<1 | 2 | 4>(1)
const generationTemplate = ref<ProcessTemplate | null>(null)
const generationCompetence = ref('')
const generationRules = ref<ProcessAudienceRules>(emptyProcessAudienceRules())
const generationIncludeIds = ref<number[]>([])
const generationExcludeIds = ref<number[]>([])
const generationBusy = ref(false)
const generationBatch = ref<GenerationBatch | null>(null)
const generationError = ref<string | null>(null)

const canManage = computed(() => canManageWorkCatalog(me.value))

if (!canManage.value) {
  await navigateTo('/work')
}

const columns: TableColumn<ProcessTemplate>[] = [
  { accessorKey: 'name', header: 'Modelo' },
  { accessorKey: 'audience', header: 'Público padrão' },
  { accessorKey: 'department', header: 'Departamento' },
  { accessorKey: 'is_active', header: 'Status' },
  { accessorKey: 'tasks', header: 'Tarefas' },
  { accessorKey: 'actions', header: '' }
]

const departmentItems = computed(() => [
  { label: 'Sem departamento padrão', value: null },
  ...departments.value.map(department => ({ label: department.name, value: department.id }))
])

const categoryItems = computed(() => categories.value.map(category => ({
  id: category.id,
  label: category.name
})))

const filteredCatalog = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('pt-BR')
  if (!needle) return catalog.value
  return catalog.value.filter(item => [item.name, item.description, item.department_role]
    .some(value => String(value || '').toLocaleLowerCase('pt-BR').includes(needle)))
})

const generationSteps = [
  { title: 'Configurar', description: 'Competência e público' },
  { title: 'Pré-visualizar', description: 'Empresas e alertas' },
  { title: 'Confirmar', description: 'Gerar processos' },
  { title: 'Acompanhar', description: 'Resultado' }
]

function emptyTask(sortOrder: number): ProcessTemplateTask {
  return {
    sort_order: sortOrder,
    title: '',
    due_rule_type: 'DAYS_BEFORE_PROCESS_DUE',
    due_rule_value: 0,
    default_department_id: null,
    default_assignee_membership_id: null,
    is_required: true,
    is_critical: false,
    requires_evidence: false
  }
}

function emptyTemplateForm(): TemplateFormState {
  return {
    id: null,
    lockVersion: null,
    name: '',
    description: '',
    defaultDepartmentId: null,
    dueDay: 20,
    monitoringModuleKey: null,
    audienceRules: emptyProcessAudienceRules(),
    isActive: true,
    tasks: [emptyTask(1)]
  }
}

function replaceEditor(next: TemplateFormState): void {
  Object.assign(editor, next)
}

function setView(nextView: ViewMode): void {
  view.value = nextView
}

function closeGeneration(): void {
  generationOpen.value = false
}

function openCreate(): void {
  replaceEditor(emptyTemplateForm())
  editorOpen.value = true
}

function openEdit(template: ProcessTemplate): void {
  replaceEditor({
    id: template.id,
    lockVersion: template.lock_version,
    name: template.name,
    description: template.description || '',
    defaultDepartmentId: template.default_department_id || null,
    dueDay: template.default_due_rule_value ?? 20,
    monitoringModuleKey: template.monitoring_module_key || null,
    audienceRules: cloneProcessAudienceRules(template.audience_rules),
    isActive: template.is_active,
    tasks: (template.tasks || []).map((task, index) => ({
      ...task,
      sort_order: index + 1,
      default_department_id: task.default_department_id || null
    }))
  })
  editorOpen.value = true
}

function addTask(): void {
  editor.tasks.push(emptyTask(editor.tasks.length + 1))
}

function removeTask(index: number): void {
  if (editor.tasks.length <= 1) return
  editor.tasks.splice(index, 1)
  normalizeTaskOrder()
}

function moveTask(index: number, direction: -1 | 1): void {
  const target = index + direction
  if (target < 0 || target >= editor.tasks.length) return
  const [task] = editor.tasks.splice(index, 1)
  if (!task) return
  editor.tasks.splice(target, 0, task)
  normalizeTaskOrder()
}

function normalizeTaskOrder(): void {
  editor.tasks.forEach((task, index) => {
    task.sort_order = index + 1
  })
}

function templatePayload(): Record<string, unknown> {
  normalizeTaskOrder()
  return {
    name: editor.name.trim(),
    description: editor.description.trim() || null,
    default_department_id: editor.defaultDepartmentId,
    default_due_rule_type: 'FIXED_DAY_OF_COMPETENCE',
    default_due_rule_value: Number(editor.dueDay),
    monitoring_module_key: editor.monitoringModuleKey,
    audience_rules: cloneProcessAudienceRules(editor.audienceRules),
    is_active: editor.isActive,
    tasks: editor.tasks.map(task => ({
      sort_order: task.sort_order,
      title: task.title.trim(),
      description: task.description?.trim() || null,
      due_rule_type: task.due_rule_type || 'DAYS_BEFORE_PROCESS_DUE',
      due_rule_value: Number(task.due_rule_value || 0),
      default_department_id: task.default_department_id || null,
      default_assignee_membership_id: task.default_assignee_membership_id || null,
      is_required: task.is_required,
      is_critical: task.is_critical,
      requires_evidence: task.requires_evidence
    }))
  }
}

const editorValid = computed(() => editor.name.trim().length > 0
  && editor.dueDay >= 0
  && editor.dueDay <= 31
  && editor.tasks.length > 0
  && editor.tasks.every(task => task.title.trim().length > 0))

async function saveTemplate(): Promise<void> {
  if (!editorValid.value) return
  editorBusy.value = true
  try {
    if (editor.id && editor.lockVersion) {
      await api.work.templates.update(editor.id, {
        ...templatePayload(),
        lock_version: editor.lockVersion
      })
      toast.add({ title: 'Modelo atualizado.', color: 'success' })
    } else {
      await api.work.templates.create(templatePayload())
      toast.add({ title: 'Modelo criado.', color: 'success' })
    }
    editorOpen.value = false
    view.value = 'office'
    await Promise.all([loadTemplates(), loadCatalog()])
  } catch (error) {
    toast.add({ title: apiErrorMessage(error, 'Não foi possível salvar o modelo.'), color: 'error' })
  } finally {
    editorBusy.value = false
  }
}

async function loadTemplates(): Promise<void> {
  const epoch = sessionEpoch.value
  loading.value = true
  try {
    const response = await api.work.templates.list({
      page: page.value,
      per_page: perPage.value,
      q: query.value || undefined
    })
    if (epoch !== sessionEpoch.value) return
    templates.value = response.data
    total.value = response.meta?.total ?? response.data.length
  } catch (error) {
    if (epoch !== sessionEpoch.value) return
    toast.add({ title: apiErrorMessage(error, 'Falha ao listar modelos.'), color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

async function loadCatalog(): Promise<void> {
  const epoch = sessionEpoch.value
  catalogLoading.value = true
  try {
    const response = await api.work.templates.catalog()
    if (epoch !== sessionEpoch.value) return
    catalog.value = response.data
  } catch (error) {
    if (epoch !== sessionEpoch.value) return
    toast.add({ title: apiErrorMessage(error, 'Falha ao carregar a biblioteca.'), color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) catalogLoading.value = false
  }
}

async function loadOptions(): Promise<void> {
  const [departmentsResult, categoriesResult] = await Promise.allSettled([
    api.work.departments.list({ per_page: 100, is_active: true }),
    api.clientCategories.list()
  ])
  departments.value = departmentsResult.status === 'fulfilled' ? departmentsResult.value.data : []
  categories.value = categoriesResult.status === 'fulfilled' ? categoriesResult.value.data : []
}

async function installCatalogItem(item: ProcessTemplateCatalogItem): Promise<void> {
  if (item.installed) {
    view.value = 'office'
    query.value = ''
    return
  }
  installingKey.value = item.key
  try {
    await api.work.templates.installCatalog(item.key)
    toast.add({
      title: `${item.name} adicionado aos modelos do escritório.`,
      description: 'A cópia já pode ser personalizada sem alterar a biblioteca.',
      color: 'success'
    })
    await Promise.all([loadCatalog(), loadTemplates()])
  } catch (error) {
    toast.add({ title: apiErrorMessage(error, 'Não foi possível adicionar o modelo.'), color: 'error' })
  } finally {
    installingKey.value = null
  }
}

function openGeneration(template: ProcessTemplate): void {
  generationTemplate.value = template
  generationCompetence.value = new Date().toISOString().slice(0, 7)
  generationRules.value = cloneProcessAudienceRules(template.audience_rules)
  generationIncludeIds.value = []
  generationExcludeIds.value = []
  generationBatch.value = null
  generationError.value = null
  generationStep.value = 1
  generationOpen.value = true
}

async function previewGeneration(): Promise<void> {
  if (!generationTemplate.value || !/^\d{4}-\d{2}$/.test(generationCompetence.value)) {
    toast.add({ title: 'Informe uma competência válida.', color: 'warning' })
    return
  }
  generationBusy.value = true
  generationError.value = null
  try {
    const response = await api.work.templates.preview(generationTemplate.value.id, {
      competence: generationCompetence.value,
      selection: buildGenerationSelection(
        generationRules.value,
        generationIncludeIds.value,
        generationExcludeIds.value
      )
    })
    generationBatch.value = response.data
    generationStep.value = 2
  } catch (error) {
    generationError.value = apiErrorMessage(error, 'Falha ao pré-visualizar a geração.')
    toast.add({ title: generationError.value, color: 'error' })
  } finally {
    generationBusy.value = false
  }
}

async function confirmGeneration(): Promise<void> {
  if (!generationBatch.value) return
  generationBusy.value = true
  generationError.value = null
  try {
    const response = await api.work.generation.confirm(generationBatch.value.id)
    generationBatch.value = response.data
    generationStep.value = 4
    toast.add({ title: 'Processos enviados para geração.', color: 'success' })
  } catch (error: unknown) {
    generationError.value = apiErrorMessage(error, 'Confirmação recusada.')
    const statusCode = (error as { statusCode?: number })?.statusCode
    toast.add({
      title: statusCode === 409
        ? 'O preview expirou ou o modelo foi alterado. Gere uma nova prévia.'
        : generationError.value,
      color: statusCode === 409 ? 'warning' : 'error'
    })
  } finally {
    generationBusy.value = false
  }
}

async function refreshBatch(): Promise<void> {
  if (!generationBatch.value) return
  generationBusy.value = true
  try {
    const response = await api.work.generation.get(generationBatch.value.id)
    generationBatch.value = response.data
  } catch (error) {
    toast.add({ title: apiErrorMessage(error, 'Falha ao atualizar o lote.'), color: 'error' })
  } finally {
    generationBusy.value = false
  }
}

function audienceLabel(template: ProcessTemplate): string {
  const rules = cloneProcessAudienceRules(template.audience_rules)
  const parts: string[] = []
  if (rules.tax_regimes.length) parts.push(`${rules.tax_regimes.length} regime(s)`)
  if (rules.category_ids.length) parts.push(`${rules.category_ids.length} tag(s)`)
  if (rules.excluded_category_ids.length) parts.push(`${rules.excluded_category_ids.length} exclusão(ões)`)
  return parts.join(' · ') || 'Todos os clientes ativos'
}

function departmentLabel(id?: number | null): string {
  return departments.value.find(department => department.id === id)?.name || 'Sem departamento'
}

function setPerPage(next: number): void {
  const allowed = [10, 20, 50]
  perPage.value = allowed.includes(Number(next)) ? Number(next) : 20
  if (page.value !== 1) page.value = 1
  else void loadTemplates()
}

watch([view, query, page], ([nextView]) => {
  void router.replace({
    query: {
      view: nextView === 'office' ? 'office' : undefined,
      q: query.value || undefined,
      page: nextView === 'office' && page.value > 1 ? String(page.value) : undefined
    }
  })
  if (nextView === 'office') void loadTemplates()
})

watch(sessionEpoch, () => {
  catalog.value = []
  templates.value = []
  page.value = 1
  total.value = 0
  void Promise.all([loadCatalog(), loadTemplates(), loadOptions()])
})

onMounted(() => {
  void Promise.all([loadCatalog(), loadTemplates(), loadOptions()])
})
</script>

<template>
  <ShellPagePanel id="work-templates" data-testid="work-templates-panel">
    <template #header>
      <ShellPageNavbar title="Modelos">
        <template #right>
          <UButton
            icon="i-lucide-plus"
            label="Novo modelo"
            @click="openCreate"
          />
        </template>
      </ShellPageNavbar>
    </template>

    <template #toolbar>
      <UDashboardToolbar>
        <div class="flex w-full min-w-0 flex-col gap-3 p-1 sm:flex-row sm:items-center sm:justify-between">
          <UFieldGroup>
            <UButton
              label="Biblioteca"
              icon="i-lucide-library"
              :variant="view === 'library' ? 'solid' : 'outline'"
              :color="view === 'library' ? 'primary' : 'neutral'"
              @click="setView('library')"
            />
            <UButton
              label="Meus modelos"
              icon="i-lucide-files"
              :variant="view === 'office' ? 'solid' : 'outline'"
              :color="view === 'office' ? 'primary' : 'neutral'"
              @click="setView('office')"
            />
          </UFieldGroup>
          <div class="flex min-w-0 flex-1 items-center gap-2 sm:max-w-sm">
            <UInput
              v-model="query"
              icon="i-lucide-search"
              class="min-w-0 flex-1"
              :placeholder="view === 'library' ? 'Buscar na biblioteca…' : 'Buscar meus modelos…'"
              aria-label="Buscar modelos"
            />
            <UButton
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="ghost"
              aria-label="Atualizar modelos"
              :loading="catalogLoading || loading"
              @click="view === 'library' ? loadCatalog() : loadTemplates()"
            />
          </div>
        </div>
      </UDashboardToolbar>
    </template>

    <template #body>
      <h1 class="sr-only">
        Modelos de processo
      </h1>

      <section v-if="view === 'library'" class="space-y-5" aria-labelledby="template-library-title">
        <div>
          <h2 id="template-library-title" class="text-lg font-semibold text-highlighted">
            Biblioteca de rotinas contábeis
          </h2>
          <p class="mt-1 text-sm text-muted">
            Escolha um modelo-base. O escritório recebe uma cópia própria e pode alterar tarefas, departamento e público.
          </p>
        </div>

        <div v-if="catalogLoading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <USkeleton v-for="index in 5" :key="index" class="h-64 rounded-lg" />
        </div>

        <div v-else-if="filteredCatalog.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="item in filteredCatalog"
            :key="item.key"
            class="flex min-w-0 flex-col rounded-lg border border-default bg-default p-5"
            :data-testid="`work-catalog-${item.key}`"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <h3 class="font-semibold text-highlighted">
                  {{ item.name }}
                </h3>
                <p class="mt-1 text-xs uppercase tracking-wide text-muted">
                  {{ item.department_role || 'Rotina geral' }} · v{{ item.version }}
                </p>
              </div>
              <UBadge
                :color="item.installed ? 'success' : 'neutral'"
                variant="subtle"
                :label="item.installed ? 'Adicionado' : 'Disponível'"
              />
            </div>

            <p class="mt-3 text-sm text-muted">
              {{ item.description }}
            </p>

            <div class="mt-4 flex flex-wrap gap-1.5">
              <UBadge
                v-if="item.monitoring_module_key"
                icon="i-lucide-activity"
                color="info"
                variant="subtle"
                :label="monitoringModuleLabel(item.monitoring_module_key)"
              />
              <UBadge
                color="neutral"
                variant="subtle"
                :label="`${item.tasks.length} tarefas`"
              />
              <UBadge
                v-for="regime in item.audience_rules.tax_regimes"
                :key="regime"
                color="neutral"
                variant="subtle"
                :label="WORK_TAX_REGIMES.find(option => option.value === regime)?.label || regime"
              />
            </div>

            <ol class="mt-4 flex-1 space-y-1.5 border-t border-default pt-3 text-sm">
              <li v-for="task in item.tasks.slice(0, 4)" :key="task.sort_order" class="flex gap-2 text-muted">
                <span class="tabular-nums">{{ task.sort_order }}.</span>
                <span>{{ task.title }}</span>
              </li>
              <li v-if="item.tasks.length > 4" class="text-xs text-muted">
                + {{ item.tasks.length - 4 }} tarefa(s)
              </li>
            </ol>

            <UAlert
              v-if="item.update_available"
              class="mt-4"
              color="info"
              variant="subtle"
              title="Nova versão disponível"
              description="Sua cópia não será substituída automaticamente."
            />

            <UButton
              class="mt-5"
              block
              :color="item.installed ? 'neutral' : 'primary'"
              :variant="item.installed ? 'outline' : 'solid'"
              :icon="item.installed ? 'i-lucide-pencil' : 'i-lucide-plus'"
              :label="item.installed ? 'Abrir meus modelos' : 'Adicionar ao escritório'"
              :loading="installingKey === item.key"
              @click="installCatalogItem(item)"
            />
          </article>
        </div>

        <UEmpty
          v-else
          icon="i-lucide-search-x"
          title="Nenhum modelo encontrado"
          description="Tente outro termo de busca."
        />
      </section>

      <section v-else class="space-y-4" aria-labelledby="office-templates-title">
        <div>
          <h2 id="office-templates-title" class="text-lg font-semibold text-highlighted">
            Modelos do escritório
          </h2>
          <p class="mt-1 text-sm text-muted">
            Personalize o roteiro e gere um processo para cada empresa selecionada.
          </p>
        </div>

        <ShellDataTable
          test-id="work-templates-table"
          ui-preset="monitoring-compact"
          primary-column-id="name"
          status-column-id="is_active"
          :summary-column-ids="['audience', 'department', 'tasks']"
          :columns="columns"
          :data="templates"
          :loading="loading"
          :page="page"
          :total="total"
          :items-per-page="perPage"
          per-page-aria-label="Modelos por página"
          @update:page="page = $event"
          @update:items-per-page="setPerPage"
        >
          <template #name-cell="{ row }">
            <div class="min-w-0">
              <p class="truncate font-medium text-highlighted" :title="row.original.name">
                {{ truncateText(row.original.name, 42) || row.original.name }}
              </p>
              <p v-if="row.original.monitoring_module_key" class="text-xs text-muted">
                {{ monitoringModuleLabel(row.original.monitoring_module_key) }}
              </p>
            </div>
          </template>
          <template #audience-cell="{ row }">
            <span class="text-sm">{{ audienceLabel(row.original) }}</span>
          </template>
          <template #department-cell="{ row }">
            {{ departmentLabel(row.original.default_department_id) }}
          </template>
          <template #is_active-cell="{ row }">
            <UBadge
              size="md"
              variant="subtle"
              :color="row.original.is_active ? 'success' : 'neutral'"
              :label="row.original.is_active ? 'Ativo' : 'Inativo'"
              :class="TABLE_CELL_BADGE_CLASS"
              :ui="TABLE_CELL_BADGE_UI"
            />
          </template>
          <template #tasks-cell="{ row }">
            {{ row.original.tasks?.length ?? 0 }}
          </template>
          <template #actions-cell="{ row }">
            <div class="flex justify-end gap-1">
              <UButton
                size="xs"
                color="neutral"
                variant="ghost"
                icon="i-lucide-pencil"
                aria-label="Editar modelo"
                @click="openEdit(row.original)"
              />
              <UButton
                size="xs"
                variant="soft"
                icon="i-lucide-play"
                label="Gerar"
                :disabled="!row.original.is_active"
                @click="openGeneration(row.original)"
              />
            </div>
          </template>
          <template #empty>
            <UEmpty
              icon="i-lucide-layout-template"
              title="Nenhum modelo no escritório"
              description="Adicione uma rotina da biblioteca ou crie seu próprio modelo."
            >
              <template #actions>
                <UButton label="Abrir biblioteca" @click="setView('library')" />
              </template>
            </UEmpty>
          </template>
          <template #footer>
            <span class="tabular-nums">{{ total }}</span> modelo(s)
          </template>
        </ShellDataTable>
      </section>

      <ShellFormModal
        v-model:open="editorOpen"
        :title="editor.id ? 'Editar modelo' : 'Novo modelo'"
        content-class="max-w-4xl"
        :loading="editorBusy"
        :disabled="!editorValid"
        :show-default-footer="false"
        @cancel="editorOpen = false"
        @submit="saveTemplate"
      >
        <template #body>
          <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-2">
              <UFormField label="Nome" required class="sm:col-span-2">
                <UInput v-model="editor.name" class="w-full" placeholder="Ex.: PGDAS mensal" />
              </UFormField>
              <UFormField label="Descrição" class="sm:col-span-2">
                <UTextarea
                  v-model="editor.description"
                  class="w-full"
                  autoresize
                  :maxrows="4"
                />
              </UFormField>
              <UFormField label="Departamento padrão">
                <USelect
                  v-model="editor.defaultDepartmentId"
                  :items="departmentItems"
                  value-key="value"
                  class="w-full"
                />
              </UFormField>
              <UFormField label="Dia de vencimento" description="Dia dentro da competência.">
                <UInputNumber
                  v-model="editor.dueDay"
                  :min="0"
                  :max="31"
                  class="w-full"
                />
              </UFormField>
              <UFormField label="Contexto no Monitoramento">
                <USelect
                  v-model="editor.monitoringModuleKey"
                  :items="WORK_MONITORING_MODULES"
                  value-key="value"
                  class="w-full"
                />
              </UFormField>
              <UFormField label="Modelo ativo">
                <USwitch v-model="editor.isActive" label="Disponível para novas gerações" />
              </UFormField>
            </section>

            <section class="space-y-4 rounded-lg border border-default p-4">
              <div>
                <h3 class="font-medium text-highlighted">
                  Público padrão
                </h3>
                <p class="text-sm text-muted">
                  As regras são avaliadas na competência informada. Inclusões e exclusões manuais entram somente na geração.
                </p>
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <UFormField label="Regimes tributários">
                  <USelectMenu
                    v-model="editor.audienceRules.tax_regimes"
                    :items="WORK_TAX_REGIMES"
                    value-key="value"
                    multiple
                    clear
                    class="w-full"
                    placeholder="Todos os regimes"
                  />
                </UFormField>
                <UFormField label="Combinação das tags">
                  <URadioGroup
                    v-model="editor.audienceRules.category_match"
                    orientation="horizontal"
                    :items="[
                      { label: 'Qualquer tag', value: 'ANY' },
                      { label: 'Todas as tags', value: 'ALL' }
                    ]"
                  />
                </UFormField>
                <UFormField label="Tags que incluem">
                  <USelectMenu
                    v-model="editor.audienceRules.category_ids"
                    :items="categoryItems"
                    value-key="id"
                    label-key="label"
                    multiple
                    clear
                    class="w-full"
                    placeholder="Nenhuma tag obrigatória"
                  />
                </UFormField>
                <UFormField label="Tags que excluem">
                  <USelectMenu
                    v-model="editor.audienceRules.excluded_category_ids"
                    :items="categoryItems"
                    value-key="id"
                    label-key="label"
                    multiple
                    clear
                    class="w-full"
                    placeholder="Nenhuma tag de exclusão"
                  />
                </UFormField>
              </div>
            </section>

            <section class="space-y-3">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h3 class="font-medium text-highlighted">
                    Tarefas do processo
                  </h3>
                  <p class="text-sm text-muted">
                    A ordem será preservada em cada empresa.
                  </p>
                </div>
                <UButton
                  size="sm"
                  variant="soft"
                  icon="i-lucide-plus"
                  label="Tarefa"
                  @click="addTask"
                />
              </div>

              <div
                v-for="(task, index) in editor.tasks"
                :key="`${task.id || 'new'}-${index}`"
                class="grid gap-3 rounded-lg border border-default p-3 lg:grid-cols-12"
              >
                <div class="flex items-center gap-1 lg:col-span-1">
                  <span class="w-6 text-center text-sm tabular-nums text-muted">{{ index + 1 }}</span>
                  <div class="flex lg:flex-col">
                    <UButton
                      size="xs"
                      icon="i-lucide-chevron-up"
                      color="neutral"
                      variant="ghost"
                      aria-label="Mover para cima"
                      :disabled="index === 0"
                      @click="moveTask(index, -1)"
                    />
                    <UButton
                      size="xs"
                      icon="i-lucide-chevron-down"
                      color="neutral"
                      variant="ghost"
                      aria-label="Mover para baixo"
                      :disabled="index === editor.tasks.length - 1"
                      @click="moveTask(index, 1)"
                    />
                  </div>
                </div>
                <UFormField label="Tarefa" required class="lg:col-span-4">
                  <UInput v-model="task.title" class="w-full" placeholder="Título da tarefa" />
                </UFormField>
                <UFormField label="Dias antes" class="lg:col-span-2">
                  <UInputNumber
                    v-model="task.due_rule_value"
                    :min="0"
                    :max="366"
                    class="w-full"
                  />
                </UFormField>
                <UFormField label="Departamento" class="lg:col-span-3">
                  <USelect
                    v-model="task.default_department_id"
                    :items="departmentItems"
                    value-key="value"
                    class="w-full"
                  />
                </UFormField>
                <div class="flex flex-wrap items-end gap-3 lg:col-span-2">
                  <UCheckbox v-model="task.is_critical" label="Crítica" />
                  <UCheckbox v-model="task.requires_evidence" label="Evidência" />
                  <UButton
                    icon="i-lucide-trash"
                    color="error"
                    variant="ghost"
                    size="xs"
                    aria-label="Remover tarefa"
                    :disabled="editor.tasks.length === 1"
                    @click="removeTask(index)"
                  />
                </div>
              </div>
            </section>
          </div>
        </template>
        <template #footer>
          <ShellModalFooter
            :submit-label="editor.id ? 'Salvar alterações' : 'Criar modelo'"
            :loading="editorBusy"
            :disabled="!editorValid"
            @cancel="editorOpen = false"
            @submit="saveTemplate"
          />
        </template>
      </ShellFormModal>

      <ShellFormModal
        v-model:open="generationOpen"
        :title="`Gerar processos — ${generationTemplate?.name || ''}`"
        content-class="max-w-3xl"
        :show-default-footer="false"
        @cancel="closeGeneration"
      >
        <template #body>
          <UStepper
            :model-value="generationStep === 4 ? 3 : generationStep - 1"
            :items="generationSteps"
            class="mb-6 w-full"
            disabled
          />

          <div v-if="generationStep === 1" class="space-y-5">
            <UFormField label="Competência" required description="O regime tributário será avaliado nesta competência.">
              <UInput
                v-model="generationCompetence"
                type="month"
                class="w-full"
                data-testid="work-gen-competence"
              />
            </UFormField>

            <section class="space-y-4 rounded-lg border border-default p-4">
              <div>
                <h3 class="font-medium text-highlighted">
                  Seleção automática
                </h3>
                <p class="text-sm text-muted">
                  Começa pelas regras do modelo; você pode ajustá-las somente para este lote.
                </p>
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <UFormField label="Regimes tributários">
                  <USelectMenu
                    v-model="generationRules.tax_regimes"
                    :items="WORK_TAX_REGIMES"
                    value-key="value"
                    multiple
                    clear
                    class="w-full"
                    placeholder="Todos os regimes"
                  />
                </UFormField>
                <UFormField label="Combinação das tags">
                  <URadioGroup
                    v-model="generationRules.category_match"
                    orientation="horizontal"
                    :items="[
                      { label: 'Qualquer', value: 'ANY' },
                      { label: 'Todas', value: 'ALL' }
                    ]"
                  />
                </UFormField>
                <UFormField label="Tags que incluem">
                  <USelectMenu
                    v-model="generationRules.category_ids"
                    :items="categoryItems"
                    value-key="id"
                    label-key="label"
                    multiple
                    clear
                    class="w-full"
                    placeholder="Sem filtro de tags"
                  />
                </UFormField>
                <UFormField label="Tags que excluem">
                  <USelectMenu
                    v-model="generationRules.excluded_category_ids"
                    :items="categoryItems"
                    value-key="id"
                    label-key="label"
                    multiple
                    clear
                    class="w-full"
                    placeholder="Sem exclusão por tag"
                  />
                </UFormField>
              </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2">
              <UFormField label="Incluir empresas manualmente" description="Entram mesmo que não atendam aos filtros.">
                <FiscalClientPicker
                  v-model="generationIncludeIds"
                  multiple
                  placeholder="Buscar empresas para incluir…"
                />
              </UFormField>
              <UFormField label="Excluir empresas manualmente" description="A exclusão sempre prevalece sobre a inclusão.">
                <FiscalClientPicker
                  v-model="generationExcludeIds"
                  multiple
                  placeholder="Buscar empresas para excluir…"
                />
              </UFormField>
            </section>

            <UAlert
              color="info"
              variant="subtle"
              icon="i-lucide-shield-check"
              title="A prévia não cria processos"
              description="Você verá exatamente quais empresas entram, o regime utilizado, alertas e conflitos antes de confirmar."
            />
          </div>

          <div v-else-if="generationStep === 2 && generationBatch" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
              <UPageCard
                title="Selecionadas"
                :description="String(generationBatch.preview_summary?.total ?? 0)"
                icon="i-lucide-building-2"
                variant="subtle"
              />
              <UPageCard
                title="Prontas"
                :description="String(generationBatch.preview_summary?.ready ?? 0)"
                icon="i-lucide-circle-check"
                variant="subtle"
              />
              <UPageCard
                title="Bloqueadas"
                :description="String(generationBatch.preview_summary?.blocked ?? 0)"
                icon="i-lucide-circle-alert"
                variant="subtle"
              />
            </div>

            <p class="text-sm text-muted">
              {{ generationBatch.preview_summary?.matched_by_rule ?? 0 }} por regra ·
              {{ generationBatch.preview_summary?.included_manually ?? 0 }} incluída(s) manualmente ·
              {{ generationBatch.preview_summary?.excluded_manually ?? 0 }} excluída(s)
            </p>

            <div class="max-h-80 space-y-2 overflow-y-auto pe-1">
              <article
                v-for="item in generationBatch.items"
                :key="item.id"
                class="rounded-lg border border-default p-3"
              >
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-highlighted">
                      {{ generationItemClientLabel(item) }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ generationItemClientMeta(item) }}
                    </p>
                  </div>
                  <UBadge
                    :color="item.is_blocked ? 'warning' : 'success'"
                    variant="subtle"
                    :label="item.is_blocked ? 'Bloqueada' : 'Pronta'"
                  />
                </div>
                <div v-if="item.preview_payload?.selection?.categories?.length" class="mt-2 flex flex-wrap gap-1">
                  <UBadge
                    v-for="category in item.preview_payload.selection.categories"
                    :key="category.id"
                    color="neutral"
                    variant="subtle"
                    :label="category.name"
                  />
                </div>
                <ul v-if="item.alerts?.length || item.conflicts?.length" class="mt-2 space-y-1 text-xs text-warning">
                  <li v-for="(alert, index) in item.alerts" :key="`alert-${index}`">
                    {{ alert.message || alert.code }}
                  </li>
                  <li v-for="conflict in item.conflicts" :key="conflict.code">
                    {{ conflict.message }}
                  </li>
                </ul>
              </article>
            </div>

            <UAlert
              v-if="generationError"
              color="error"
              :title="generationError"
            />
          </div>

          <div v-else-if="generationStep === 4 && generationBatch" class="space-y-4">
            <UAlert
              color="success"
              icon="i-lucide-circle-check"
              title="Lote confirmado"
              :description="`Status atual: ${generationBatch.status}.`"
            />
            <ul class="max-h-80 divide-y divide-default overflow-y-auto rounded-lg border border-default">
              <li v-for="item in generationBatch.items" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                <span>{{ generationItemClientLabel(item) }}</span>
                <UButton
                  v-if="item.created_process_id"
                  :to="`/work/processes/${item.created_process_id}`"
                  size="xs"
                  variant="soft"
                  icon="i-lucide-arrow-up-right"
                  label="Abrir processo"
                />
                <span v-else-if="item.error_message" class="text-error">{{ item.error_message }}</span>
                <UBadge
                  v-else
                  color="neutral"
                  variant="subtle"
                  :label="item.status"
                />
              </li>
            </ul>
            <div class="flex flex-wrap gap-2">
              <UButton
                size="sm"
                variant="soft"
                icon="i-lucide-refresh-cw"
                label="Atualizar status"
                :loading="generationBusy"
                @click="refreshBatch"
              />
              <UButton
                size="sm"
                color="neutral"
                variant="outline"
                icon="i-lucide-folder-kanban"
                label="Ver processos"
                to="/work/processes"
              />
            </div>
          </div>
        </template>

        <template #footer>
          <ShellModalFooter :show-submit="false">
            <UButton
              color="neutral"
              variant="ghost"
              label="Fechar"
              @click="closeGeneration"
            />
            <UButton
              v-if="generationStep === 1"
              data-testid="work-gen-preview"
              :loading="generationBusy"
              label="Pré-visualizar empresas"
              @click="previewGeneration"
            />
            <UButton
              v-if="generationStep === 2"
              data-testid="work-gen-confirm"
              :loading="generationBusy"
              :disabled="(generationBatch?.preview_summary?.ready ?? 0) < 1"
              label="Confirmar geração"
              @click="confirmGeneration"
            />
          </ShellModalFooter>
        </template>
      </ShellFormModal>
    </template>
  </ShellPagePanel>
</template>
