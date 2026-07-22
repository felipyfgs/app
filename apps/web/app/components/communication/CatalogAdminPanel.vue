<script setup lang="ts">
import { apiErrorMessage } from '~/utils/api-error'

const workspace = useCommunicationWorkspace()
const api = useApi()
const toast = useToast()
const labelName = ref('')
const labelColor = ref('neutral')
const cannedTitle = ref('')
const cannedShortcut = ref('')
const cannedBody = ref('')
const saving = ref(false)

const colorItems = [
  'neutral', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald',
  'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'
].map(value => ({ label: value, value }))

async function createLabel() {
  if (!labelName.value.trim()) return
  saving.value = true
  try {
    await api.communication.catalog.createLabel({
      name: labelName.value.trim(),
      color: labelColor.value
    })
    labelName.value = ''
    await workspace.loadCatalog()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao criar marcador.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function deleteLabel(id: number) {
  try {
    await api.communication.catalog.deleteLabel(id)
    await workspace.loadCatalog()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao excluir marcador.'), color: 'error' })
  }
}

async function createCanned() {
  if (!cannedTitle.value.trim() || !cannedShortcut.value.trim() || !cannedBody.value.trim()) return
  saving.value = true
  try {
    await api.communication.catalog.createCannedResponse({
      title: cannedTitle.value.trim(),
      shortcut: cannedShortcut.value.trim().toLowerCase(),
      body: cannedBody.value.trim(),
      is_active: true
    })
    cannedTitle.value = ''
    cannedShortcut.value = ''
    cannedBody.value = ''
    await workspace.loadCatalog()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao criar resposta pronta.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function deleteCanned(id: number) {
  try {
    await api.communication.catalog.deleteCannedResponse(id)
    await workspace.loadCatalog()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao excluir resposta pronta.'), color: 'error' })
  }
}
</script>

<template>
  <div
    data-testid="communication-catalog-admin"
    class="grid gap-6 xl:grid-cols-2"
  >
    <UCard variant="subtle">
      <template #header>
        <div>
          <p class="font-semibold text-highlighted">
            Marcadores
          </p>
          <p class="text-xs text-muted">
            Classificação compartilhada das conversas.
          </p>
        </div>
      </template>

      <div class="space-y-3">
        <div class="grid gap-2 sm:grid-cols-[1fr_9rem_auto]">
          <UInput
            v-model="labelName"
            placeholder="Nome do marcador"
          />
          <USelectMenu
            v-model="labelColor"
            :items="colorItems"
            value-key="value"
          />
          <UButton
            icon="i-lucide-plus"
            aria-label="Criar marcador"
            :loading="saving"
            @click="createLabel"
          />
        </div>
        <div class="space-y-2">
          <div
            v-for="label in workspace.labels.value"
            :key="label.id"
            class="flex items-center justify-between rounded-md border border-default px-3 py-2"
          >
            <UBadge
              :label="label.name"
              color="neutral"
              variant="soft"
            />
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="ghost"
              size="xs"
              aria-label="Excluir marcador"
              @click="deleteLabel(label.id)"
            />
          </div>
        </div>
      </div>
    </UCard>

    <UCard variant="subtle">
      <template #header>
        <div>
          <p class="font-semibold text-highlighted">
            Respostas prontas
          </p>
          <p class="text-xs text-muted">
            Textos reutilizáveis disponíveis no composer.
          </p>
        </div>
      </template>

      <div class="space-y-3">
        <div class="grid gap-2 sm:grid-cols-2">
          <UInput
            v-model="cannedTitle"
            placeholder="Título"
          />
          <UInput
            v-model="cannedShortcut"
            placeholder="atalho_sem_espaco"
          />
        </div>
        <UTextarea
          v-model="cannedBody"
          placeholder="Texto da resposta pronta"
          :rows="3"
          class="w-full"
        />
        <div class="flex justify-end">
          <UButton
            label="Criar resposta"
            icon="i-lucide-plus"
            :loading="saving"
            @click="createCanned"
          />
        </div>

        <div class="space-y-2">
          <div
            v-for="item in workspace.cannedResponses.value"
            :key="item.id"
            class="flex items-start justify-between gap-3 rounded-md border border-default px-3 py-2"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-highlighted">
                {{ item.shortcut }} · {{ item.title }}
              </p>
              <p class="line-clamp-2 text-xs text-muted">
                {{ item.body }}
              </p>
            </div>
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="ghost"
              size="xs"
              aria-label="Excluir resposta pronta"
              @click="deleteCanned(item.id)"
            />
          </div>
        </div>
      </div>
    </UCard>
  </div>
</template>
