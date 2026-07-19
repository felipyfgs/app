<script setup lang="ts">
const {
  item,
  credential,
  canManageClients,
  canManageCredentials,
  load,
  onCredentialActivated
} = useClientDetail()
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
    />

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
