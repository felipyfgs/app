<script setup lang="ts">
const { clientId, item, canTriggerSync } = useClientDetail()
</script>

<template>
  <ClientsClientPnrRenunciationsPanel v-if="item" :client-id="clientId" :can-consult="canTriggerSync" />
</template>
