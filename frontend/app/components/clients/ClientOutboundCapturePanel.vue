<script setup lang="ts">
/**
 * Captura de saídas MA — arquétipo Settings (UPageCard + formulários Nuxt UI).
 * Posição sempre em nNF (nunca NSU). Segredos (CSC/A1) só como metadados.
 */
import type {
  Establishment,
  OutboundCaptureProfile,
  OutboundNumberState,
  OutboundSeriesCursor
} from '~/types/api'

const props = defineProps<{
  clientId: number
  establishments: Establishment[]
  canManage: boolean
  canAdmin: boolean
}>()

const api = useApi()
const toast = useToast()

const loading = ref(false)
const profiles = ref<OutboundCaptureProfile[]>([])
const seriesByProfile = ref<Record<number, OutboundSeriesCursor[]>>({})
const gapsBySeries = ref<Record<number, OutboundNumberState[]>>({})
const selectedEstablishmentId = ref<number | null>(null)
const environment = ref<'homologation' | 'production'>('homologation')
const seedXml = ref('')
const seedFile = ref<File | null>(null)
const packageFiles = ref<File[]>([])
const mandateRef = ref('')
const cscToken = ref('')
const cscId = ref('')
const resetReason = ref('')
const resetPosition = ref<number>(1)
const killReason = ref('')
const submitting = ref(false)

const maEstablishments = computed(() =>
  props.establishments.filter((e) => {
    const uf = (e.address?.state || '').toUpperCase()
    return uf === 'MA' || uf === ''
  })
)

const selectedEstablishment = computed(() =>
  maEstablishments.value.find(e => e.id === selectedEstablishmentId.value) || null
)

async function load() {
  loading.value = true
  try {
    const res = await api.outbound.profiles({ client_id: props.clientId })
    profiles.value = res.data || []
    seriesByProfile.value = {}
    for (const p of profiles.value) {
      const s = await api.outbound.series(p.id)
      seriesByProfile.value[p.id] = s.data || []
      for (const ser of seriesByProfile.value[p.id]) {
        const gaps = await api.outbound.numbers(ser.id, true)
        gapsBySeries.value[ser.id] = gaps.data || []
      }
    }
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao carregar captura de saídas.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

async function submitSeed() {
  if (!selectedEstablishment.value || !props.canManage) return
  submitting.value = true
  try {
    await api.outbound.seed(selectedEstablishment.value.id, {
      environment: environment.value,
      xml: seedFile.value ? undefined : seedXml.value,
      file: seedFile.value || undefined
    })
    toast.add({ title: 'Semente registrada (procNFe).', color: 'success' })
    seedXml.value = ''
    seedFile.value = null
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Semente rejeitada.'), color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function submitPackage(profile: OutboundCaptureProfile) {
  if (!props.canManage || packageFiles.value.length === 0) return
  submitting.value = true
  try {
    const result = await api.outbound.uploadPackage(profile.id, packageFiles.value)
    toast.add({
      title: `Pacote: ${result.data.imported} importado(s), ${result.data.skipped} ignorado(s).`,
      color: 'success'
    })
    packageFiles.value = []
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha no pacote oficial.'), color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function activateProfile(profile: OutboundCaptureProfile) {
  if (!props.canAdmin || !mandateRef.value.trim()) return
  submitting.value = true
  try {
    await api.outbound.activate(profile.id, {
      mandate_reference: mandateRef.value.trim(),
      allowlisted: true
    })
    toast.add({ title: 'Perfil ativado (allowlist + mandato).', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Ativação negada.'), color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function saveCsc(profile: OutboundCaptureProfile) {
  if (!props.canAdmin || !cscToken.value || !cscId.value) return
  submitting.value = true
  try {
    await api.outbound.storeCsc(profile.id, { csc: cscToken.value, csc_id: cscId.value })
    cscToken.value = ''
    toast.add({ title: 'CSC armazenado (somente metadados na API).', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao gravar CSC.'), color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function triggerQuery(series: OutboundSeriesCursor) {
  if (!props.canManage) return
  try {
    await api.outbound.triggerQuery(series.id)
    toast.add({ title: `Consulta read-only enfileirada (série ${series.series}, nNF).`, color: 'success' })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Consulta não enfileirada (flags/kill switch).'), color: 'error' })
  }
}

async function resetSeries(series: OutboundSeriesCursor) {
  if (!props.canAdmin || !resetReason.value.trim()) return
  submitting.value = true
  try {
    await api.outbound.resetSeries(series.id, {
      reason: resetReason.value.trim(),
      discovery_position: resetPosition.value,
      confirm: true
    })
    toast.add({ title: 'Posição nNF resetada com auditoria.', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Reset negado.'), color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function killProfile(profile: OutboundCaptureProfile, active: boolean) {
  if (!props.canAdmin || !killReason.value.trim()) return
  submitting.value = true
  try {
    await api.outbound.killSwitch({
      active,
      reason: killReason.value.trim(),
      profile_id: profile.id
    })
    toast.add({ title: active ? 'Kill switch do perfil ligado.' : 'Kill switch desligado.', color: 'warning' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Kill switch falhou.'), color: 'error' })
  } finally {
    submitting.value = false
  }
}

function onSeedFile(files: FileList | File[] | null | undefined) {
  const list = files ? Array.from(files as FileList) : []
  seedFile.value = list[0] || null
}

function onPackageFiles(files: FileList | File[] | null | undefined) {
  packageFiles.value = files ? Array.from(files as FileList) : []
}

onMounted(() => {
  if (maEstablishments.value[0]) {
    selectedEstablishmentId.value = maEstablishments.value[0].id
  }
  load()
})

watch(() => props.clientId, () => load())
</script>

<template>
  <div class="space-y-4" data-testid="outbound-capture-panel">
    <UPageCard
      title="Captura de saídas"
      description="NF-e 55 / NFC-e 65 (MA). Posição por nNF — nunca NSU. Canal assistido por padrão; M2M e mutação desligados."
      variant="subtle"
      :ui="{ body: 'space-y-4' }"
    >
      <UAlert
        color="info"
        variant="subtle"
        title="Somente leitura por padrão"
        description="Consulta 562 e pacote oficial não usam CSC. Fallback 539 exige gates G5 e permanece desligado."
        icon="i-lucide-shield"
      />

      <div v-if="loading" class="text-sm text-muted">
        Carregando perfis…
      </div>

      <template v-else>
        <div class="grid gap-4 md:grid-cols-2">
          <UFormField label="Estabelecimento (MA)">
            <USelect
              v-model="selectedEstablishmentId"
              :items="maEstablishments.map(e => ({ label: `${e.cnpj} ${e.trade_name || ''}`.trim(), value: e.id }))"
              value-key="value"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Ambiente">
            <USelect
              v-model="environment"
              :items="[
                { label: 'Homologação', value: 'homologation' },
                { label: 'Produção', value: 'production' }
              ]"
              value-key="value"
              class="w-full"
            />
          </UFormField>
        </div>

        <UFormField
          v-if="canManage"
          label="XML-semente (procNFe autorizado)"
          description="Não aceita NFe sem protocolo. Não clona itens/tributos."
        >
          <UTextarea
            v-model="seedXml"
            :rows="4"
            placeholder="Cole o procNFe ou envie arquivo…"
            class="w-full font-mono text-xs"
          />
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <input
              type="file"
              accept=".xml,application/xml,text/xml"
              class="text-sm"
              data-testid="outbound-seed-file"
              @change="onSeedFile(($event.target as HTMLInputElement).files)"
            >
            <UButton
              label="Registrar semente"
              icon="i-lucide-sprout"
              :loading="submitting"
              :disabled="!selectedEstablishmentId || (!seedXml && !seedFile)"
              data-testid="outbound-seed-submit"
              @click="submitSeed"
            />
          </div>
        </UFormField>

        <div v-if="profiles.length === 0" class="text-sm text-muted">
          Nenhum perfil de saída configurado. Registre uma semente de estabelecimento MA.
        </div>

        <div
          v-for="profile in profiles"
          :key="profile.id"
          class="rounded-lg border border-default p-4 space-y-3"
          data-testid="outbound-profile-card"
        >
          <div class="flex flex-wrap items-center gap-2 justify-between">
            <div>
              <p class="font-medium">
                Modelo {{ profile.model }} · {{ profile.environment }} · {{ profile.mode }}
              </p>
              <p class="text-xs text-muted">
                Status {{ profile.status }}
                · CSC {{ profile.csc?.configured ? `configurado (id ${profile.csc.csc_id || '—'})` : 'não configurado' }}
                · Allowlist {{ profile.allowlisted ? 'sim' : 'não' }}
                · Kill switch {{ profile.kill_switch ? 'ATIVO' : 'off' }}
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <UBadge :color="profile.status === 'ACTIVE' ? 'success' : 'neutral'" variant="subtle">
                {{ profile.status }}
              </UBadge>
              <UBadge color="info" variant="subtle">
                pos: nNF
              </UBadge>
            </div>
          </div>

          <div v-if="canAdmin && profile.status !== 'ACTIVE'" class="flex flex-wrap gap-2 items-end">
            <UFormField label="Referência do mandato" class="flex-1 min-w-[12rem]">
              <UInput v-model="mandateRef" placeholder="CONTRATO-…" class="w-full" />
            </UFormField>
            <UButton
              label="Ativar perfil"
              color="primary"
              :loading="submitting"
              data-testid="outbound-activate"
              @click="activateProfile(profile)"
            />
          </div>

          <div v-if="canAdmin && profile.model === '65'" class="grid gap-2 md:grid-cols-3 items-end">
            <UFormField label="CSC (não exibido depois)">
              <UInput v-model="cscToken" type="password" autocomplete="off" class="w-full" />
            </UFormField>
            <UFormField label="ID CSC">
              <UInput v-model="cscId" class="w-full" />
            </UFormField>
            <UButton label="Substituir CSC" :loading="submitting" @click="saveCsc(profile)" />
          </div>

          <div v-if="canManage" class="flex flex-wrap items-center gap-2">
            <input
              type="file"
              multiple
              accept=".xml,.zip,application/zip,application/xml"
              class="text-sm"
              data-testid="outbound-package-file"
              @change="onPackageFiles(($event.target as HTMLInputElement).files)"
            >
            <UButton
              label="Enviar pacote oficial"
              icon="i-lucide-package"
              variant="soft"
              :loading="submitting"
              :disabled="packageFiles.length === 0"
              data-testid="outbound-package-submit"
              @click="submitPackage(profile)"
            />
          </div>

          <div
            v-for="ser in (seriesByProfile[profile.id] || [])"
            :key="ser.id"
            class="rounded-md bg-elevated/50 p-3 space-y-2"
            data-testid="outbound-series-card"
          >
            <div class="flex flex-wrap justify-between gap-2">
              <div>
                <p class="text-sm font-medium">
                  Série {{ ser.series }} · semente nNF {{ ser.seed_nnf }} · posição nNF {{ ser.discovery_position }}
                </p>
                <p class="text-xs text-muted">
                  Status {{ ser.status }}
                  · última {{ ser.last_run_at ? formatDateTime(ser.last_run_at) : '—' }}
                  · próxima {{ ser.next_run_at ? formatDateTime(ser.next_run_at) : '—' }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <UButton
                  v-if="canManage"
                  size="sm"
                  variant="soft"
                  label="Consultar (read-only)"
                  icon="i-lucide-search"
                  data-testid="outbound-trigger-query"
                  @click="triggerQuery(ser)"
                />
              </div>
            </div>

            <div v-if="(gapsBySeries[ser.id] || []).length" class="text-xs">
              <p class="font-medium mb-1">
                Lacunas / pendências
              </p>
              <ul class="space-y-1">
                <li
                  v-for="g in gapsBySeries[ser.id]"
                  :key="g.id"
                  class="flex flex-wrap gap-2"
                >
                  <span>nNF {{ g.nnf }}</span>
                  <UBadge size="sm" variant="subtle">
                    {{ g.status }}
                  </UBadge>
                  <span class="text-muted">tentativas {{ g.attempts }}</span>
                  <span v-if="g.has_full_xml" class="text-success">XML ok</span>
                  <span v-else-if="g.discovered_access_key" class="text-warning">chave s/ XML</span>
                </li>
              </ul>
            </div>

            <div v-if="canAdmin" class="grid gap-2 md:grid-cols-3 items-end pt-1 border-t border-default">
              <UFormField label="Reset posição nNF">
                <UInput v-model.number="resetPosition" type="number" min="1" class="w-full" />
              </UFormField>
              <UFormField label="Motivo (obrigatório)">
                <UInput v-model="resetReason" class="w-full" />
              </UFormField>
              <UButton
                color="warning"
                variant="soft"
                label="Reset auditado"
                data-testid="outbound-reset"
                :loading="submitting"
                @click="resetSeries(ser)"
              />
            </div>
          </div>

          <div v-if="canAdmin" class="flex flex-wrap gap-2 items-end border-t border-default pt-3">
            <UFormField label="Motivo kill switch" class="flex-1 min-w-[12rem]">
              <UInput v-model="killReason" class="w-full" />
            </UFormField>
            <UButton
              color="error"
              variant="soft"
              label="Kill switch ON"
              data-testid="outbound-kill-on"
              @click="killProfile(profile, true)"
            />
            <UButton
              variant="ghost"
              label="Kill switch OFF"
              @click="killProfile(profile, false)"
            />
          </div>
        </div>
      </template>
    </UPageCard>
  </div>
</template>
