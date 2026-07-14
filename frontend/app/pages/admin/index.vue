<script setup lang="ts">
import type { BackupStatus } from '~/types/api'

const { me, canAccessAdministration } = useDashboard()
const api = useApi()

// Conteúdo administrativo só após confirmação de papel e 2FA (middleware + gate local).
const allowed = computed(() => canAccessAdministration.value)

const backup = ref<BackupStatus | null>(null)
const backupLoading = ref(false)
const backupError = ref<string | null>(null)

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

watch(allowed, (ok) => {
  if (ok) {
    void loadBackup()
  }
}, { immediate: true })
</script>

<template>
  <UDashboardPanel id="admin" data-testid="settings-panel" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar title="Administração">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
      <!-- Toolbar omitida: uma única seção real no MVP. -->
    </template>

    <template #body>
      <div class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-2xl lg:gap-12">
        <UAlert
          v-if="!allowed"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito"
          description="Somente administradores com segundo fator confirmado podem acessar esta área."
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
            title="Certificados A1"
            description="Gerenciados no detalhe de cada cliente."
          />
          <UPageCard variant="subtle">
            <p class="text-sm text-muted">
              A API expõe somente metadados públicos e não possui rota de recuperação de PFX, senha ou chave privada.
            </p>
            <UButton class="mt-4" to="/clients" label="Gerenciar por cliente" />
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Backup da instância"
            description="Somente leitura. Restore e drill são comandos operacionais (CLI), não rotas da API."
          />
          <UPageCard variant="subtle" data-testid="admin-backup-card">
            <div v-if="backupLoading" class="space-y-2" role="status" aria-label="Carregando status de backup">
              <USkeleton class="h-4 w-1/2" />
              <USkeleton class="h-4 w-2/3" />
            </div>
            <UAlert
              v-else-if="backupError"
              color="warning"
              icon="i-lucide-wifi-off"
              title="Status de backup indisponível"
              :description="backupError"
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
            title="Chave mestra fora da aplicação"
            description="A VAULT_MASTER_KEY deve permanecer separada do banco e dos backups comuns. A perda da chave torna os objetos irrecuperáveis."
          />
        </template>
      </div>
    </template>
  </UDashboardPanel>
</template>
