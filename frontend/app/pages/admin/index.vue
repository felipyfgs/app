<script setup lang="ts">
const { me } = useDashboard()
</script>

<template>
  <UDashboardPanel id="admin">
    <template #header>
      <UDashboardNavbar title="Administração">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UAlert
        v-if="me?.role !== 'ADMIN'"
        color="warning"
        title="Acesso restrito"
        description="Somente administradores com 2FA confirmado podem acessar esta área."
      />
      <template v-else>
        <div class="grid gap-4 lg:grid-cols-2">
          <UCard>
            <template #header>
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-shield-check" class="size-5 text-success" />
                <h2 class="font-semibold">
                  Conta administrativa
                </h2>
              </div>
            </template>
            <dl class="space-y-3 text-sm">
              <div>
                <dt class="text-muted">
                  Usuário
                </dt><dd class="text-highlighted">
                  {{ me.name }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  E-mail
                </dt><dd class="text-highlighted">
                  {{ me.email }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Escritório
                </dt><dd class="text-highlighted">
                  {{ me.office?.name || '—' }}
                </dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-muted">
                  Segundo fator
                </dt>
                <UBadge :color="me.two_factor_confirmed ? 'success' : 'error'" variant="subtle">
                  {{ me.two_factor_confirmed ? 'Confirmado' : 'Pendente' }}
                </UBadge>
              </div>
            </dl>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-key-round" class="size-5 text-primary" />
                <h2 class="font-semibold">
                  Certificados A1
                </h2>
              </div>
            </template>
            <p class="text-sm text-muted">
              O A1 é gerenciado no detalhe de cada cliente. A API expõe somente metadados públicos e não possui rota de recuperação de PFX, senha ou chave privada.
            </p>
            <UButton class="mt-4" to="/clients" label="Gerenciar por cliente" />
          </UCard>
        </div>

        <UAlert
          color="warning"
          icon="i-lucide-lock-keyhole"
          title="Chave mestra fora da aplicação"
          description="A VAULT_MASTER_KEY deve permanecer separada do banco e dos backups comuns. A perda da chave torna os objetos irrecuperáveis."
        />
      </template>
    </template>
  </UDashboardPanel>
</template>
