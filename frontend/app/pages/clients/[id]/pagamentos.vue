<script setup lang="ts">
const { clientId, item, canTriggerSync } = useClientDetail()
</script>

<template>
  <div v-if="item" class="space-y-4">
    <ClientsClientPagtowebPaymentListPanel :client-id="clientId" :can-consult="canTriggerSync" />
    <ClientsClientPagtowebPaymentCountPanel :client-id="clientId" :can-consult="canTriggerSync" />
  </div>
</template>
