<script setup lang="ts">
/**
 * Wizard de criação de Office pendente.
 * UStepper: Escritório → Plano → 1º admin → Entrega → Revisão.
 */
import type { StepperItem } from '@nuxt/ui'
import type {
  ActivationMethod,
  CreatePlatformOfficeResult,
  SubscriptionPlanCode
} from '~/types/api'
import { apiErrorMessage } from '~/utils/api-error'
import { normalizeCnpj } from '~/utils/format'

const api = useApi()
const toast = useToast()
const router = useRouter()

const step = ref(0)
const submitting = ref(false)
const reconfirmPassword = ref('')
const formError = ref('')
const result = ref<CreatePlatformOfficeResult | null>(null)
/** Chave estável para retries do mesmo envio. */
const idempotencyKey = ref(
  typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `office-${Date.now()}-${Math.random().toString(36).slice(2)}`
)

const form = reactive({
  name: '',
  cnpj: '',
  legal_name: '',
  institutional_email: '',
  institutional_phone: '',
  plan: 'STARTER' as SubscriptionPlanCode,
  admin_name: '',
  admin_email: '',
  method: 'MANUAL_LINK' as ActivationMethod
})

const steps = computed<StepperItem[]>(() => [
  {
    title: 'Escritório',
    description: 'Identidade',
    icon: 'i-lucide-building-2',
    value: 0
  },
  {
    title: 'Plano',
    description: 'Assinatura',
    icon: 'i-lucide-badge-check',
    value: 1
  },
  {
    title: 'Primeiro administrador',
    description: 'Conta inicial',
    icon: 'i-lucide-user-cog',
    value: 2
  },
  {
    title: 'Entrega',
    description: 'Método',
    icon: 'i-lucide-key-round',
    value: 3
  },
  {
    title: 'Revisão',
    description: 'Confirmar',
    icon: 'i-lucide-check-check',
    value: 4
  }
])

const planItems = [
  { label: 'Starter (5 usuários)', value: 'STARTER' as SubscriptionPlanCode },
  { label: 'Professional (25 usuários)', value: 'PROFESSIONAL' as SubscriptionPlanCode },
  { label: 'Enterprise (200 usuários)', value: 'ENTERPRISE' as SubscriptionPlanCode }
]

const methodItems = [
  { label: 'Link manual (7 dias)', value: 'MANUAL_LINK' as ActivationMethod },
  { label: 'Senha provisória (7 dias)', value: 'TEMPORARY_PASSWORD' as ActivationMethod }
]

const planLabel = computed(() =>
  planItems.find(p => p.value === form.plan)?.label || form.plan
)
const methodLabel = computed(() =>
  methodItems.find(m => m.value === form.method)?.label || form.method
)

const secretDelivered = computed(() =>
  result.value?.credential_delivery === 'delivered'
  && Boolean(result.value.activation_url || result.value.temporary_password)
)

function validateStep(index: number): string | null {
  if (index === 0) {
    if (!form.name.trim()) return 'Informe o nome do escritório.'
    if (!form.cnpj.trim()) return 'Informe o CNPJ.'
    if (!form.legal_name.trim()) return 'Informe a razão social.'
    if (!form.institutional_email.trim()) return 'Informe o e-mail institucional.'
    if (!form.institutional_phone.trim()) return 'Informe o telefone institucional.'
  }
  if (index === 1 && !form.plan) return 'Selecione o plano.'
  if (index === 2) {
    if (!form.admin_name.trim()) return 'Informe o nome do administrador.'
    if (!form.admin_email.trim()) return 'Informe o e-mail do administrador.'
  }
  if (index === 3 && !form.method) return 'Selecione o método de entrega.'
  return null
}

function next() {
  formError.value = ''
  const err = validateStep(step.value)
  if (err) {
    formError.value = err
    return
  }
  if (step.value < 4) step.value += 1
}

function back() {
  formError.value = ''
  if (step.value > 0) step.value -= 1
}

async function submit() {
  formError.value = ''
  for (let i = 0; i <= 3; i++) {
    const err = validateStep(i)
    if (err) {
      formError.value = err
      step.value = i
      return
    }
  }
  if (!reconfirmPassword.value) {
    formError.value = 'Confirme sua senha para criar o escritório.'
    return
  }

  submitting.value = true
  try {
    await api.confirmPassword(reconfirmPassword.value)
    const res = await api.platform.offices.create({
      name: form.name.trim(),
      profile: {
        cnpj: normalizeCnpj(form.cnpj),
        legal_name: form.legal_name.trim(),
        institutional_email: form.institutional_email.trim(),
        institutional_phone: form.institutional_phone.trim()
      },
      plan: form.plan,
      admin_name: form.admin_name.trim(),
      admin_email: form.admin_email.trim(),
      method: form.method,
      idempotency_key: idempotencyKey.value
    })
    result.value = res.data
    reconfirmPassword.value = ''

    if (res.data.credential_delivery === 'regeneration_required') {
      toast.add({
        title: 'Escritório já criado. Regeneração necessária.',
        color: 'warning'
      })
      const id = res.data.office?.id
      if (id) {
        await router.replace(`/admin/offices/${id}`)
        return
      }
    }

    toast.add({ title: 'Escritório criado.', color: 'success' })
  } catch (e) {
    formError.value = apiErrorMessage(e, 'Não foi possível criar o escritório.')
  } finally {
    submitting.value = false
  }
}

function goToDetail() {
  const id = result.value?.office?.id
  if (id) void router.push(`/admin/offices/${id}`)
  else void router.push('/admin/offices')
}

onBeforeUnmount(() => {
  result.value = null
})
</script>

<template>
  <UDashboardPanel
    id="admin-offices-new"
    data-testid="admin-offices-new"
    :ui="{ body: 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar
        title="Novo escritório"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            to="/admin/offices"
            color="neutral"
            variant="ghost"
            icon="i-lucide-arrow-left"
            label="Cancelar"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <DashboardContent width="comfortable" class="gap-4 sm:gap-6">
        <!-- Resultado único pós-criação -->
        <template v-if="result && secretDelivered">
          <UPageCard
            title="Escritório criado"
            :description="result.office?.name"
            variant="subtle"
            data-testid="admin-office-created"
          >
            <ActivationOneTimeSecret
              class="mt-2"
              :activation-url="result.activation_url"
              :temporary-password="result.temporary_password"
              :expires-at="result.expires_at"
              :method="result.method"
            />
            <div class="mt-4 flex flex-wrap gap-2">
              <UButton
                label="Abrir detalhe"
                icon="i-lucide-building-2"
                @click="goToDetail"
              />
              <UButton
                to="/admin/offices"
                color="neutral"
                variant="outline"
                label="Voltar à lista"
              />
            </div>
          </UPageCard>
        </template>

        <template v-else>
          <UStepper
            v-model="step"
            :items="steps"
            class="w-full"
            color="primary"
            size="sm"
            data-testid="admin-office-stepper"
          />

          <UAlert
            v-if="formError"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            :title="formError"
            :close="{ onClick: () => { formError = '' } }"
            data-testid="admin-office-wizard-error"
          />

          <UPageCard
            variant="subtle"
            :ui="{ container: 'sm:p-6 space-y-4' }"
          >
            <!-- 0 Escritório -->
            <div
              v-if="step === 0"
              class="space-y-4"
              data-testid="wizard-step-office"
            >
              <UFormField
                label="Nome de exibição"
                required
              >
                <UInput
                  v-model="form.name"
                  class="w-full"
                  data-testid="wizard-office-name"
                />
              </UFormField>
              <UFormField
                label="CNPJ"
                required
              >
                <UInput
                  v-model="form.cnpj"
                  class="w-full"
                  data-testid="wizard-office-cnpj"
                />
              </UFormField>
              <UFormField
                label="Razão social"
                required
              >
                <UInput
                  v-model="form.legal_name"
                  class="w-full"
                  data-testid="wizard-office-legal-name"
                />
              </UFormField>
              <UFormField
                label="E-mail institucional"
                required
              >
                <UInput
                  v-model="form.institutional_email"
                  type="email"
                  class="w-full"
                  data-testid="wizard-office-email"
                />
              </UFormField>
              <UFormField
                label="Telefone institucional"
                required
              >
                <UInput
                  v-model="form.institutional_phone"
                  class="w-full"
                  data-testid="wizard-office-phone"
                />
              </UFormField>
            </div>

            <!-- 1 Plano -->
            <div
              v-else-if="step === 1"
              class="space-y-4"
              data-testid="wizard-step-plan"
            >
              <UFormField
                label="Plano"
                required
              >
                <USelect
                  v-model="form.plan"
                  :items="planItems"
                  value-key="value"
                  label-key="label"
                  class="w-full"
                  data-testid="wizard-office-plan"
                />
              </UFormField>
            </div>

            <!-- 2 Admin -->
            <div
              v-else-if="step === 2"
              class="space-y-4"
              data-testid="wizard-step-admin"
            >
              <UFormField
                label="Nome"
                required
              >
                <UInput
                  v-model="form.admin_name"
                  class="w-full"
                  data-testid="wizard-admin-name"
                />
              </UFormField>
              <UFormField
                label="E-mail"
                required
              >
                <UInput
                  v-model="form.admin_email"
                  type="email"
                  class="w-full"
                  data-testid="wizard-admin-email"
                />
              </UFormField>
            </div>

            <!-- 3 Entrega -->
            <div
              v-else-if="step === 3"
              class="space-y-4"
              data-testid="wizard-step-delivery"
            >
              <UFormField
                label="Método de entrega"
                required
              >
                <USelect
                  v-model="form.method"
                  :items="methodItems"
                  value-key="value"
                  label-key="label"
                  class="w-full"
                  data-testid="wizard-delivery-method"
                />
              </UFormField>
              <p class="text-xs text-muted">
                O segredo será exibido uma vez para cópia manual. Não há envio por e-mail.
              </p>
            </div>

            <!-- 4 Revisão -->
            <div
              v-else
              class="space-y-4"
              data-testid="wizard-step-review"
            >
              <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt class="text-muted">
                    Escritório
                  </dt>
                  <dd class="text-highlighted">
                    {{ form.name }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    CNPJ
                  </dt>
                  <dd class="text-highlighted">
                    {{ form.cnpj }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Razão social
                  </dt>
                  <dd class="text-highlighted">
                    {{ form.legal_name }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Plano
                  </dt>
                  <dd class="text-highlighted">
                    {{ planLabel }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    1º admin
                  </dt>
                  <dd class="text-highlighted">
                    {{ form.admin_name }} · {{ form.admin_email }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Entrega
                  </dt>
                  <dd class="text-highlighted">
                    {{ methodLabel }}
                  </dd>
                </div>
              </dl>
              <UFormField
                label="Sua senha"
                required
                description="Confirmação para criar o escritório."
              >
                <UInput
                  v-model="reconfirmPassword"
                  type="password"
                  autocomplete="current-password"
                  class="w-full"
                  data-testid="wizard-reconfirm"
                />
              </UFormField>
            </div>

            <div class="flex flex-wrap justify-between gap-2 pt-2">
              <UButton
                v-if="step > 0"
                label="Voltar"
                color="neutral"
                variant="ghost"
                icon="i-lucide-arrow-left"
                :disabled="submitting"
                @click="back"
              />
              <div
                v-else
                class="flex-1"
              />
              <UButton
                v-if="step < 4"
                label="Continuar"
                color="primary"
                trailing-icon="i-lucide-arrow-right"
                data-testid="wizard-next"
                @click="next"
              />
              <UButton
                v-else
                label="Criar escritório"
                color="primary"
                icon="i-lucide-check"
                :loading="submitting"
                data-testid="wizard-submit"
                @click="() => { void submit() }"
              />
            </div>
          </UPageCard>
        </template>
      </DashboardContent>
    </template>
  </UDashboardPanel>
</template>
