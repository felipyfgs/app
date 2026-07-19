<script setup lang="ts">
/**
 * Cadastro do cliente — chrome: ShellSectionHeader (template settings).
 */
const {
  item,
  canManageClients,
  registrationEditRequested,
  load
} = useClientDetail()

function onEditingChange(value: boolean) {
  registrationEditRequested.value = value
}
</script>

<template>
  <div
    v-if="item"
    class="min-w-0"
    data-testid="client-page-cadastro"
  >
    <ShellSectionHeader
      title="Cadastro"
      description="Dados cadastrais, sócios e filiais do contribuinte."
      test-id="client-section-cadastro"
    />

    <ClientsClientRegistration
      :client="item"
      :can-manage-clients="canManageClients"
      :start-editing="registrationEditRequested"
      panel="all"
      @editing-change="onEditingChange"
      @updated="load"
    />
  </div>
</template>
