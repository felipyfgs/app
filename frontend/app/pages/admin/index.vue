<script setup lang="ts">
/**
 * Hub da plataforma (`/admin`) — reservado a PLATFORM_ADMIN (OpenSpec 6.2).
 * Sem configuração de escritório (movida para /settings).
 * Seletor global no OfficeIdentity; banner de contexto privilegiado no shell.
 */
import type { BackupStatus, PlatformOfficeSummary } from '~/types/api'

const api = useApi()
const { me, canAccessPlatformAdmin, isPlatformPrivileged } = useDashboard()
const {
  offices,
  loading,
  loadError,
  loadOffices,
  selectOffice,
  clearSelection,
  switching,
  privileged
} = usePlatformOfficeSelect()

const q = ref('')

const backup = ref<BackupStatus | null>(null)
const backupLoading = ref(false)
const backupError = ref<string | null>(null)

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return offices.value
  return offices.value.filter(o =>
    o.name.toLowerCase().includes(term)
    || o.slug.toLowerCase().includes(term)
    || String(o.id).includes(term)
  )
})

async function loadBackup() {
  if (!canAccessPlatformAdmin.value) {
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

onMounted(() => {
  if (canAccessPlatformAdmin.value) {
    void loadOffices()
    void loadBackup()
  }
})

async function onSelect(office: PlatformOfficeSummary) {
  await selectOffice(office.id)
}
</script>

<template>
  <UDashboardPanel
    id="admin"
    data-testid="admin-platform-panel"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        title="Plataforma"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-3xl lg:gap-12">
        <UAlert
          v-if="!canAccessPlatformAdmin"
          color="warning"
          icon="i-lucide-shield-off"
          title="Acesso restrito à plataforma"
          description="/admin/* é exclusivo de PLATFORM_ADMIN. A configuração do escritório está em Configurações."
          data-testid="admin-access-denied"
        />

        <template v-else>
          <UPageCard
            variant="naked"
            title="Administração da plataforma"
            description="Contrato SERPRO, saúde técnica, orçamento e seletor global de escritórios. Sem TOTP global na navegação."
          />

          <UPageCard
            variant="subtle"
            data-testid="admin-actor-card"
          >
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  Administrador
                </dt>
                <dd class="text-highlighted">
                  {{ me?.name }} · {{ me?.email }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Modo de acesso
                </dt>
                <dd>
                  <UBadge
                    :color="isPlatformPrivileged ? 'warning' : 'neutral'"
                    variant="subtle"
                  >
                    {{ isPlatformPrivileged ? 'Contexto privilegiado' : 'Plataforma (sem office)' }}
                  </UBadge>
                </dd>
              </div>
              <div v-if="me?.office">
                <dt class="text-muted">
                  Office resolvido
                </dt>
                <dd class="text-highlighted">
                  {{ me.office.name }}
                  <span
                    v-if="me.office.slug"
                    class="text-muted"
                  > ({{ me.office.slug }})</span>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Capacidades efetivas
                </dt>
                <dd class="text-highlighted">
                  {{ isPlatformPrivileged ? 'Equivalente a Office ADMIN no office selecionado' : 'Somente superfícies /admin e console SERPRO' }}
                </dd>
              </div>
            </dl>
            <UAlert
              v-if="isPlatformPrivileged"
              class="mt-4"
              color="warning"
              icon="i-lucide-shield-alert"
              title="Auditoria interna ativa"
              description="Leituras e mutações relevantes são registradas com o administrador real. A trilha não aparece para o escritório."
            />
            <div
              v-if="privileged"
              class="mt-4 flex justify-end"
            >
              <UButton
                color="neutral"
                variant="outline"
                icon="i-lucide-log-out"
                label="Encerrar contexto privilegiado"
                :loading="switching"
                data-testid="admin-clear-privileged"
                @click="() => { void clearSelection() }"
              />
            </div>
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Seletor global de escritórios"
            description="Lista qualquer office ativo. Não cria membership nem personifica usuário."
          />
          <UPageCard
            variant="subtle"
            data-testid="admin-global-office-selector"
          >
            <div class="mb-4 flex flex-wrap items-center gap-2">
              <UInput
                v-model="q"
                icon="i-lucide-search"
                placeholder="Buscar por nome, slug ou id…"
                class="w-full sm:max-w-sm"
                aria-label="Filtrar escritórios da plataforma"
                data-testid="admin-office-search"
              />
              <UButton
                color="neutral"
                variant="soft"
                icon="i-lucide-refresh-cw"
                label="Atualizar"
                :loading="loading"
                @click="() => { void loadOffices() }"
              />
            </div>

            <div
              v-if="loading && !offices.length"
              class="space-y-2"
              role="status"
              aria-label="Carregando escritórios"
            >
              <USkeleton class="h-10 w-full" />
              <USkeleton class="h-10 w-full" />
              <USkeleton class="h-10 w-2/3" />
            </div>
            <UAlert
              v-else-if="loadError"
              color="warning"
              icon="i-lucide-wifi-off"
              :title="loadError"
              :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => loadOffices() }]"
              data-testid="admin-offices-error"
            />
            <UEmpty
              v-else-if="!filtered.length"
              icon="i-lucide-building-2"
              title="Nenhum escritório encontrado"
              description="Quando a API de offices da plataforma estiver disponível, a lista aparece aqui e no seletor da sidebar."
              data-testid="admin-offices-empty"
            />
            <ul
              v-else
              class="divide-y divide-default"
              role="listbox"
              aria-label="Escritórios da plataforma"
            >
              <li
                v-for="office in filtered"
                :key="office.id"
                class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-highlighted">
                    {{ office.name }}
                  </p>
                  <p class="text-xs text-muted">
                    #{{ office.id }}
                    <span v-if="office.slug"> · {{ office.slug }}</span>
                    <span v-if="office.plan"> · {{ office.plan }}</span>
                  </p>
                </div>
                <UButton
                  size="sm"
                  :color="me?.office?.id === office.id && privileged ? 'warning' : 'neutral'"
                  :variant="me?.office?.id === office.id && privileged ? 'soft' : 'outline'"
                  :label="me?.office?.id === office.id && privileged ? 'Selecionado' : 'Operar'"
                  icon="i-lucide-shield"
                  :loading="switching"
                  :aria-label="`Selecionar escritório ${office.name} em contexto privilegiado`"
                  @click="onSelect(office)"
                />
              </li>
            </ul>
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Console SERPRO"
            description="Contrato global, readiness, kill switch, cobertura e conciliação."
          />
          <UPageCard variant="subtle">
            <p class="text-sm text-muted">
              Superfície global sanitizada — sem PFX, Consumer Secret, token ou XML.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <UButton
                to="/admin/serpro"
                label="Abrir console"
                icon="i-lucide-server-cog"
                data-testid="admin-open-serpro"
              />
              <UButton
                v-if="isPlatformPrivileged"
                to="/settings"
                color="neutral"
                variant="outline"
                label="Configuração do office"
                icon="i-lucide-sliders-horizontal"
                data-testid="admin-open-office-settings"
              />
            </div>
          </UPageCard>

          <UPageCard
            variant="naked"
            title="Backup da instância"
            description="Somente leitura — sem restore pelo painel."
          />
          <UPageCard
            variant="subtle"
            data-testid="admin-backup-card"
          >
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
            <dl
              v-else-if="backup"
              class="space-y-3 text-sm"
            >
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
            <p
              v-else
              class="text-sm text-muted"
            >
              Sem dados de backup.
            </p>
            <p class="mt-4 text-xs text-muted">
              Comandos: <code class="text-highlighted">php artisan ops:backup-run</code>
              e <code class="text-highlighted">php artisan ops:backup-restore-drill</code>.
              A chave mestra permanece em custódia offline. Não há restore pelo painel.
            </p>
          </UPageCard>

          <UAlert
            color="info"
            icon="i-lucide-info"
            title="Configuração do escritório mudou de lugar"
            description="Perfil, consentimento, A1 e agendas ficam em Configurações. /admin não exibe mais identidade fiscal do tenant."
          />
        </template>
      </div>
    </template>
  </UDashboardPanel>
</template>
