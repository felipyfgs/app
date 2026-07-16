<script setup lang="ts">
import type { BackupStatus } from '~/types/api'

const { me, canAccessAdministration, canAccessPlatformSerpro } = useDashboard()
const api = useApi()

// Conteúdo administrativo só após confirmação de papel e 2FA (middleware + gate local).
const allowed = computed(() => canAccessAdministration.value)

const backup = ref<BackupStatus | null>(null)
const backupLoading = ref(false)
const backupError = ref<string | null>(null)

const fiscalIdentity = ref<Record<string, unknown> | null>(null)
const fiscalCredential = ref<Record<string, unknown> | null>(null)
const fiscalLoading = ref(false)
const fiscalError = ref<string | null>(null)
const fiscalCnpj = ref('')
const fiscalLegalName = ref('')
const fiscalPfx = ref<File | null>(null)
const fiscalPassword = ref('')
const fiscalSaving = ref(false)
const toast = useToast()

async function loadBackup() {
  if (!allowed.value) {
    backup.value = null
    return
  }
  backupLoading.value = true
  try {
    const summary = (await api.operations.summary()).data
    backup.value = summary.backup ?? null
    backupError.value = null
  } catch (caught) {
    backupError.value = apiErrorMessage(caught, 'Não foi possível carregar o status de backup.')
  } finally {
    backupLoading.value = false
  }
}

async function loadFiscal() {
  if (!allowed.value) {
    fiscalIdentity.value = null
    fiscalCredential.value = null
    return
  }
  fiscalLoading.value = true
  try {
    const res = await api.officeFiscal.get()
    fiscalIdentity.value = res.data.identity
    fiscalCredential.value = res.data.credential
    fiscalError.value = null
    if (res.data.identity?.cnpj) {
      fiscalCnpj.value = String(res.data.identity.cnpj)
    }
    if (res.data.identity?.legal_name) {
      fiscalLegalName.value = String(res.data.identity.legal_name)
    }
  } catch (caught) {
    fiscalError.value = apiErrorMessage(caught, 'Não foi possível carregar a identidade fiscal do escritório.')
  } finally {
    fiscalLoading.value = false
  }
}

async function saveIdentity() {
  if (fiscalSaving.value) return
  fiscalSaving.value = true
  try {
    const res = await api.officeFiscal.upsertIdentity({
      cnpj: fiscalCnpj.value,
      legal_name: fiscalLegalName.value || undefined
    })
    fiscalIdentity.value = res.data
    toast.add({ title: 'Identidade fiscal salva', color: 'success' })
    await loadFiscal()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao salvar identidade.'), color: 'error' })
  } finally {
    fiscalSaving.value = false
  }
}

async function uploadOfficeA1() {
  if (fiscalSaving.value || !fiscalPfx.value) return
  fiscalSaving.value = true
  try {
    await api.officeFiscal.uploadCredential(fiscalPfx.value, fiscalPassword.value)
    fiscalPassword.value = ''
    fiscalPfx.value = null
    toast.add({ title: 'A1 do escritório atualizado (sem recuperação de PFX)', color: 'success' })
    await loadFiscal()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao enviar A1.'), color: 'error' })
  } finally {
    fiscalSaving.value = false
  }
}

function onPfxChange(event: Event) {
  const input = event.target as HTMLInputElement
  fiscalPfx.value = input.files?.[0] ?? null
}

function copyCnpj() {
  const c = String(fiscalIdentity.value?.cnpj || fiscalCnpj.value || '')
  if (!c) return
  void navigator.clipboard.writeText(c)
  toast.add({ title: 'CNPJ copiado', color: 'success' })
}

watch(allowed, (ok) => {
  if (ok) {
    void loadBackup()
    void loadFiscal()
  }
}, { immediate: true })
</script>

<template>
  <UDashboardPanel id="admin" data-testid="settings-panel" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar title="Administração" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-2xl lg:gap-12">
        <UAlert
          v-if="!allowed"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito"
        />

        <template v-else-if="me">
          <UPageCard
            variant="naked"
            title="Conta administrativa"
            description="Identidade da sessão e estado do segundo fator."
          />
          <UPageCard variant="subtle">
            <dl class="space-y-3 text-sm">
              <div>
                <dt class="text-muted">
                  Usuário
                </dt>
                <dd class="text-highlighted">
                  {{ me.name }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  E-mail
                </dt>
                <dd class="text-highlighted">
                  {{ me.email }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Escritório
                </dt>
                <dd class="text-highlighted">
                  {{ me.office?.name || '—' }}
                </dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-muted">
                  Segundo fator
                </dt>
                <UBadge
                  :color="!me.two_factor_required || me.two_factor_confirmed ? 'success' : 'error'"
                  variant="subtle"
                >
                  <UIcon
                    :name="!me.two_factor_required || me.two_factor_confirmed ? 'i-lucide-check' : 'i-lucide-x'"
                    class="mr-1 size-3"
                    aria-hidden="true"
                  />
                  {{ !me.two_factor_required ? 'Desativado em desenvolvimento' : me.two_factor_confirmed ? 'Confirmado' : 'Pendente' }}
                </UBadge>
              </div>
            </dl>
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Identidade fiscal do escritório"
            description="CNPJ do contador para autXML DistDFe e A1 do escritório (ADMIN + 2FA)."
            data-testid="admin-office-fiscal"
          />
          <UPageCard variant="subtle">
            <div v-if="fiscalLoading" class="space-y-2" role="status">
              <USkeleton class="h-4 w-1/2" />
              <USkeleton class="h-4 w-2/3" />
            </div>
            <UAlert
              v-else-if="fiscalError"
              color="warning"
              icon="i-lucide-wifi-off"
              :title="fiscalError"
              :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: loadFiscal }]"
            />
            <div v-else class="space-y-4 text-sm">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-muted">CNPJ ativo:</span>
                <code class="rounded bg-elevated px-2 py-0.5 text-highlighted">
                  {{ fiscalIdentity?.cnpj || '—' }}
                </code>
                <UButton
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  icon="i-lucide-copy"
                  label="Copiar"
                  :disabled="!fiscalIdentity?.cnpj"
                  @click="copyCnpj"
                />
              </div>
              <p class="text-muted">
                A1 do escritório:
                <UBadge
                  class="ml-1"
                  :color="fiscalCredential ? 'success' : 'neutral'"
                  variant="subtle"
                >
                  {{ fiscalCredential ? 'Configurado' : 'Ausente' }}
                </UBadge>
                <span v-if="fiscalCredential?.valid_to" class="ml-2 text-xs">
                  válido até {{ formatDateTime(String(fiscalCredential.valid_to)) }}
                </span>
              </p>
              <div class="grid gap-3 sm:grid-cols-2">
                <UFormField label="CNPJ do escritório">
                  <UInput v-model="fiscalCnpj" placeholder="14 caracteres" autocomplete="off" />
                </UFormField>
                <UFormField label="Razão social (opcional)">
                  <UInput v-model="fiscalLegalName" autocomplete="organization" />
                </UFormField>
              </div>
              <UButton
                color="primary"
                label="Salvar identidade"
                :loading="fiscalSaving"
                :disabled="!fiscalCnpj"
                @click="saveIdentity"
              />
              <USeparator />
              <p class="text-xs text-muted">
                Upload de A1: a senha só trafega nesta requisição; não há download nem recuperação de PFX/PEM.
              </p>
              <div class="grid gap-3 sm:grid-cols-2">
                <UFormField label="Arquivo PFX/P12">
                  <input
                    type="file"
                    accept=".pfx,.p12,application/x-pkcs12"
                    class="block w-full text-sm"
                    @change="onPfxChange"
                  >
                </UFormField>
                <UFormField label="Senha do PFX">
                  <UInput
                    v-model="fiscalPassword"
                    type="password"
                    autocomplete="new-password"
                  />
                </UFormField>
              </div>
              <UButton
                color="neutral"
                variant="outline"
                label="Substituir A1 do escritório"
                :loading="fiscalSaving"
                :disabled="!fiscalPfx || !fiscalPassword"
                @click="uploadOfficeA1"
              />
            </div>
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Onboarding autXML por estabelecimento"
            data-testid="admin-autxml-onboarding"
          />
          <UPageCard variant="subtle" data-testid="admin-autxml-card">
            <OfficeAutXmlOnboardingChecklist />
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Certificados A1 dos clientes"
            description="Gerenciados no detalhe de cada cliente."
          />
          <UPageCard variant="subtle">
            <p class="text-sm text-muted">
              A API expõe somente metadados públicos e não possui rota de recuperação de PFX, senha ou chave privada.
            </p>
            <UButton class="mt-4" to="/clients" label="Gerenciar por cliente" />
          </UPageCard>

          <UPageCard
            v-if="canAccessPlatformSerpro"
            variant="naked"
            title="Console SERPRO (plataforma)"
            description="Contrato global, readiness, kill switch, cobertura e conciliação — PLATFORM_ADMIN."
            data-testid="admin-serpro-link-card"
          />
          <UPageCard
            v-if="canAccessPlatformSerpro"
            variant="subtle"
          >
            <p class="text-sm text-muted">
              Superfície global sem dados fiscais de tenants. Requer TOTP.
            </p>
            <UButton
              class="mt-4"
              to="/admin/serpro"
              label="Abrir console SERPRO"
              icon="i-lucide-server-cog"
            />
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Backup da instância"
          />
          <UPageCard variant="subtle" data-testid="admin-backup-card">
            <div
              v-if="backupLoading"
              class="space-y-2"
              role="status"
              aria-label="Carregando status de backup"
            >
              <USkeleton class="h-4 w-1/2" />
              <USkeleton class="h-4 w-2/3" />
            </div>
            <UAlert
              v-else-if="backupError"
              color="warning"
              icon="i-lucide-wifi-off"
              :title="backupError"
              :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: loadBackup }]"
            />
            <dl v-else-if="backup" class="space-y-3 text-sm">
              <div class="flex items-center justify-between gap-3">
                <dt class="text-muted">
                  Estado
                </dt>
                <UBadge
                  :color="backup.never ? 'error' : backup.stale ? 'warning' : 'success'"
                  variant="subtle"
                >
                  {{ backup.never ? 'Nunca executado' : backup.stale ? 'Atrasado (>24h)' : 'OK' }}
                </UBadge>
              </div>
              <div>
                <dt class="text-muted">
                  Último SUCCESS
                </dt>
                <dd class="text-highlighted">
                  {{ backup.last_success_at ? formatDateTime(backup.last_success_at) : '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Último status de run
                </dt>
                <dd class="text-highlighted">
                  {{ backup.last_status || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Último restore drill
                </dt>
                <dd class="text-highlighted">
                  <template v-if="backup.last_restore_drill_at">
                    {{ formatDateTime(backup.last_restore_drill_at) }}
                    <span class="text-muted"> ({{ backup.last_restore_drill_status || '—' }})</span>
                  </template>
                  <template v-else>
                    —
                  </template>
                </dd>
              </div>
            </dl>
            <p v-else class="text-sm text-muted">
              Sem dados de backup.
            </p>
            <p class="mt-4 text-xs text-muted">
              Comandos: <code class="text-highlighted">php artisan ops:backup-run</code>
              e <code class="text-highlighted">php artisan ops:backup-restore-drill</code>.
              A chave mestra permanece em custódia offline.
            </p>
          </UPageCard>

          <UAlert
            color="warning"
            icon="i-lucide-lock-keyhole"
            title="VAULT_MASTER_KEY externa e separada dos backups é obrigatória"
          />
        </template>
      </div>
    </template>
  </UDashboardPanel>
</template>
