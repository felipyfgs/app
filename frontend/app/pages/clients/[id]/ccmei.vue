<script setup lang="ts">
const { clientId, item, canTriggerSync } = useClientDetail()
</script>

<template>
  <div v-if="item" class="space-y-4">
    <ClientsClientCcmeiPanel :client-id="clientId" :can-consult="canTriggerSync" />
    <ClientsClientCcmeiRegistrationStatusPanel :client-id="clientId" :can-consult="canTriggerSync" />
    <ClientsClientCcmeiCertificateIssuancePanel :client-id="clientId" :can-consult="canTriggerSync" />
  </div>
</template>
