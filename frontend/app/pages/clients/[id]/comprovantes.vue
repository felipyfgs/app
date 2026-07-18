<script setup lang="ts">
// Arquétipo Settings: filho de /clients/[id], baseado no template de settings.
const { clientId, item, canTriggerSync } = useClientDetail()
</script>

<template>
  <div v-if="item" class="space-y-4">
    <ClientsClientPagtowebArrecadacaoReceiptPanel :client-id="clientId" :can-consult="canTriggerSync" />
  </div>
</template>
