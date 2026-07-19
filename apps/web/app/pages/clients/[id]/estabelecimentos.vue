<script setup lang="ts">
/**
 * Aba Estabelecimentos: CNPJ deste cliente + filiais vinculadas à matriz.
 * Cada filial tem cadastro próprio; aqui só o vínculo e a lista.
 */
const {
  item,
  establishments,
  canManageClients,
  canManageCredentials,
  load
} = useClientDetail()

function onBranchCreated(id: number) {
  // recarrega lista de filiais na matriz; opcionalmente ir à filial
  load()
  // permanece na matriz para ver a lista atualizada
  void id
}
</script>

<template>
  <ClientsClientBranchesPanel
    v-if="item"
    :client="item"
    :establishments="establishments"
    :can-manage-clients="canManageClients"
    :can-manage-credentials="canManageCredentials"
    @updated="load"
    @branch-created="onBranchCreated"
  />
</template>
