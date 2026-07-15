import { createSharedComposable } from '@vueuse/core'
import {
  canCreateExport as userCanCreateExport,
  canImportDocuments as userCanImportDocuments,
  canManageClients as userCanManageClients,
  canManageCredentials as userCanManageCredentials,
  canTriggerSync as userCanTriggerSync,
  hasConfirmedAdminAccess,
  unwrapMeUser
} from '~/utils/permissions'
import type { MeIdentity } from '~/utils/permissions'

const _useDashboard = () => {
  const route = useRoute()
  const router = useRouter()
  const { user, isAuthenticated } = useSanctumAuth()
  const isNotificationsSlideoverOpen = ref(false)
  const sessionEpoch = ref(0)

  const me = computed(() => unwrapMeUser(user.value as MeIdentity))

  const canManageClients = computed(() => userCanManageClients(me.value))
  const canManageCredentials = computed(() => userCanManageCredentials(me.value))
  const canTriggerSync = computed(() => userCanTriggerSync(me.value))
  const canCreateExport = computed(() => userCanCreateExport(me.value))
  const canImportDocuments = computed(() => userCanImportDocuments(me.value))
  const canAccessAdministration = computed(() => hasConfirmedAdminAccess(me.value))

  defineShortcuts({
    'g-h': () => router.push('/'),
    'g-c': () => router.push('/clients'),
    'g-n': () => router.push('/docs'),
    'g-d': () => router.push('/docs'),
    'g-e': () => router.push('/exports'),
    'g-f': () => router.push('/closing'),
    'g-s': () => router.push('/syncs'),
    'g-o': () => router.push('/health'),
    'g-a': () => {
      if (canAccessAdministration.value) {
        router.push('/admin')
      }
    },
    'n': () => {
      isNotificationsSlideoverOpen.value = !isNotificationsSlideoverOpen.value
    }
  })

  watch(() => route.fullPath, () => {
    isNotificationsSlideoverOpen.value = false
  })

  // Limpa alertas e estado de UI sensível quando a identidade muda ou a sessão termina.
  watch(
    () => [me.value?.id ?? null, isAuthenticated.value] as const,
    ([nextId, authenticated], [prevId, wasAuthenticated]) => {
      const identityChanged = prevId !== undefined && nextId !== prevId
      const loggedOut = wasAuthenticated && !authenticated

      if (identityChanged || loggedOut) {
        isNotificationsSlideoverOpen.value = false
        sessionEpoch.value += 1
      }
    }
  )

  return {
    canAccessAdministration,
    canCreateExport,
    canImportDocuments,
    canManageClients,
    canManageCredentials,
    canTriggerSync,
    isNotificationsSlideoverOpen,
    me,
    sessionEpoch
  }
}

export const useDashboard = createSharedComposable(_useDashboard)
