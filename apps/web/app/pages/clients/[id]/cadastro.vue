<script setup lang="ts">
const { item, canManageClients, load, registrationEditRequested } = useClientDetail()

function onEditingChange(value: boolean) {
  registrationEditRequested.value = value
}
</script>

<template>
  <ClientsClientRegistration
    v-if="item"
    :client="item"
    :can-manage-clients="canManageClients"
    :start-editing="registrationEditRequested"
    @editing-change="onEditingChange"
    @updated="load"
  />
</template>
