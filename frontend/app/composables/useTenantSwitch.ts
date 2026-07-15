/**
 * Troca explícita de escritório entre memberships autorizadas (15.2).
 * Invalida stores/queries tenant-scoped via sessionEpoch + refreshIdentity.
 */
import type { OfficeMembership } from '~/types/api'

export function useTenantSwitch() {
  const api = useApi()
  const toast = useToast()
  const { refreshIdentity } = useSanctumAuth()
  const { sessionEpoch, bumpSessionEpoch } = useDashboard()

  const memberships = ref<OfficeMembership[]>([])
  const currentOfficeId = ref<number | null>(null)
  const loading = ref(false)
  const switching = ref(false)
  const loadError = ref<string | null>(null)

  async function loadMemberships() {
    loading.value = true
    try {
      const res = await api.tenants.memberships()
      memberships.value = res.data.memberships || []
      currentOfficeId.value = res.data.current_office_id
      loadError.value = null
    } catch (caught) {
      loadError.value = apiErrorMessage(caught, 'Não foi possível carregar os escritórios.')
      memberships.value = []
    } finally {
      loading.value = false
    }
  }

  /**
   * Confirma troca: POST /tenants/switch → refresh me → bump epoch → reload rota.
   */
  async function switchTo(officeId: number): Promise<boolean> {
    if (switching.value) return false
    if (officeId === currentOfficeId.value) return true

    const target = memberships.value.find(m => m.office_id === officeId)
    if (!target) {
      toast.add({
        title: 'Escritório não autorizado',
        description: 'Só é possível trocar entre memberships ativas.',
        color: 'error'
      })
      return false
    }

    switching.value = true
    try {
      await api.tenants.switch(officeId)
      await refreshIdentity()
      bumpSessionEpoch()
      currentOfficeId.value = officeId
      memberships.value = memberships.value.map(m => ({
        ...m,
        is_current: m.office_id === officeId
      }))
      toast.add({
        title: 'Escritório alterado',
        description: target.office_name || `Escritório #${officeId}`,
        color: 'success'
      })
      // Recarrega a rota atual sem misturar dados do tenant anterior.
      const path = useRoute().fullPath
      if (import.meta.client) {
        await navigateTo(path, { replace: true, external: false })
        // Força remount de páginas que leem dados tenant-scoped.
        window.location.assign(path)
      }
      return true
    } catch (caught) {
      toast.add({
        title: apiErrorMessage(caught, 'Falha ao trocar de escritório.'),
        color: 'error'
      })
      return false
    } finally {
      switching.value = false
    }
  }

  return {
    memberships,
    currentOfficeId,
    loading,
    switching,
    loadError,
    loadMemberships,
    switchTo,
    sessionEpoch
  }
}
