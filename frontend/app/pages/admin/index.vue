<script setup lang="ts">
const { me, canAccessAdministration } = useDashboard()

// Conteúdo administrativo só após confirmação de papel e 2FA (middleware + gate local).
const allowed = computed(() => canAccessAdministration.value)
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
