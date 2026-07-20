<script setup lang="ts">
/**
 * Modal aninhado — Histórico de Declarações (nível 2 do DAS Simples Nacional).
 * Downloads usam artefatos PGDAS-D já persistidos; abrir o modal não chama SERPRO.
 */
import type {
  PgdasdArtifactDescriptor,
  PgdasdHistoryDas,
  PgdasdHistoryDeclaration,
  PgdasdHistoryPeriod
} from '~/types/fiscal-modules'
import { resolveApiUrl } from '~/utils/api-url'
import { formatDateTime } from '~/utils/format'
import { formatPgdasdPeriod } from '~/utils/pgdasd'
import { usePgdasdMonitoring } from '~/composables/usePgdasdMonitoring'

const props = defineProps<{
  open: boolean
  period: PgdasdHistoryPeriod | null
  clientName?: string | null
  cnpjMasked?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { artifactDownloadUrl } = usePgdasdMonitoring()
const apiBase = useRuntimeConfig().public.apiBase as string

function resolveOperationLabel(raw: string | null | undefined, fallback: 'declaration' | 'das'): string {
  if (!raw) return fallback === 'declaration' ? 'Declaração' : 'Geração de DAS'
  const normalized = raw.trim().toUpperCase().replaceAll('-', '_').replaceAll(' ', '_')
  const labels: Record<string, string> = {
    ORIGINAL: 'Original',
    RECTIFIER: 'Retificadora',
    RECTIFYING: 'Retificadora',
    RETIFICADORA: 'Retificadora',
    DAS_GENERATION: 'Geração de DAS',
    GENERATION_OF_DAS: 'Geração de DAS'
  }
  if (labels[normalized]) return labels[normalized]
  if (raw !== raw.toUpperCase()) return raw
  const readable = raw.replaceAll('_', ' ').toLocaleLowerCase('pt-BR')
  return readable.charAt(0).toLocaleUpperCase('pt-BR') + readable.slice(1)
}

function yesNoLabel(value?: string | boolean | null): string {
  if (value == null || value === '') return '—'
  if (value === true || ['SIM', 'TRUE', '1'].includes(String(value).toUpperCase())) return 'Sim'
  if (value === false || ['NAO', 'NÃO', 'FALSE', '0'].includes(String(value).toUpperCase())) return 'Não'
  return String(value)
}

function periodArtifacts(period: PgdasdHistoryPeriod | null): PgdasdArtifactDescriptor[] {
  if (!period) return []
  const all = [
    ...(period.artifacts || []),
    ...(period.documents || []),
    ...(period.declarations || []).flatMap(item => item.documents || []),
    ...(period.das || []).flatMap(item => item.documents || [])
  ]
  return [...new Map(all.map(item => [item.id, item])).values()]
}

function artifactByKind(kinds: string[]): PgdasdArtifactDescriptor | null {
  const wanted = kinds.map(k => k.toUpperCase())
  return periodArtifacts(props.period).find(a =>
    wanted.includes(String(a.kind || '').toUpperCase())
  ) || null
}

function artifactHref(artifact: PgdasdArtifactDescriptor | null): string | null {
  if (!artifact) return null
  const path = artifact.download_path?.trim() || artifact.download_href?.trim()
  if (path) return resolveApiUrl(path, apiBase)
  if (artifact.id) return artifactDownloadUrl(artifact.id)
  return null
}

interface NestedRow {
  key: string
  operation: string
  declarationNumber: string | null
  transmittedAt: string | null
  malha: string | boolean | null
  dasNumber: string | null
  issuedAt: string | null
  reciboHref: string | null
  declaracaoHref: string | null
  maedHref: string | null
  extratoHref: string | null
}

const rows = computed<NestedRow[]>(() => {
  const period = props.period
  if (!period) return []
  const declarations = period.declarations || []
  const dasItems = period.das || []
  const recibo = artifactByKind(['RECIBO'])
  const declaracao = artifactByKind(['DECLARACAO'])
  const maed = artifactByKind(['NOTIFICACAO_MAED', 'DARF_MAED', 'MAED'])
  const extrato = artifactByKind(['EXTRATO'])

  const fromDeclarations: NestedRow[] = declarations.map((d: PgdasdHistoryDeclaration, index) => ({
    key: `decl-${d.id || d.declaration_number || d.number || index}`,
    operation: resolveOperationLabel(d.normalized_operation_type || d.operation_type, 'declaration'),
    declarationNumber: d.declaration_number || d.number || null,
    transmittedAt: d.transmitted_at || null,
    malha: d.malha ?? null,
    dasNumber: null,
    issuedAt: null,
    reciboHref: artifactHref(recibo),
    declaracaoHref: artifactHref(declaracao),
    maedHref: artifactHref(maed),
    extratoHref: artifactHref(extrato)
  }))

  const fromDas: NestedRow[] = dasItems.map((das: PgdasdHistoryDas, index) => ({
    key: `das-${das.id || das.das_number || index}`,
    operation: resolveOperationLabel(das.normalized_operation_type, 'das'),
    declarationNumber: null,
    transmittedAt: null,
    malha: null,
    dasNumber: das.das_number || null,
    issuedAt: das.issued_at || null,
    reciboHref: artifactHref(recibo),
    declaracaoHref: artifactHref(declaracao),
    maedHref: artifactHref(maed),
    extratoHref: artifactHref(extrato)
  }))

  return [...fromDeclarations, ...fromDas]
})

const periodLabel = computed(() => formatPgdasdPeriod(props.period?.period_key))
const description = computed(() => {
  const parts = [
    props.clientName?.trim() || null,
    props.cnpjMasked?.trim() || null,
    periodLabel.value !== '—' ? `PA ${periodLabel.value}` : null
  ].filter(Boolean)
  return parts.join(' · ') || 'Histórico local do período'
})
</script>

<template>
  <ShellScrollableModal
    :open="open"
    title="Histórico de Declarações"
    :description="description"
    content-class="w-[calc(100vw-1rem)] sm:max-w-5xl"
    test-id="pgdasd-declarations-history-modal"
    :show-default-footer="false"
    @update:open="emit('update:open', $event)"
    @cancel="emit('update:open', false)"
  >
    <template #body>
      <div class="space-y-4">
        <p class="text-xs text-muted">
          Artefatos já armazenados localmente. Abrir este modal não dispara consulta SERPRO.
        </p>

        <div
          v-if="!rows.length"
          class="rounded-lg border border-dashed border-default px-4 py-10 text-center text-sm text-muted"
        >
          Nenhuma declaração ou DAS neste período.
        </div>

        <div
          v-else
          class="overflow-x-auto rounded-lg border border-default"
          role="region"
          aria-label="Histórico de declarações do período"
        >
          <table class="w-full min-w-[960px] border-separate border-spacing-0 text-left text-sm">
            <thead class="bg-elevated/60 text-xs text-muted">
              <tr>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Operação
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Nº Declaração
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Transmissão
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Recibo
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Declaração
                </th>
                <th class="border-b border-default px-3 py-2.5 text-center font-medium">
                  Malha
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  MAED
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Nº DAS
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Data Emissão
                </th>
                <th class="border-b border-default px-3 py-2.5 font-medium">
                  Extrato
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.key"
                class="group"
              >
                <td class="border-b border-default px-3 py-3 group-hover:bg-elevated/40">
                  {{ row.operation }}
                </td>
                <td class="border-b border-default px-3 py-3 font-mono text-xs tabular-nums group-hover:bg-elevated/40">
                  {{ row.declarationNumber || '—' }}
                </td>
                <td class="whitespace-nowrap border-b border-default px-3 py-3 text-xs group-hover:bg-elevated/40">
                  {{ formatDateTime(row.transmittedAt) }}
                </td>
                <td class="border-b border-default px-3 py-3 group-hover:bg-elevated/40">
                  <UButton
                    v-if="row.reciboHref"
                    size="xs"
                    color="neutral"
                    variant="soft"
                    icon="i-lucide-download"
                    label="Recibo"
                    :to="row.reciboHref"
                    external
                    target="_blank"
                    rel="noopener noreferrer"
                  />
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="border-b border-default px-3 py-3 group-hover:bg-elevated/40">
                  <UButton
                    v-if="row.declaracaoHref"
                    size="xs"
                    color="neutral"
                    variant="soft"
                    icon="i-lucide-download"
                    label="PDF"
                    :to="row.declaracaoHref"
                    external
                    target="_blank"
                    rel="noopener noreferrer"
                  />
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="border-b border-default px-3 py-3 text-center group-hover:bg-elevated/40">
                  {{ yesNoLabel(row.malha) }}
                </td>
                <td class="border-b border-default px-3 py-3 group-hover:bg-elevated/40">
                  <UButton
                    v-if="row.maedHref"
                    size="xs"
                    color="neutral"
                    variant="soft"
                    icon="i-lucide-download"
                    label="MAED"
                    :to="row.maedHref"
                    external
                    target="_blank"
                    rel="noopener noreferrer"
                  />
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="border-b border-default px-3 py-3 font-mono text-xs tabular-nums group-hover:bg-elevated/40">
                  {{ row.dasNumber || '—' }}
                </td>
                <td class="whitespace-nowrap border-b border-default px-3 py-3 text-xs group-hover:bg-elevated/40">
                  {{ formatDateTime(row.issuedAt) }}
                </td>
                <td class="border-b border-default px-3 py-3 group-hover:bg-elevated/40">
                  <UButton
                    v-if="row.extratoHref"
                    size="xs"
                    color="neutral"
                    variant="soft"
                    icon="i-lucide-download"
                    label="Extrato"
                    :to="row.extratoHref"
                    external
                    target="_blank"
                    rel="noopener noreferrer"
                  />
                  <span v-else class="text-muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </ShellScrollableModal>
</template>
