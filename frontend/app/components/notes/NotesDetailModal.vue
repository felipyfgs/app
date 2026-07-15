<script setup lang="ts">
/**
 * Modal de detalhe do documento fiscal.
 *
 * Anatomia (skill modal + ClientDetailModal):
 *  title / description / #actions
 *  #body → layout tipo nota (NotesDetail)
 *  #footer → copiar · baixar XML · fechar
 */
import type { NfseNote } from '~/types/api'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  accessKey: string | null
  preview?: NfseNote | null
}>()

const sanctum = useSanctumClient()
const toast = useToast()
const downloading = ref(false)

const title = computed(() => {
  const n = props.preview
  if (n?.number) return `${n.kind_label || documentKindLabel(n.kind)} nº ${n.number}`
  const key = props.accessKey
  if (!key) return 'Detalhe do documento'
  if (key.length <= 22) return key
  return `${key.slice(0, 10)}…${key.slice(-8)}`
})

const description = computed(() => {
  const n = props.preview
  if (!n) return 'Documento fiscal eletrônico'
  const parts = [
    n.kind_label || documentKindLabel(n.kind),
    n.competence ? `Competência ${n.competence}` : null,
    n.fiscal_role ? statusLabel(n.fiscal_role) : null,
    n.xml_completeness === 'SUMMARY_ONLY' || n.is_summary ? 'somente resumo' : null
  ].filter(Boolean)
  return parts.length ? parts.join(' · ') : 'Documento fiscal eletrônico'
})

async function copyAccessKey() {
  const key = (props.accessKey || '').trim()
  if (!key) return
  try {
    await navigator.clipboard.writeText(key)
    toast.add({ title: 'Chave copiada', color: 'success' })
  } catch {
    toast.add({ title: 'Não foi possível copiar a chave', color: 'error' })
  }
}

/**
 * Download via client Sanctum (mesmo baseUrl/proxy das demais APIs) + blob .xml.
 * Não usa fetch(apiUrl) cru — evita path errado e salva sempre como .xml.
 */
async function downloadXml() {
  const key = (props.accessKey || '').trim()
  if (!key || downloading.value) return

  downloading.value = true
  try {
    const blob = await sanctum<Blob>(
      `/api/v1/documents/${encodeURIComponent(key)}/xml`,
      {
        method: 'GET',
        responseType: 'blob',
        headers: {
          Accept: 'application/xml, text/xml, application/octet-stream, */*'
        }
      }
    )

    if (!(blob instanceof Blob)) {
      toast.add({ title: 'Resposta inválida ao baixar XML', color: 'error' })
      return
    }

    // Rejeita corpo JSON de erro (ex.: 422 serializado como blob)
    const head = await blob.slice(0, 64).text()
    const trimmed = head.trimStart()
    if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
      let msg = 'XML indisponível no cofre.'
      try {
        const err = JSON.parse(await blob.text()) as { message?: string }
        if (err?.message) msg = err.message
      } catch {
        // ignore
      }
      toast.add({ title: msg, color: 'error' })
      return
    }

    const filename = `${key}.xml`
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
    toast.add({ title: 'XML baixado', color: 'success' })
  } catch (caught: unknown) {
    const msg = apiErrorMessage(caught, 'Falha ao baixar o XML. Verifique a sessão e tente de novo.')
    toast.add({ title: msg, color: 'error' })
  } finally {
    downloading.value = false
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    data-testid="note-detail-modal"
    :title="title"
    :description="description"
    :ui="{
      content: 'w-[calc(100vw-1.5rem)] sm:w-[min(42rem,calc(100vw-2rem))] lg:w-[min(48rem,calc(100vw-2rem))] sm:max-w-none h-[min(92dvh,52rem)] max-h-[min(92dvh,52rem)] overflow-hidden',
      body: 'flex min-h-0 flex-1 flex-col overflow-hidden p-0 sm:p-0',
      footer: 'justify-between gap-2 shrink-0'
    }"
  >
    <template #actions>
      <div
        v-if="preview"
        class="me-6 flex flex-wrap items-center gap-1.5"
      >
        <AppStatusBadge
          v-if="preview.status"
          :status="preview.status"
          :label="preview.status_label"
        />
        <UBadge
          v-if="preview.service_amount != null"
          color="neutral"
          variant="subtle"
          class="tabular-nums"
          size="sm"
        >
          {{ formatCurrency(preview.service_amount) }}
        </UBadge>
      </div>
    </template>

    <template #body>
      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
        <NotesDetail
          v-if="accessKey"
          :access-key="accessKey"
          :preview="preview"
          embedded
        />
      </div>
    </template>

    <template #footer="{ close }">
      <div class="flex w-full flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-muted">
          Download do XML é auditado
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <UButton
            v-if="accessKey"
            color="neutral"
            variant="ghost"
            size="sm"
            icon="i-lucide-copy"
            label="Copiar chave"
            @click="copyAccessKey"
          />
          <UButton
            v-if="accessKey"
            color="primary"
            variant="soft"
            size="sm"
            icon="i-lucide-download"
            label="Baixar XML"
            :loading="downloading"
            :disabled="downloading"
            @click="downloadXml"
          />
          <UButton
            color="neutral"
            variant="subtle"
            size="sm"
            label="Fechar"
            @click="close"
          />
        </div>
      </div>
    </template>
  </UModal>
</template>
