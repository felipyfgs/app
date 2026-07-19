<script setup lang="ts">
const {
  clientId,
  item,
  credential,
  canManageCredentials,
  onCredentialActivated
} = useClientDetail()
</script>

<template>
  <ClientsClientCredentialPanel
    :client-id="clientId"
    :credential="credential"
    :credential-summary="item?.credential_summary"
    :can-manage-credentials="canManageCredentials"
    @activated="onCredentialActivated"
  />
</template>
