<script setup lang="ts">
/**
 * Select compacto de transição de status da tarefa (listas Work).
 */
import type { TaskStatus } from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import {
  workTaskStatusOptions,
  type WorkTaskInlineAction
} from '~/utils/work-task-status-options'
import { taskStatusLabel } from '~/utils/work-labels'

const props = defineProps<{
  taskId: number
  status: TaskStatus
  lockVersion: number
  /** Sem responsável → ofereceere claim quando A_FAZER. */
  canClaim?: boolean
  disabled?: boolean
  size?: 'xs' | 'sm' | 'md'
}>()

const emit = defineEmits<{
  updated: []
}>()

const api = useApi()
const toast = useToast()

const loading = ref(false)
const blockOpen = ref(false)
const blockReason = ref('')
const pendingAction = ref<WorkTaskInlineAction | null>(null)

const options = computed(() => workTaskStatusOptions(props.status, {
  canClaim: props.canClaim === true
}))

const selectItems = computed(() =>
  options.value.map(option => ({
    label: option.label,
    value: option.value
  }))
)

const currentLabel = computed(() => taskStatusLabel(props.status))

async function runAction(action: WorkTaskInlineAction, reason?: string) {
  loading.value = true
  try {
    const lock = props.lockVersion
    if (action === 'start') await api.work.tasks.start(props.taskId, lock)
    else if (action === 'complete') await api.work.tasks.complete(props.taskId, lock)
    else if (action === 'resume') await api.work.tasks.resume(props.taskId, lock)
    else if (action === 'claim') await api.work.tasks.claim(props.taskId, lock)
    else if (action === 'block') await api.work.tasks.block(props.taskId, lock, reason || '')
    toast.add({ title: 'Status atualizado', color: 'success' })
    emit('updated')
  } catch (e: unknown) {
    const statusCode = (e as { statusCode?: number, status?: number })?.statusCode
      ?? (e as { status?: number })?.status
    toast.add({
      title: apiErrorMessage(e, 'Não foi possível atualizar o status.'),
      color: 'error'
    })
    if (statusCode === 409) {
      emit('updated')
    }
  } finally {
    loading.value = false
    pendingAction.value = null
    blockOpen.value = false
    blockReason.value = ''
  }
}

function onSelect(value: string | undefined | null) {
  if (!value || loading.value || props.disabled) return
  const action = value as WorkTaskInlineAction
  if (action === 'block') {
    pendingAction.value = 'block'
    blockReason.value = ''
    blockOpen.value = true
    return
  }
  void runAction(action)
}

function confirmBlock() {
  if (!blockReason.value.trim()) {
    toast.add({ title: 'Informe o motivo do impedimento.', color: 'warning' })
    return
  }
  void runAction('block', blockReason.value.trim())
}
</script>

<template>
  <div class="inline-flex min-w-0 items-center gap-1" data-testid="work-task-status-select">
    <USelect
      :model-value="undefined"
      :items="selectItems"
      :placeholder="currentLabel"
      :disabled="disabled || loading || !selectItems.length"
      :loading="loading"
      :size="size || 'xs'"
      class="min-w-36"
      aria-label="Alterar status da tarefa"
      @update:model-value="onSelect"
    />

    <ShellFormModal
      v-model:open="blockOpen"
      title="Impedir tarefa"
      description="Informe o motivo do impedimento."
      submit-label="Impedir"
      submit-color="warning"
      :loading="loading"
      :disabled="!blockReason.trim()"
      test-id="work-task-status-block-modal"
      @submit="confirmBlock"
    >
      <template #body>
        <UFormField label="Motivo" required>
          <UTextarea
            v-model="blockReason"
            :rows="3"
            maxlength="2000"
            placeholder="Descreva o impedimento…"
            data-testid="work-task-status-block-reason"
          />
        </UFormField>
      </template>
    </ShellFormModal>
  </div>
</template>
