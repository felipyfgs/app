<script setup lang="ts">
import QRCode from 'qrcode'
import type { OfficeMember } from '~/types/api'
import type { CommunicationInbox, CommunicationPairingState } from '~/types/communication'
import type { WorkDepartment } from '~/types/work'
import { COMMUNICATION_INBOX_STATUS, formatCommunicationDate } from '~/utils/communication'

const props = defineProps<{
  inbox: CommunicationInbox
  members: OfficeMember[]
  departments: WorkDepartment[]
}>()

const workspace = useCommunicationWorkspace()
const name = ref(props.inbox.name)
const enabled = ref(props.inbox.is_enabled)
const isDefault = ref(props.inbox.is_default)
const departmentId = ref<number>(props.inbox.work_department_id || 0)
const memberIds = ref<number[]>([...(props.inbox.member_ids ?? [])])
const saving = ref(false)
const pairing = ref<CommunicationPairingState | null>(null)
const pairingLoading = ref(false)
const qrDataUrl = ref<string | null>(null)
const revokeOpen = ref(false)
let pollingTimer: ReturnType<typeof setTimeout> | null = null

const departmentItems = computed(() => [
  { label: 'Sem departamento', value: 0 },
  ...props.departments.map(department => ({ label: department.name, value: department.id }))
])

watch(() => props.inbox, (inbox) => {
  name.value = inbox.name
  enabled.value = inbox.is_enabled
  isDefault.value = inbox.is_default
  departmentId.value = inbox.work_department_id || 0
  memberIds.value = [...(inbox.member_ids ?? [])]
}, { deep: true })

watch(() => pairing.value?.code, async (code) => {
  qrDataUrl.value = null
  if (!code || pairing.value?.event !== 'code') return
  try {
    qrDataUrl.value = await QRCode.toDataURL(String(code), {
      errorCorrectionLevel: 'M',
      margin: 2,
      width: 280
    })
  } catch {
    qrDataUrl.value = null
  }
})

function toggleMember(id: number, selected: boolean | 'indeterminate') {
  const next = new Set(memberIds.value)
  if (selected === true) next.add(id)
  else next.delete(id)
  memberIds.value = [...next]
}

async function save() {
  saving.value = true
  const settingsSaved = await workspace.updateInbox(props.inbox, {
    name: name.value.trim(),
    is_enabled: enabled.value,
    is_default: isDefault.value,
    work_department_id: departmentId.value || null
  })
  if (settingsSaved) {
    await workspace.replaceInboxMembers(props.inbox.id, memberIds.value)
  }
  saving.value = false
}

async function pollPairing() {
  if (pollingTimer) clearTimeout(pollingTimer)
  pairing.value = await workspace.getPairing(props.inbox.id)
  if (props.inbox.status === 'CONNECTED' || pairing.value?.event === 'success') return
  const expiresAt = pairing.value?.expires_at ? new Date(pairing.value.expires_at).getTime() : Date.now() + 120_000
  if (expiresAt <= Date.now() || pairing.value?.event === 'timeout' || pairing.value?.event === 'error') return
  pollingTimer = setTimeout(() => void pollPairing(), 2500)
}

async function startPairing() {
  pairingLoading.value = true
  pairing.value = await workspace.startPairing(props.inbox.id)
  pairingLoading.value = false
  void pollPairing()
}

async function revoke() {
  if (await workspace.revokeInbox(props.inbox.id)) {
    revokeOpen.value = false
    pairing.value = null
    qrDataUrl.value = null
  }
}

function openRevoke() {
  revokeOpen.value = true
}

function closeRevoke() {
  revokeOpen.value = false
}

onBeforeUnmount(() => {
  if (pollingTimer) clearTimeout(pollingTimer)
})
</script>

<template>
  <UCard
    :data-testid="`communication-inbox-admin-${inbox.id}`"
    variant="subtle"
  >
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
          <p class="truncate font-semibold text-highlighted">
            {{ inbox.name }}
          </p>
          <p class="text-xs text-muted">
            {{ inbox.address_masked || 'Número ainda não conectado' }}
          </p>
        </div>
        <UBadge
          :label="COMMUNICATION_INBOX_STATUS[inbox.status].label"
          :icon="COMMUNICATION_INBOX_STATUS[inbox.status].icon"
          :color="COMMUNICATION_INBOX_STATUS[inbox.status].color"
          variant="subtle"
        />
      </div>
    </template>

    <div class="grid gap-4 sm:grid-cols-2">
      <UFormField label="Nome do canal">
        <UInput
          v-model="name"
          class="w-full"
          maxlength="120"
        />
      </UFormField>
      <UFormField label="Fila padrão">
        <USelectMenu
          v-model="departmentId"
          :items="departmentItems"
          value-key="value"
          class="w-full"
        />
      </UFormField>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
      <USwitch
        v-model="enabled"
        label="Canal habilitado"
        description="Permite conectar e transportar mensagens."
      />
      <USwitch
        v-model="isDefault"
        label="Inbox geral"
        description="Saída padrão das automações fiscais."
      />
    </div>

    <USeparator class="my-4" />

    <div>
      <p class="mb-2 text-sm font-medium text-highlighted">
        Membros autorizados
      </p>
      <div class="grid max-h-40 gap-2 overflow-y-auto rounded-md border border-default p-3 sm:grid-cols-2">
        <UCheckbox
          v-for="member in members"
          :key="member.id"
          :model-value="memberIds.includes(member.id)"
          :label="member.name || member.email || `Membro #${member.id}`"
          @update:model-value="toggleMember(member.id, $event)"
        />
        <p
          v-if="!members.length"
          class="text-xs text-muted"
        >
          Nenhum membro ativo encontrado.
        </p>
      </div>
    </div>

    <div
      v-if="pairing"
      class="mt-4 rounded-lg border border-default bg-default p-4 text-center"
    >
      <img
        v-if="qrDataUrl"
        :src="qrDataUrl"
        alt="QR Code efêmero para parear o WhatsApp"
        class="mx-auto size-64 max-w-full rounded-md bg-white p-2"
      >
      <div
        v-else-if="pairing.code"
        class="mx-auto max-w-sm rounded-md bg-elevated p-3 font-mono text-lg tracking-widest text-highlighted"
      >
        {{ pairing.code }}
      </div>
      <p class="mt-3 text-sm text-muted">
        Abra WhatsApp → Aparelhos conectados → Conectar aparelho.
      </p>
      <p
        v-if="pairing.expires_at"
        class="mt-1 text-xs text-muted"
      >
        Expira em {{ formatCommunicationDate(pairing.expires_at) }}.
      </p>
    </div>

    <template #footer>
      <div class="flex flex-wrap justify-end gap-2">
        <UButton
          v-if="inbox.status !== 'CONNECTED' && inbox.status !== 'REVOKED'"
          label="Parear WhatsApp"
          icon="i-lucide-qr-code"
          color="neutral"
          variant="outline"
          :loading="pairingLoading"
          :disabled="!enabled"
          @click="startPairing"
        />
        <UButton
          v-if="inbox.status === 'CONNECTED' || inbox.status === 'DEGRADED'"
          label="Revogar sessão"
          icon="i-lucide-unplug"
          color="error"
          variant="soft"
          @click="openRevoke"
        />
        <UButton
          label="Salvar canal"
          icon="i-lucide-save"
          :loading="saving"
          :disabled="!name.trim()"
          @click="save"
        />
      </div>
    </template>
  </UCard>

  <UModal
    v-model:open="revokeOpen"
    title="Revogar sessão do WhatsApp?"
    description="O gateway fará logout e descartará as credenciais locais desta inbox."
  >
    <template #body>
      <UAlert
        title="A inbox deixará de enviar imediatamente. Um novo pareamento será necessário."
        color="error"
        icon="i-lucide-triangle-alert"
        variant="subtle"
      />
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          label="Cancelar"
          color="neutral"
          variant="ghost"
          @click="closeRevoke"
        />
        <UButton
          label="Revogar"
          color="error"
          @click="revoke"
        />
      </div>
    </template>
  </UModal>
</template>
