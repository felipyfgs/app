<script setup lang="ts">
import type { NfseNote } from '~/types/api'

const route = useRoute()
const api = useApi()
const toast = useToast()
const accessKey = String(route.params.accessKey)
const note = ref<NfseNote | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    note.value = (await api.notes.get(accessKey)).data
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível carregar a nota.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <UDashboardPanel id="note-detail">
    <template #header>
      <UDashboardNavbar title="Detalhe da nota">
        <template #leading>
          <div class="flex items-center gap-1">
            <UDashboardSidebarCollapse />
            <UButton
              to="/notes"
              color="neutral"
              variant="ghost"
              icon="i-lucide-arrow-left"
              square
              aria-label="Voltar para notas"
            />
          </div>
        </template>
        <template #right>
          <UButton
            v-if="note"
            :href="api.notes.xmlUrl(note.access_key)"
            external
            download
            icon="i-lucide-download"
            label="Baixar XML original"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div v-if="loading" class="space-y-4">
        <USkeleton class="h-28 w-full" />
        <USkeleton class="h-64 w-full" />
      </div>
      <UEmpty
        v-else-if="!note"
        icon="i-lucide-file-x"
        title="Nota não encontrada"
        description="A chave não existe ou pertence a outro escritório."
      >
        <UButton to="/notes" label="Voltar para notas" />
      </UEmpty>
      <template v-else>
        <UCard>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <p class="text-sm text-muted">
                Chave de acesso
              </p>
              <p class="break-all font-mono text-sm font-medium text-highlighted">
                {{ note.access_key }}
              </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
              <UBadge color="info" variant="subtle">
                {{ statusLabel(note.fiscal_role) }}
              </UBadge>
              <AppStatusBadge :status="note.status" />
            </div>
          </div>
        </UCard>

        <div class="grid gap-4 lg:grid-cols-2">
          <UCard>
            <template #header>
              <h2 class="font-semibold">
                Dados fiscais
              </h2>
            </template>
            <dl class="grid gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-sm text-muted">
                  CNPJ emitente
                </dt><dd class="font-mono text-highlighted">
                  {{ note.issuer_cnpj || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  CNPJ tomador
                </dt><dd class="font-mono text-highlighted">
                  {{ note.taker_cnpj || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  CNPJ intermediário
                </dt><dd class="font-mono text-highlighted">
                  {{ note.intermediary_cnpj || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  Valor do serviço
                </dt><dd class="text-highlighted">
                  {{ formatCurrency(note.service_amount) }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  Competência
                </dt><dd class="text-highlighted">
                  {{ note.competence || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  Data de emissão
                </dt><dd class="text-highlighted">
                  {{ formatDateTime(note.issued_at) }}
                </dd>
              </div>
            </dl>
          </UCard>

          <UCard>
            <template #header>
              <h2 class="font-semibold">
                Documento original
              </h2>
            </template>
            <dl class="space-y-3">
              <div>
                <dt class="text-sm text-muted">
                  Tipo
                </dt><dd class="text-highlighted">
                  {{ note.document?.document_type || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  Versão do schema
                </dt><dd class="text-highlighted">
                  {{ note.document?.schema_version || 'Desconhecida' }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  Tamanho
                </dt><dd class="text-highlighted">
                  {{ formatBytes(note.document?.byte_size) }}
                </dd>
              </div>
              <div>
                <dt class="text-sm text-muted">
                  SHA-256
                </dt><dd class="break-all font-mono text-xs text-highlighted">
                  {{ note.document?.sha256 || '—' }}
                </dd>
              </div>
            </dl>
          </UCard>
        </div>

        <UAlert
          v-if="note.document?.parse_status !== 'OK'"
          color="warning"
          icon="i-lucide-file-warning"
          title="XML preservado para revisão"
          :description="note.document?.parse_alert || 'A versão ou o XSD ainda não é reconhecido. O XML original permanece disponível.'"
        />
        <UAlert
          icon="i-lucide-shield-check"
          title="Download auditado"
          description="O XML é transmitido diretamente do cofre e o download é registrado na trilha de auditoria."
        />
      </template>
    </template>
  </UDashboardPanel>
</template>
