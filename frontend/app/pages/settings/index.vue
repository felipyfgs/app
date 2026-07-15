<script setup lang="ts">
/**
 * Onboarding: Autor do Pedido, Termo, saúde, ações requeridas (15.3).
 * SEM campos de recuperação de segredo (sem download PFX/XML/token).
 */
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { OfficeSerproAuthorization, SerproPlatformHealth } from '~/types/api'

const authorSchema = z.object({
  authorType: z.enum(['CNPJ', 'CPF']),
  authorIdentity: z.string().min(1, 'Informe a identidade do Autor do Pedido.'),
  authorName: z.string().max(160, 'Use no máximo 160 caracteres.'),
  certificateMode: z.enum(['EXTERNAL_SIGNATURE', 'MANAGED_A1'])
}).superRefine((data, context) => {
  const identity = data.authorIdentity.replace(/[^0-9A-Za-z]/g, '').toUpperCase()
  const valid = data.authorType === 'CPF'
    ? /^\d{11}$/.test(identity)
    : /^[0-9A-Z]{14}$/.test(identity)

  if (!valid) {
    context.addIssue({
      code: 'custom',
      path: ['authorIdentity'],
      message: data.authorType === 'CPF'
        ? 'Informe um CPF com 11 dígitos.'
        : 'Informe um CNPJ com 14 caracteres.'
    })
  }
})

type AuthorSchema = z.output<typeof authorSchema>

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const saving = ref(false)
const loadError = ref<string | null>(null)
const auth = ref<OfficeSerproAuthorization | null>(null)
const health = ref<SerproPlatformHealth | null>(null)
const strategy = ref<string | null>(null)

const authorForm = reactive<AuthorSchema>({
  authorType: 'CNPJ',
  authorIdentity: '',
  authorName: '',
  certificateMode: 'EXTERNAL_SIGNATURE'
})
const termoFile = ref<File | null>(null)
const a1File = ref<File | null>(null)
const a1Password = ref('')
const a1Consent = ref(false)

const actionsRequired = computed(() => {
  const raw = auth.value?.actions_required
  if (!raw) return [] as string[]
  return raw.map((item) => {
    if (typeof item === 'string') return item
    return item.message || item.code || JSON.stringify(item)
  })
})

function clearSensitive() {
  a1Password.value = ''
  a1File.value = null
  a1Consent.value = false
  termoFile.value = null
}

/** Labels operacionais acionáveis (sem jargão técnico). */
function authorizationStatusLabel(status?: string | null) {
  switch (status) {
    case 'DRAFT': return 'Configure o Autor do Pedido'
    case 'PENDING_TERM': return 'Envie o Termo assinado'
    case 'TERM_VALID': return 'Termo válido localmente — autentique o procurador'
    case 'TOKEN_ACTIVE': return 'Autorização ativa'
    case 'ACTION_REQUIRED': return 'Ação necessária no Termo ou token'
    case 'BLOCKED': return 'Autorização bloqueada'
    case 'EXPIRED': return 'Autorização expirada — renove o Termo/token'
    case 'REVOKED': return 'Autorização revogada'
    default: return status || '—'
  }
}

function termoStateLabel(state?: string | null) {
  switch (state) {
    case 'LOCAL_VALIDATED': return 'Validado localmente (ainda sem aceite SERPRO)'
    case 'SERPRO_ACCEPTED': return 'Aceito pelo SERPRO'
    case 'SIMULATED': return 'Simulado (desenvolvimento)'
    case 'REJECTED': return 'Rejeitado — revise o Termo'
    case 'PENDING': return 'Pendente de validação'
    case 'EXPIRED': return 'Expirado'
    default: return state || '—'
  }
}

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.office.serproAuthorization.show()
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    auth.value = res.data
    health.value = res.platform_health || null
    strategy.value = res.term_representation_strategy || null
    if (res.data.author_identity_type === 'CNPJ' || res.data.author_identity_type === 'CPF') {
      authorForm.authorType = res.data.author_identity_type
    }
    if (res.data.author_name) authorForm.authorName = res.data.author_name
    if (
      res.data.certificate_mode === 'EXTERNAL_SIGNATURE'
      || res.data.certificate_mode === 'MANAGED_A1'
    ) {
      authorForm.certificateMode = res.data.certificate_mode
    }
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    auth.value = null
    health.value = null
    strategy.value = null
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar autorização Integra.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function saveAuthor(event: FormSubmitEvent<AuthorSchema>) {
  saving.value = true
  try {
    const res = await api.office.serproAuthorization.configureAuthor({
      author_identity_type: event.data.authorType,
      author_identity: event.data.authorIdentity.replace(/[^0-9A-Za-z]/g, '').toUpperCase(),
      author_name: event.data.authorName || undefined,
      certificate_mode: event.data.certificateMode
    })
    auth.value = res.data
    authorForm.authorIdentity = ''
    toast.add({ title: 'Autor do Pedido configurado', color: 'success' })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao configurar Autor.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function uploadTermo() {
  if (!termoFile.value) {
    toast.add({ title: 'Selecione o XML do Termo assinado.', color: 'warning' })
    return
  }
  saving.value = true
  try {
    const body = new FormData()
    body.append('termo_file', termoFile.value)
    const res = await api.office.serproAuthorization.uploadTermo(body)
    auth.value = res.data
    termoFile.value = null
    toast.add({ title: 'Termo enviado (armazenado no cofre — sem recuperação)', color: 'success' })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao enviar Termo.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function uploadA1() {
  if (!a1File.value || !a1Password.value || !a1Consent.value) {
    toast.add({ title: 'PFX, senha e consentimento são obrigatórios.', color: 'warning' })
    return
  }
  saving.value = true
  try {
    const res = await api.office.serproAuthorization.storeAuthorA1(
      a1File.value,
      a1Password.value,
      true
    )
    auth.value = res.data
    clearSensitive()
    toast.add({ title: 'A1 do Autor armazenado (sem recuperação de PFX)', color: 'success' })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao armazenar A1 do Autor.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function refreshToken() {
  saving.value = true
  try {
    const res = await api.office.serproAuthorization.refreshToken()
    auth.value = res.data
    toast.add({ title: 'Token do procurador atualizado (valor nunca exibido)', color: 'success' })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao renovar token.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

watch(sessionEpoch, () => {
  auth.value = null
  health.value = null
  strategy.value = null
  clearSensitive()
  void load()
})
onMounted(load)
onBeforeUnmount(clearSensitive)
</script>

<template>
  <div class="space-y-6 lg:space-y-8">
    <UPageCard
      variant="naked"
      orientation="horizontal"
      title="Onboarding Integra Contador"
      description="Autor do Pedido, Termo e saúde sanitizada. Credenciais SERPRO da plataforma nunca são expostas ao tenant."
    />

    <UAlert
      v-if="loadError"
      color="error"
      icon="i-lucide-circle-x"
      :title="loadError"
    />

    <div
      v-if="loading && !auth"
      class="text-sm text-muted"
    >
      Carregando…
    </div>

    <template v-else-if="auth">
      <UAlert
        v-if="actionsRequired.length"
        color="warning"
        icon="i-lucide-list-checks"
        title="Ações requeridas"
      >
        <ul class="mt-2 list-disc space-y-1 ps-4 text-sm">
          <li
            v-for="(action, i) in actionsRequired"
            :key="i"
          >
            {{ action }}
          </li>
        </ul>
      </UAlert>

      <div class="grid gap-4 xl:grid-cols-2">
        <UPageCard
          variant="subtle"
          title="Estado atual"
          description="Situação da autorização deste escritório."
        >
          <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-muted">
                Status
              </dt>
              <dd class="font-medium">
                {{ authorizationStatusLabel(auth.status) }}
                <span class="block text-xs text-muted font-normal">{{ auth.status }}</span>
              </dd>
            </div>
            <div v-if="auth.termo_authorization_state || auth.authorization_state">
              <dt class="text-muted">
                Validação do Termo
              </dt>
              <dd class="font-medium">
                {{ termoStateLabel(auth.termo_authorization_state || auth.authorization_state) }}
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                Ambiente
              </dt>
              <dd class="font-medium">
                {{ auth.environment }}
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                Autor (mascarado)
              </dt>
              <dd class="font-medium">
                {{ auth.author_identity_masked || '—' }}
                <span
                  v-if="auth.author_name"
                  class="text-muted"
                > · {{ auth.author_name }}</span>
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                Termo
              </dt>
              <dd class="font-medium">
                {{ auth.has_termo ? 'Presente' : 'Ausente' }}
                <span
                  v-if="auth.termo_sha256"
                  class="block truncate font-mono text-xs text-muted"
                >SHA-256 {{ auth.termo_sha256 }}</span>
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                A1 gerenciado
              </dt>
              <dd class="font-medium">
                {{ auth.has_managed_a1 ? 'Configurado' : 'Não' }}
                <!-- Sem botão de download/recuperação de PFX -->
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                Token procurador
              </dt>
              <dd class="font-medium">
                {{ auth.has_procurador_token ? 'Válido' : 'Ausente/expirado' }}
                <span
                  v-if="auth.procurador_token_expires_at"
                  class="block text-xs text-muted"
                >
                  Expira {{ formatDateTime(auth.procurador_token_expires_at) }}
                </span>
              </dd>
            </div>
            <div
              v-if="auth.last_validation_message"
              class="sm:col-span-2"
            >
              <dt class="text-muted">
                Última validação
              </dt>
              <dd>{{ auth.last_validation_result }} — {{ auth.last_validation_message }}</dd>
            </div>
          </dl>
        </UPageCard>

        <UPageCard
          variant="subtle"
          title="Saúde da plataforma (sanitizada)"
          description="Sem Consumer Secret, mTLS material ou e-CNPJ contratante completo desnecessário."
        >
          <div
            v-if="health"
            class="space-y-2 text-sm"
          >
            <p>
              Status: <strong>{{ health.status ?? (health.healthy ? 'OK' : 'Degradado') }}</strong>
            </p>
            <p
              v-if="health.message"
              class="text-muted"
            >
              {{ health.message }}
            </p>
            <p
              v-if="strategy"
              class="text-xs text-muted"
            >
              Estratégia de representação do Termo: {{ strategy }}
            </p>
          </div>
          <p
            v-else
            class="text-sm text-muted"
          >
            Saúde não disponível neste ambiente.
          </p>
        </UPageCard>
      </div>

      <UPageCard
        variant="subtle"
        title="Configurar Autor do Pedido"
        description="Defina quem assina o pedido e como o certificado será utilizado."
      >
        <UForm
          id="author-form"
          :schema="authorSchema"
          :state="authorForm"
          class="space-y-6"
          @submit="saveAuthor"
        >
          <div class="grid gap-4 md:grid-cols-2">
            <UFormField
              name="authorType"
              label="Tipo de identidade"
              required
            >
              <USelect
                v-model="authorForm.authorType"
                :items="[
                  { label: 'CNPJ', value: 'CNPJ' },
                  { label: 'CPF', value: 'CPF' }
                ]"
                class="w-full"
                value-key="value"
              />
            </UFormField>
            <UFormField
              name="authorIdentity"
              label="Identidade (sem máscara)"
              description="Aceita CPF ou CNPJ, conforme o tipo selecionado."
              required
            >
              <UInput
                v-model="authorForm.authorIdentity"
                autocomplete="off"
                placeholder="Somente dígitos/alfanumérico"
                class="w-full"
              />
            </UFormField>
            <UFormField
              name="authorName"
              label="Nome"
            >
              <UInput
                v-model="authorForm.authorName"
                autocomplete="organization"
                placeholder="Nome do responsável ou da empresa"
                class="w-full"
              />
            </UFormField>
            <UFormField
              name="certificateMode"
              label="Modo de certificado"
              required
            >
              <USelect
                v-model="authorForm.certificateMode"
                :items="[
                  { label: 'Assinatura externa', value: 'EXTERNAL_SIGNATURE' },
                  { label: 'A1 gerenciado no cofre', value: 'MANAGED_A1' }
                ]"
                class="w-full"
                value-key="value"
              />
            </UFormField>
          </div>

          <div class="flex justify-end border-t border-default pt-4">
            <UButton
              type="submit"
              icon="i-lucide-save"
              label="Salvar Autor"
              :loading="saving"
              class="w-full justify-center sm:w-auto"
            />
          </div>
        </UForm>
      </UPageCard>

      <div class="grid items-start gap-4 xl:grid-cols-2">
        <UPageCard
          variant="subtle"
          title="Enviar Termo de Autorização"
          description="O XML assinado é armazenado no cofre. Não há download ou recuperação posterior na UI."
        >
          <div class="space-y-4">
            <UFileUpload
              v-model="termoFile"
              accept=".xml,text/xml,application/xml"
              label="Selecione ou arraste o Termo XML"
              description="Somente arquivo XML assinado."
              class="w-full"
            />
            <div class="flex justify-end">
              <UButton
                icon="i-lucide-upload"
                label="Enviar Termo"
                :loading="saving"
                :disabled="!termoFile"
                class="w-full justify-center sm:w-auto"
                @click="uploadTermo"
              />
            </div>
          </div>
        </UPageCard>

        <UPageCard
          v-if="authorForm.certificateMode === 'MANAGED_A1' || auth.certificate_mode === 'MANAGED_A1'"
          variant="subtle"
          title="A1 do Autor (cofre)"
          description="Upload único. Senha e PFX não são reexibidos nem recuperáveis."
        >
          <div class="space-y-4">
            <UFileUpload
              v-model="a1File"
              accept=".pfx,.p12,application/x-pkcs12"
              label="Selecione ou arraste o certificado A1"
              description="Arquivo PFX ou P12."
              color="warning"
              class="w-full"
            />
            <UFormField
              label="Senha do PFX"
              required
            >
              <UInput
                v-model="a1Password"
                type="password"
                autocomplete="new-password"
                class="w-full"
              />
            </UFormField>
            <UCheckbox
              v-model="a1Consent"
              label="Consentimento: autorizo o armazenamento cifrado do A1 do Autor neste escritório."
            />
            <div class="flex justify-end">
              <UButton
                label="Armazenar A1"
                icon="i-lucide-shield-check"
                color="warning"
                :loading="saving"
                :disabled="!a1File || !a1Password || !a1Consent"
                class="w-full justify-center sm:w-auto"
                @click="uploadA1"
              />
            </div>
          </div>
        </UPageCard>

        <UPageCard
          variant="subtle"
          title="Token do procurador"
          description="Renove a autorização usada nas consultas sem expor o valor do token."
          :class="authorForm.certificateMode === 'MANAGED_A1' || auth.certificate_mode === 'MANAGED_A1' ? 'xl:col-span-2' : undefined"
        >
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-muted">
              O valor do token nunca é exibido na interface.
            </p>
            <UButton
              label="Renovar token"
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="soft"
              :loading="saving"
              class="w-full justify-center sm:w-auto"
              @click="refreshToken"
            />
          </div>
        </UPageCard>
      </div>
    </template>
  </div>
</template>
