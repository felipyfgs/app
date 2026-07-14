import { createSharedComposable } from '@vueuse/core'
import {
  canCreateExport as userCanCreateExport,
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
  const { user } = useSanctumAuth()
  const isNotificationsSlideoverOpen = ref(false)

  const me = computed(() => unwrapMeUser(user.value as MeIdentity))

  const canManageClients = computed(() => userCanManageClients(me.value))
  const canManageCredentials = computed(() => userCanManageCredentials(me.value))
  const canTriggerSync = computed(() => userCanTriggerSync(me.value))
  const canCreateExport = computed(() => userCanCreateExport(me.value))
  const canAccessAdministration = computed(() => hasConfirmedAdminAccess(me.value))

  defineShortcuts({
    'g-h': () => router.push('/'),
    'g-c': () => router.push('/clients'),
    'g-n': () => router.push('/notes'),
    'g-e': () => router.push('/exports'),
    'g-s': () => router.push('/syncs'),
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

  return {
    canAccessAdministration,
    canCreateExport,
    canManageClients,
    canManageCredentials,
    canTriggerSync,
    isNotificationsSlideoverOpen,
    me
  }
}

export const useDashboard = createSharedComposable(_useDashboard)
