<script setup lang="ts">
/**
 * Resumo: dashboard operacional (KPIs + atalhos). Cadastro completo fica na aba Cadastro.
 */
const {
  item,
  credential,
  establishments,
  triggeredIds,
  canManageCredentials,
  canManageClients,
  canTriggerSync
} = useClientDetail()
</script>

<template>
  <ClientsClientDashboard
    v-if="item"
    data-testid="client-resumo"
    :client="item"
    :credential="credential"
    :establishments="establishments"
    :triggered-ids="triggeredIds"
    :can-manage-credentials="canManageCredentials"
    :can-manage-clients="canManageClients"
    :can-trigger-sync="canTriggerSync"
  />
</template>
