<script setup lang="ts">
/**
 * Cadastro do cliente — chrome: ShellSectionHeader (template settings).
 */
import { clientFiscalHref } from '~/utils/client-cross-links'

const {
  clientId,
  item,
  canManageClients,
  registrationEditRequested,
  load
} = useClientDetail()

const fiscalHref = computed(() => clientFiscalHref(clientId.value))

function onEditingChange(value: boolean) {
  registrationEditRequested.value = value
}
</script>

<template>
  <div
    v-if="item"
    class="min-w-0"
    data-testid="client-page-cadastro"
  >
    <ShellSectionHeader
      title="Cadastro"
      description="Dossiê RFB: identificação, situação, atividades, QSA e filiais do contribuinte."
      test-id="client-section-cadastro"
    >
      <UButton
        :to="fiscalHref"
        color="primary"
        variant="soft"
        icon="i-lucide-radar"
        label="Monitoramento fiscal"
        data-testid="client-cadastro-to-fiscal"
      />
    </ShellSectionHeader>

    <ClientsClientRegistration
      :client="item"
      :can-manage-clients="canManageClients"
      :start-editing="registrationEditRequested"
      panel="all"
      @editing-change="onEditingChange"
      @updated="load"
    />
  </div>
</template>
