<script setup lang="ts">
const { item, canManageClients, load } = useClientDetail()
</script>

<template>
  <ClientsClientRegistration
    v-if="item"
    :client="item"
    :can-manage-clients="canManageClients"
    @updated="load"
  />
</template>
