<script setup lang="ts">
/**
 * Seção Captura de saídas — arquétipo Settings do template dashboard.
 */
const {
  item,
  establishments,
  canManageClients,
  canManageCredentials
} = useClientDetail()
</script>

<template>
  <ClientsClientOutboundCapturePanel
    v-if="item"
    :client-id="item.id"
    :establishments="establishments"
    :can-manage="canManageClients"
    :can-admin="canManageCredentials"
  />
  <UAlert
    v-else
    color="neutral"
    variant="subtle"
    title="Cliente indisponível"
    description="Carregue o detalhe do cliente para configurar saídas MA."
  />
</template>
