<script setup lang="ts">
const {
  item,
  establishments,
  credential,
  canTriggerSync,
  canManageCredentials,
  triggeringId,
  triggeredIds,
  triggerSync
} = useClientDetail()
</script>

<template>
  <ClientsClientSyncPanel
    :establishments="establishments"
    :credential="credential"
    :credential-summary="item?.credential_summary"
    :can-trigger-sync="canTriggerSync"
    :can-manage-credentials="canManageCredentials"
    :triggering-id="triggeringId"
    :triggered-ids="triggeredIds"
    @sync="triggerSync"
  />
</template>
