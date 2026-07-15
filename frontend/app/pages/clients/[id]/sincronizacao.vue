<script setup lang="ts">
import type { OutboundCaptureProfile } from '~/types/api'

const {
  item,
  establishments,
  credential,
  canTriggerSync,
  canManageCredentials,
  canManageClients,
  triggeringId,
  triggeredIds,
  triggerSync
} = useClientDetail()

const api = useApi()
const outboundProfiles = ref<OutboundCaptureProfile[]>([])

onMounted(async () => {
  if (!item.value) return
  try {
    const res = await api.outbound.profiles({ client_id: item.value.id })
    outboundProfiles.value = res.data || []
  } catch {
    outboundProfiles.value = []
  }
})
</script>

<template>
  <div class="space-y-8">
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

    <ClientsClientSvrsNfcePanel
      v-if="item"
      :client-id="item.id"
      :profiles="outboundProfiles"
      :can-manage="canManageClients"
      :can-admin="canManageCredentials"
    />
  </div>
</template>
