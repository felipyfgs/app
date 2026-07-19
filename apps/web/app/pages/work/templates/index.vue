<script setup lang="ts">
/**
 * Modelos de processo — lista customers + modal AddModal + stepper de geração.
 */
import type { TableColumn } from '@nuxt/ui'
import type { GenerationBatch, ProcessTemplate, ProcessTemplateTask } from '~/types/work'
import { canManageWorkCatalog } from '~/utils/permissions'
import { apiErrorMessage } from '~/utils/api-error'
import {
  TABLE_CELL_BADGE_CLASS,
  TABLE_CELL_BADGE_UI
} from '~/utils/table-ui'
import ShellDataTable from '~/components/shell/DataTable.vue'
import ShellListFilterToolbar from '~/components/shell/ListFilterToolbar.vue'
import { truncateText } from '~/utils/format'

const api = useApi()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { me, sessionEpoch } = useDashboard()

const items = ref<ProcessTemplate[]>([])
const loading = ref(false)
const q = ref(String(route.query.q || ''))
const page = ref(Math.max(1, Number(route.query.page) || 1))
const perPage = ref(20)
const total = ref(0)

const createOpen = ref(false)
const creating = ref(false)
const formName = ref('')
const formTasks = ref<ProcessTemplateTask[]>([
  { sort_order: 1, title: 'Preparar', is_required: true, is_critical: false, requires_evidence: false, due_rule_type: 'DAYS_BEFORE_PROCESS_DUE', due_rule_value: 5 },
  { sort_order: 2, title: 'Entregar', is_required: true, is_critical: true, requires_evidence: true, due_rule_type: 'DAYS_BEFORE_PROCESS_DUE', due_rule_value: 0 }
])

const genOpen = ref(false)
const genStep = ref(0)
const genTemplate = ref<ProcessTemplate | null>(null)
const genCompetence = ref('')
const genClientIds = ref('')
const genBusy = ref(false)
const genBatch = ref<GenerationBatch | null>(null)
const genError = ref<string | null>(null)

const canManage = computed(() => canManageWorkCatalog(me.value))

if (!canManage.value) {
  await navigateTo('/work')
}

const columns: TableColumn<ProcessTemplate>[] = [
  { accessorKey: 'name', header: 'Modelo' },
  { accessorKey: 'is_active', header: 'Status' },
  { accessorKey: 'tasks', header: 'Tarefas' },
  { accessorKey: 'actions', header: '' }
]

const steps = [
  { title: 'Selecionar', description: 'Modelo' },
  { title: 'Configurar', description: 'Clientes e competência' },
  { title: 'Pré-visualizar', description: 'Alertas e bloqueios' },
  { title: 'Confirmar', description: 'Gerar processos' },
  { title: 'Acompanhar', description: 'Resultado do batch' }
]

async function load() {
  const epoch = sessionEpoch.value
  loading.value = true
  try {
    const res = await api.work.templates.list({
      per_page: perPage.value,
      page: page.value,
      q: q.value || undefined
    })
    if (epoch !== sessionEpoch.value) return
    items.value = res.data
    total.value = res.meta?.total ?? res.data.length
  } catch (e) {
    if (epoch !== sessionEpoch.value) return
    toast.add({ title: apiErrorMessage(e, 'Falha ao listar modelos.'), color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

async function createTemplate() {
  if (!formName.value.trim()) return
  creating.value = true
  try {
    await api.work.templates.create({
      name: formName.value.trim(),
      default_due_rule_type: 'FIXED_DAY_OF_COMPETENCE',
      default_due_rule_value: 20,
      tasks: formTasks.value.map((t, i) => ({
        ...t,
        sort_order: i + 1,
        title: t.title.trim()
      }))
    })
    createOpen.value = false
    formName.value = ''
    toast.add({ title: 'Modelo criado.', color: 'success' })
    await load()
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Não foi possível criar o modelo.'), color: 'error' })
  } finally {
    creating.value = false
  }
}

function openGeneration(t: ProcessTemplate) {
  genTemplate.value = t
  genCompetence.value = new Date().toISOString().slice(0, 7)
  genClientIds.value = ''
  genBatch.value = null
  genError.value = null
  genStep.value = 1
  genOpen.value = true
}

async function runPreview() {
  if (!genTemplate.value) return
  const ids = genClientIds.value
    .split(/[,\s]+/)
    .map(s => Number(s.trim()))
    .filter(n => Number.isFinite(n) && n > 0)
  if (!ids.length || !/^\d{4}-\d{2}$/.test(genCompetence.value)) {
    toast.add({ title: 'Informe competência YYYY-MM e ao menos um client_id.', color: 'warning' })
    return
  }
  genBusy.value = true
  genError.value = null
  try {
    const res = await api.work.templates.preview(genTemplate.value.id, {
      competence: genCompetence.value,
      client_ids: ids
    })
    genBatch.value = res.data
    genStep.value = 2
  } catch (e) {
    genError.value = apiErrorMessage(e, 'Falha no preview.')
    toast.add({ title: genError.value, color: 'error' })
  } finally {
    genBusy.value = false
  }
}

async function runConfirm() {
  if (!genBatch.value) return
  genBusy.value = true
  genError.value = null
  try {
    const res = await api.work.generation.confirm(genBatch.value.id)
    genBatch.value = res.data
    genStep.value = 4
    toast.add({ title: 'Geração confirmada.', color: 'success' })
  } catch (e: unknown) {
    const status = (e as { statusCode?: number })?.statusCode
    genError.value = apiErrorMessage(e, 'Confirmação recusada.')
    if (status === 409) {
      toast.add({ title: 'Preview expirado ou alterado (409). Ajuste e gere novamente.', color: 'warning' })
    } else {
      toast.add({ title: genError.value, color: 'error' })
    }
  } finally {
    genBusy.value = false
  }
}

async function refreshBatch() {
  if (!genBatch.value) return
  genBusy.value = true
  try {
    const res = await api.work.generation.get(genBatch.value.id)
    genBatch.value = res.data
  } catch (e) {
    toast.add({ title: apiErrorMessage(e, 'Falha ao consultar batch.'), color: 'error' })
  } finally {
    genBusy.value = false
  }
}

function addTaskRow() {
  formTasks.value.push({
    sort_order: formTasks.value.length + 1,
    title: '',
    is_required: true,
    is_critical: false,
    requires_evidence: false,
    due_rule_type: 'DAYS_BEFORE_PROCESS_DUE',
    due_rule_value: 0
  })
}

function removeTaskRow(idx: number) {
  if (formTasks.value.length <= 1) return
  formTasks.value.splice(idx, 1)
}

function setPerPage(next: number) {
  const allowed = [10, 20, 50]
  const target = allowed.includes(Number(next)) ? Number(next) : 20
  if (perPage.value === target) return
  perPage.value = target
  if (page.value !== 1) {
    page.value = 1
    return
  }
  void load()
}

watch([page, q], () => {
  router.replace({
    query: {
      page: page.value > 1 ? String(page.value) : undefined,
      q: q.value || undefined
    }
  })
  void load()
})

watch(sessionEpoch, () => {
  items.value = []
  void load()
})

onMounted(load)
</script>

<template>
  <!--
    Arquétipo lista admin (customers.vue) via ShellPagePanel.
    Fontes: .local/reference/.../customers.vue + clients + table-ui.
  -->
  <ShellPagePanel
    id="work-templates"
    data-testid="work-templates-panel"
  >
    <template #header>
      <ShellPageNavbar title="Modelos de processo">
        <template #right>
          <UButton
            icon="i-lucide-plus"
            label="Novo modelo"
            @click="() => { createOpen = true }"
          />
        </template>
      </ShellPageNavbar>
    </template>

    <template #toolbar>
      <UDashboardToolbar>
        <ShellListFilterToolbar
          :q="q"
          search-placeholder="Buscar modelos…"
          search-aria-label="Buscar modelos"
          :loading="loading"
          test-id-prefix="work-templates-filter"
          @update:q="q = $event"
          @refresh="load"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <h1 class="sr-only">
        Modelos de processo
      </h1>

      <ShellDataTable
        test-id="work-templates-table"
        ui-preset="monitoring-compact"
        primary-column-id="name"
        status-column-id="is_active"
        :summary-column-ids="['tasks']"
        :columns="columns"
        :data="items"
        :loading="loading"
        :page="page"
        :total="total"
        :items-per-page="perPage"
        per-page-aria-label="Modelos por página"
        @update:page="page = $event"
        @update:items-per-page="setPerPage"
      >
        <template #name-cell="{ row }">
          <span
            class="block min-w-0 max-w-xs truncate font-medium text-highlighted"
            :title="row.original.name || undefined"
          >
            {{ truncateText(row.original.name, 40) || row.original.name || '—' }}
          </span>
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
          <UButton
            size="xs"
            variant="soft"
            label="Gerar"
            @click="openGeneration(row.original)"
          />
        </template>
        <template #empty>
          <UEmpty
            icon="i-lucide-layout-template"
            title="Nenhum modelo"
            description="Crie um modelo para gerar processos."
          />
        </template>
        <template #footer>
          <span class="tabular-nums">{{ total }}</span> modelo(s)
        </template>
      </ShellDataTable>

      <ShellFormModal
        v-model:open="createOpen"
        title="Novo modelo de processo"
        submit-label="Criar"
        :loading="creating"
        :disabled="!formName.trim()"
        :show-default-footer="false"
        @cancel="() => { createOpen = false }"
        @submit="createTemplate"
      >
        <template #body>
          <div class="space-y-4">
            <UFormField label="Nome" required>
              <UInput v-model="formName" placeholder="Ex.: DAS mensal" class="w-full" />
            </UFormField>
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium">
                  Tarefas do modelo
                </p>
                <UButton
                  size="xs"
                  variant="ghost"
                  icon="i-lucide-plus"
                  label="Tarefa"
                  @click="() => addTaskRow()"
                />
              </div>
              <div
                v-for="(t, idx) in formTasks"
                :key="idx"
                class="grid gap-2 rounded-md border border-default p-2 sm:grid-cols-12"
              >
                <UInput
                  v-model="t.title"
                  placeholder="Título"
                  class="sm:col-span-5"
                  :aria-label="`Título da tarefa ${idx + 1}`"
                />
                <UInput
                  :model-value="t.due_rule_value ?? undefined"
                  type="number"
                  placeholder="Dias antes"
                  class="sm:col-span-2"
                  @update:model-value="(v: string | number) => { t.due_rule_value = v === '' || v == null ? 0 : Number(v) }"
                />
                <UCheckbox v-model="t.is_critical" label="Crítica" class="sm:col-span-2" />
                <UCheckbox v-model="t.requires_evidence" label="Evidência" class="sm:col-span-2" />
                <UButton
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  icon="i-lucide-trash"
                  class="sm:col-span-1"
                  aria-label="Remover tarefa"
                  @click="removeTaskRow(idx)"
                />
              </div>
            </div>
          </div>
        </template>
        <template #footer>
          <ShellModalFooter
            submit-label="Criar"
            :loading="creating"
            :disabled="!formName.trim()"
            @cancel="() => { createOpen = false }"
            @submit="createTemplate"
          />
        </template>
      </ShellFormModal>

      <ShellFormModal
        v-model:open="genOpen"
        :title="`Gerar — ${genTemplate?.name || ''}`"
        content-class="max-w-2xl"
        :show-default-footer="false"
        @cancel="() => { genOpen = false }"
      >
        <template #body>
          <UStepper
            :model-value="genStep"
            :items="steps"
            class="mb-6 w-full"
            disabled
          />

          <div v-if="genStep === 1" class="space-y-3">
            <UFormField label="Competência (YYYY-MM)" required>
              <UInput
                v-model="genCompetence"
                data-testid="work-gen-competence"
                placeholder="2026-06"
                class="w-full"
              />
            </UFormField>
            <UFormField label="IDs de clientes (separados por vírgula)" required>
              <UInput
                v-model="genClientIds"
                data-testid="work-gen-clients"
                placeholder="1, 2, 3"
                class="w-full"
              />
            </UFormField>
          </div>

          <div v-else-if="genStep === 2 && genBatch" class="space-y-3">
            <p class="text-sm">
              Batch #{{ genBatch.id }} · status {{ genBatch.status }}
            </p>
            <p v-if="genBatch.preview_summary" class="text-sm text-muted">
              Total {{ genBatch.preview_summary.total }} · prontos {{ genBatch.preview_summary.ready }} · bloqueados {{ genBatch.preview_summary.blocked }}
            </p>
            <ul class="max-h-48 space-y-1 overflow-y-auto text-sm">
              <li
                v-for="item in genBatch.items || []"
                :key="item.id"
                class="rounded border border-default p-2"
              >
                Cliente {{ item.client_id }} · {{ item.status }}
                <span v-if="item.is_blocked" class="text-warning"> (bloqueado)</span>
              </li>
            </ul>
            <UAlert
              v-if="genError"
              color="error"
              :title="genError"
            />
          </div>

          <div v-else-if="genStep === 4 && genBatch" class="space-y-3">
            <UAlert
              color="success"
              :title="`Batch confirmado: ${genBatch.status}`"
            />
            <ul class="space-y-1 text-sm">
              <li
                v-for="item in genBatch.items || []"
                :key="item.id"
              >
                Cliente {{ item.client_id }}
                <NuxtLink
                  v-if="item.created_process_id"
                  :to="`/work/processes/${item.created_process_id}`"
                  class="ms-2 text-primary hover:underline"
                >
                  Processo #{{ item.created_process_id }}
                </NuxtLink>
                <span v-if="item.error_message" class="text-error"> — {{ item.error_message }}</span>
              </li>
            </ul>
            <UButton
              size="sm"
              variant="soft"
              label="Atualizar status"
              :loading="genBusy"
              @click="refreshBatch"
            />
          </div>
        </template>

        <template #footer>
          <ShellModalFooter
            v-if="genOpen"
            :show-cancel="true"
            :show-submit="false"
          >
            <UButton
              color="neutral"
              variant="ghost"
              label="Fechar"
              data-testid="shell-modal-cancel"
              @click="() => { genOpen = false }"
            />
            <UButton
              v-if="genStep === 1"
              data-testid="work-gen-preview"
              :loading="genBusy"
              label="Pré-visualizar"
              @click="() => { void runPreview() }"
            />
            <UButton
              v-if="genStep === 2"
              data-testid="work-gen-confirm"
              color="primary"
              :loading="genBusy"
              label="Confirmar geração"
              @click="() => { void runConfirm() }"
            />
          </ShellModalFooter>
        </template>
      </ShellFormModal>
    </template>
  </ShellPagePanel>
</template>
