import { createSharedComposable } from '@vueuse/core'
import {
  canAssociateCategories as userCanAssociateCategories,
  canCreateExport as userCanCreateExport,
  canExecuteHighRiskMutation as userCanExecuteHighRiskMutation,
  canImportDocuments as userCanImportDocuments,
  canManageClients as userCanManageClients,
  canManageCredentials as userCanManageCredentials,
  canTriageMailbox as userCanTriageMailbox,
  canTriggerSync as userCanTriggerSync,
  canAccessPlatformSerproConsole as userCanAccessPlatformSerproConsole,
  hasConfirmedAdminAccess,
  unwrapMeUser
} from '~/utils/permissions'
import type { MeIdentity } from '~/utils/permissions'

const _useDashboard = () => {
  const route = useRoute()
  const router = useRouter()
  const { user, isAuthenticated } = useSanctumAuth()
  const isNotificationsSlideoverOpen = ref(false)
  const isClientFormOpen = ref(false)
  const isExportFormOpen = ref(false)
  /** Incrementado a cada "Novo cliente" global — força modo create na lista. */
  const clientFormCreateNonce = ref(0)
  const sessionEpoch = ref(0)

  const me = computed(() => unwrapMeUser(user.value as MeIdentity))

  const canManageClients = computed(() => userCanManageClients(me.value))
  const canManageCredentials = computed(() => userCanManageCredentials(me.value))
  const canTriggerSync = computed(() => userCanTriggerSync(me.value))
  const canCreateExport = computed(() => userCanCreateExport(me.value))
  const canImportDocuments = computed(() => userCanImportDocuments(me.value))
  const canAccessAdministration = computed(() => hasConfirmedAdminAccess(me.value))
  const canAccessPlatformSerpro = computed(() => userCanAccessPlatformSerproConsole(me.value))
  const canAssociateCategories = computed(() => userCanAssociateCategories(me.value))
  const canTriageMailbox = computed(() => userCanTriageMailbox(me.value))
  const canExecuteHighRiskMutation = computed(() => userCanExecuteHighRiskMutation(me.value))

  async function openClientCreate() {
    if (!canManageClients.value) return
    if (route.path !== '/clients') {
      await router.push('/clients')
    }
    // Sempre modo create: zera cliente residual na lista via watch do nonce.
    clientFormCreateNonce.value += 1
    isClientFormOpen.value = true
  }

  async function openExportCreate() {
    if (!canCreateExport.value) return
    if (route.path !== '/exports') {
      await router.push('/exports')
    }
    isExportFormOpen.value = true
  }

  function bumpSessionEpoch() {
    sessionEpoch.value += 1
  }

  defineShortcuts({
    'g-h': () => router.push('/'),
    'g-c': () => router.push('/clients'),
    'g-n': () => router.push('/docs'),
    'g-d': () => router.push('/docs'),
    'g-e': () => router.push('/exports'),
    'g-f': () => router.push('/closing'),
    'g-s': () => router.push('/syncs'),
    'g-o': () => router.push('/health'),
    'g-m': () => router.push('/monitoring'),
    'g-w': () => router.push('/work'),
    'g-k': () => router.push('/work/calendar'),
    'g-u': () => router.push('/settings/usage'),
    'g-a': () => {
      if (canAccessAdministration.value) {
        void router.push('/admin')
      }
    },
    'n': () => {
      isNotificationsSlideoverOpen.value = !isNotificationsSlideoverOpen.value
    }
  })

  watch(() => route.fullPath, () => {
    isNotificationsSlideoverOpen.value = false
    if (route.path !== '/clients') {
      isClientFormOpen.value = false
    }
    if (route.path !== '/exports') {
      isExportFormOpen.value = false
    }
  })

  // Limpa alertas e estado de UI sensível quando a identidade muda ou a sessão termina.
  watch(
    () => [me.value?.id ?? null, isAuthenticated.value] as const,
    ([nextId, authenticated], [prevId, wasAuthenticated]) => {
      const identityChanged = prevId !== undefined && nextId !== prevId
      const loggedOut = wasAuthenticated && !authenticated

      if (identityChanged || loggedOut) {
        isNotificationsSlideoverOpen.value = false
        isClientFormOpen.value = false
        isExportFormOpen.value = false
        sessionEpoch.value += 1
      }
    }
  )

  return {
    bumpSessionEpoch,
    canAccessAdministration,
    canAccessPlatformSerpro,
    canAssociateCategories,
    canCreateExport,
    canExecuteHighRiskMutation,
    canImportDocuments,
    canManageClients,
    canManageCredentials,
    canTriageMailbox,
    canTriggerSync,
    clientFormCreateNonce,
    isClientFormOpen,
    isExportFormOpen,
    isNotificationsSlideoverOpen,
    me,
    openClientCreate,
    openExportCreate,
    sessionEpoch
  }
}

export const useDashboard = createSharedComposable(_useDashboard)
