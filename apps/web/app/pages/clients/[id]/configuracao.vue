<script setup lang="ts">
import { clientFiscalHref } from '~/utils/client-cross-links'

const {
  clientId,
  item,
  credential,
  canManageClients,
  canManageCredentials,
  load,
  onCredentialActivated
} = useClientDetail()

const fiscalHref = computed(() => clientFiscalHref(clientId.value))
</script>

<template>
  <div
    v-if="item"
    class="min-w-0 space-y-4"
    data-testid="client-page-configuracao"
  >
    <ShellSectionHeader
      title="Configuração"
      description="Certificado, canais de captura e campos personalizados deste cliente."
      test-id="client-section-configuracao"
    >
      <UButton
        :to="fiscalHref"
        color="primary"
        variant="soft"
        icon="i-lucide-radar"
        label="Monitoramento fiscal"
        data-testid="client-config-to-fiscal"
      />
    </ShellSectionHeader>

    <ClientsClientConfigPanel
      :client="item"
      :credential="credential"
      :can-manage-clients="canManageClients"
      :can-manage-credentials="canManageCredentials"
      @updated="load"
      @credential-activated="onCredentialActivated"
    />
  </div>
</template>
