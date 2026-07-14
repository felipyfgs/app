<script setup lang="ts">
import type { Client, ClientCredential, Establishment } from '~/types/api'
import {
  formatSourceDate,
  registrationSourceLabel,
  registrationStatusColor,
  registrationStatusIcon,
  registrationStatusLabel
} from '~/utils/registrationLabels'

const props = defineProps<{
  client: Client
  credential: ClientCredential | null
  establishments: Establishment[]
  triggeredIds: number[]
  canManageCredentials: boolean
  canManageClients?: boolean
  canTriggerSync?: boolean
  /** Quando true, seções mudam via evento (modal) em vez de rotas. */
  inModal?: boolean
  /**
   * Só o wizard de setup (sem identidade/atalhos duplicados).
   * Use na página de detalhe que já tem header + aside.
   */
  wizardOnly?: boolean
}>()

const emit = defineEmits<{
  navigateSection: [section: 'resumo' | 'cadastro' | 'certificado' | 'sincronizacao']
  /** Notifica o pai que a raiz foi atualizada (ex.: recarregar detalhe) */
  updated: []
}>()

const route = useRoute()
const formOpen = ref(false)

type SectionKey = 'resumo' | 'cadastro' | 'certificado' | 'sincronizacao'

function sectionTo(section: SectionKey) {
  const id = props.client.id || Number(route.params.id)
  return clientSectionPath(id, section)
}

function goSection(section: SectionKey, event?: Event) {
  if (props.inModal) {
    event?.preventDefault()
    emit('navigateSection', section)
  }
}

const hasEstablishments = computed(() => props.establishments.length > 0)
/** Detalhe completo (ADMIN) ou summary do show (OPERATOR/VIEWER). */
const hasCredential = computed(() => !!props.credential || !!props.client.credential_summary)
const hasTriggeredSync = computed(() => props.triggeredIds.length > 0)

/**
 * Onboarding só na "primeira vez": some com A1 ativo.
 * O CNPJ/estabelecimento já nasce com o cadastro do cliente (1:1).
 */
const showOnboarding = computed(() => !hasCredential.value)

const captureReadyCount = computed(() =>
  props.establishments.filter(e =>
    e.is_active
    && e.capture_enabled !== false
    && (e.capture_eligibility?.eligible !== false)
  ).length
)

const capturePausedCount = computed(() =>
  props.establishments.filter(e => e.capture_enabled === false).length
)

const steps = computed(() => {
  const list: Array<{
    key: string
    title: string
    description: string
    complete: boolean
    informational: boolean
    section: SectionKey
    to: string
    icon: string
  }> = [{
    key: 'cliente',
    title: 'Cliente',
    description: hasEstablishments.value
      ? 'CNPJ cadastrado'
      : 'Cadastro da empresa',
    complete: true,
    informational: false,
    section: 'cadastro',
    to: sectionTo('cadastro'),
    icon: 'i-lucide-building-2'
  }, {
    key: 'certificado',
    title: 'Certificado A1',
    description: props.canManageCredentials
      ? (hasCredential.value ? 'Validado e ativo' : 'Envie o PFX (matriz ou da filial)')
      : (hasCredential.value ? 'Ativo (gerenciado por ADMIN)' : 'Aguardando ADMIN ativar o A1'),
    complete: hasCredential.value,
    informational: !props.canManageCredentials && !hasCredential.value,
    section: 'certificado',
    to: sectionTo('certificado'),
    icon: 'i-lucide-badge-check'
  }, {
    key: 'sync',
    title: 'Primeira sincronização',
    description: hasTriggeredSync.value
      ? 'Solicitada nesta sessão'
      : (hasCredential.value
          ? 'Dispare na seção Sincronização'
          : 'Disponível após ativar o A1'),
    complete: hasTriggeredSync.value,
    informational: false,
    section: 'sincronizacao',
    to: sectionTo('sincronizacao'),
    icon: 'i-lucide-refresh-cw'
  }]

  return list
})

const completedSteps = computed(() => steps.value.filter(s => s.complete).length)
const nextStep = computed(() => steps.value.find(s => !s.complete && !s.informational) || null)

const displayName = computed(() =>
  props.client.display_name || props.client.legal_name || props.client.name
)
</script>

<template>
  <div class="space-y-4 sm:space-y-6" data-testid="client-resumo">
    <!-- Só na primeira vez: some com estabelecimento + A1 cadastrados -->
    <template v-if="showOnboarding">
      <UPageCard
        title="Onboarding"
        description="Conclua as etapas para ativar a captura ADN deste cliente."
        variant="naked"
        orientation="horizontal"
        class="mb-4"
      >
        <UBadge color="primary" variant="subtle" class="w-fit lg:ms-auto">
          {{ completedSteps }}/{{ steps.length }} etapas
        </UBadge>
      </UPageCard>

      <UPageCard variant="subtle">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <button
            v-for="(step, index) in steps"
            :key="step.key"
            type="button"
            class="flex gap-3 rounded-lg p-2 -m-2 text-left transition-colors hover:bg-elevated/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            @click="inModal ? goSection(step.section) : navigateTo(step.to)"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold ring ring-inset"
              :class="step.complete
                ? (step.informational
                  ? 'bg-primary/10 text-primary ring-primary/25'
                  : 'bg-success/10 text-success ring-success/25')
                : 'bg-elevated text-muted ring-default'"
            >
              <UIcon
                v-if="step.complete && step.informational"
                name="i-lucide-info"
                class="size-4"
                aria-hidden="true"
              />
              <UIcon
                v-else-if="step.complete"
                name="i-lucide-check"
                class="size-4"
                aria-hidden="true"
              />
              <span v-else>{{ index + 1 }}</span>
            </div>
            <div class="min-w-0">
              <p class="font-medium text-highlighted">
                {{ step.title }}
              </p>
              <p class="text-xs text-muted">
                {{ step.description }}
              </p>
            </div>
            <UIcon
              name="i-lucide-chevron-right"
              class="ms-auto size-4 shrink-0 self-center text-muted"
              aria-hidden="true"
            />
          </button>
        </div>

        <UAlert
          v-if="nextStep"
          class="mt-4"
          color="primary"
          variant="subtle"
          :icon="nextStep.icon"
          :title="`Próximo passo: ${nextStep.title}`"
          :description="nextStep.description"
        >
          <template #actions>
            <UButton
              label="Continuar"
              color="primary"
              variant="soft"
              size="sm"
              :to="inModal ? undefined : nextStep.to"
              @click="inModal ? goSection(nextStep.section) : undefined"
            />
          </template>
        </UAlert>
      </UPageCard>
    </template>

    <!-- Identidade / painéis (omitidos na página completa com header próprio) -->
    <template v-if="!wizardOnly">
    <UPageCard
      title="Identidade da raiz"
      description="Dados do Cliente (CNPJ raiz) e estado no escritório."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
      :class="showOnboarding ? 'mt-2' : undefined"
    >
      <div v-if="canManageClients" class="flex w-fit flex-wrap gap-2 lg:ms-auto">
        <UButton
          label="Editar cadastro"
          color="primary"
          variant="soft"
          icon="i-lucide-pencil"
          size="sm"
          data-testid="client-onboarding-edit"
          @click="() => { formOpen = true }"
        />
      </div>
    </UPageCard>

    <UPageCard variant="subtle">
      <dl class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <dt class="text-sm text-muted">
            Razão social
          </dt>
          <dd class="text-highlighted font-medium">
            {{ client.legal_name || client.name }}
          </dd>
        </div>
        <div v-if="client.display_name">
          <dt class="text-sm text-muted">
            Nome interno
          </dt>
          <dd class="text-highlighted">
            {{ client.display_name }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            Raiz CNPJ
          </dt>
          <dd class="font-mono text-highlighted">
            {{ client.root_cnpj }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            Estado no escritório
          </dt>
          <dd>
            <UBadge
              :color="client.is_active ? 'success' : 'neutral'"
              variant="subtle"
              :icon="client.is_active ? 'i-lucide-check' : 'i-lucide-minus'"
            >
              {{ client.is_active ? 'Ativo' : 'Inativo' }}
            </UBadge>
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            Fonte cadastral
          </dt>
          <dd class="flex flex-wrap items-center gap-2">
            <UBadge color="neutral" variant="subtle" icon="i-lucide-database">
              {{ registrationSourceLabel(client.registration_source) }}
            </UBadge>
            <span class="text-xs text-muted">
              {{ formatSourceDate(client.registration_refreshed_at) }}
              <template v-if="client.registration_source === 'CNPJ_WS'">
                · pode estar defasado
              </template>
            </span>
          </dd>
        </div>
        <div v-if="client.legal_nature_name || client.company_size_name" class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
          <div v-if="client.legal_nature_name">
            <dt class="text-sm text-muted">
              Natureza jurídica
            </dt>
            <dd class="text-highlighted text-sm">
              <span v-if="client.legal_nature_code" class="font-mono text-muted">{{ client.legal_nature_code }} · </span>
              {{ client.legal_nature_name }}
            </dd>
          </div>
          <div v-if="client.company_size_name">
            <dt class="text-sm text-muted">
              Porte
            </dt>
            <dd class="text-highlighted text-sm">
              <span v-if="client.company_size_code" class="font-mono text-muted">{{ client.company_size_code }} · </span>
              {{ client.company_size_name }}
            </dd>
          </div>
        </div>
        <div v-if="client.notes" class="sm:col-span-2">
          <dt class="text-sm text-muted">
            Observações
          </dt>
          <dd class="text-highlighted text-sm whitespace-pre-wrap">
            {{ client.notes }}
          </dd>
        </div>
        <div v-if="!client.is_active && client.inactive_reason" class="sm:col-span-2">
          <dt class="text-sm text-muted">
            Motivo de inativação
          </dt>
          <dd class="text-highlighted text-sm">
            {{ client.inactive_reason }}
          </dd>
        </div>
      </dl>
    </UPageCard>

    <!-- CNPJ deste cliente (1:1) e captura -->
    <UPageCard
      title="CNPJ e captura"
      description="Cada cliente é um CNPJ. Filiais = novo cadastro de cliente."
      variant="naked"
      class="mb-4 mt-2"
    />

    <UPageCard variant="subtle">
      <div v-if="!establishments.length">
        <UEmpty
          icon="i-lucide-building-2"
          title="CNPJ não encontrado"
          description="O CNPJ nasce com o cadastro do cliente. Se faltar, edite ou recadastre."
        />
      </div>

      <template v-else>
        <div
          v-for="est in establishments.slice(0, 1)"
          :key="est.id"
          class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium text-highlighted">
                {{ est.trade_name || est.cnpj }}
              </span>
              <UBadge v-if="est.is_matrix" color="primary" variant="subtle" size="sm">
                Matriz
              </UBadge>
              <UBadge
                :color="est.capture_enabled !== false ? 'success' : 'warning'"
                variant="subtle"
                size="sm"
                :icon="est.capture_enabled !== false ? 'i-lucide-radio' : 'i-lucide-radio-off'"
              >
                {{ est.capture_enabled !== false ? 'Captura on' : 'Captura off' }}
              </UBadge>
            </div>
            <p class="font-mono text-sm text-muted">
              {{ est.cnpj }}
            </p>
            <p
              v-if="est.capture_eligibility && !est.capture_eligibility.eligible"
              class="mt-1 text-xs text-warning"
            >
              {{ est.capture_eligibility.reasons[0] || 'Inelegível para captura.' }}
            </p>
          </div>
          <UButton
            v-if="canManageClients"
            size="sm"
            color="neutral"
            variant="subtle"
            icon="i-lucide-user-plus"
            label="Cadastrar filial (novo cliente)"
            to="/clients?new=1"
          />
        </div>
      </template>
    </UPageCard>

    <!-- Certificado e sync (atalhos) -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-2">
      <UPageCard
        variant="subtle"
        class="hover:ring-primary/30 transition-shadow cursor-pointer"
        role="button"
        tabindex="0"
        @click="() => { if (inModal) goSection('certificado'); else void navigateTo(sectionTo('certificado')) }"
        @keydown.enter="() => { if (inModal) goSection('certificado'); else void navigateTo(sectionTo('certificado')) }"
      >
        <div class="flex items-start gap-3">
          <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 ring ring-inset ring-primary/25">
            <UIcon name="i-lucide-badge-check" class="size-5 text-primary" />
          </div>
          <div class="min-w-0">
            <p class="font-medium text-highlighted">
              Certificado A1
            </p>
            <p class="text-sm text-muted">
              <template v-if="!canManageCredentials">
                Gerenciado por ADMIN — sem formulário sensível neste perfil.
              </template>
              <template v-else-if="credential">
                Ativo até {{ formatDateTime(credential.valid_to) }}
              </template>
              <template v-else>
                Nenhum certificado ativo. Envie o PFX na seção dedicada.
              </template>
            </p>
          </div>
        </div>
      </UPageCard>

      <UPageCard
        variant="subtle"
        class="hover:ring-primary/30 transition-shadow cursor-pointer"
        role="button"
        tabindex="0"
        @click="() => { if (inModal) goSection('sincronizacao'); else void navigateTo(sectionTo('sincronizacao')) }"
        @keydown.enter="() => { if (inModal) goSection('sincronizacao'); else void navigateTo(sectionTo('sincronizacao')) }"
      >
        <div class="flex items-start gap-3">
          <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 ring ring-inset ring-primary/25">
            <UIcon name="i-lucide-refresh-cw" class="size-5 text-primary" />
          </div>
          <div class="min-w-0">
            <p class="font-medium text-highlighted">
              Sincronização ADN
            </p>
            <p class="text-sm text-muted">
              <template v-if="!establishments.length">
                CNPJ do cliente necessário para capturar.
              </template>
              <template v-else-if="canManageCredentials && !credential">
                Ative o A1 antes da primeira captura.
              </template>
              <template v-else-if="captureReadyCount === 0">
                CNPJ inelegível para captura no momento.
              </template>
              <template v-else>
                Pronto para captura. NSU não é editável.
              </template>
            </p>
          </div>
        </div>
      </UPageCard>
    </div>

    <p class="text-xs text-muted text-center sm:text-start">
      Exibindo {{ displayName }} · seções disponíveis na barra acima.
    </p>
    </template>

    <!-- Mesmo formulário de criar/editar (modal padronizado) -->
    <ClientsClientFormModal
      v-if="canManageClients"
      v-model:open="formOpen"
      :client="client"
      :can-manage-clients="canManageClients"
      :can-manage-credentials="false"
      @saved="() => { formOpen = false; emit('updated') }"
      @open-existing="(id) => navigateTo(clientSectionPath(id))"
    />
  </div>
</template>
