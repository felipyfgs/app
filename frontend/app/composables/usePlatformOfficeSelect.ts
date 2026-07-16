/**
 * Seletor global de escritórios para PLATFORM_ADMIN.
 * Sessão privilegiada separada — não cria membership nem altera selected_office_id.
 */
import type { PlatformOfficeSummary } from '~/types/api'
import { isPlatformAdmin } from '~/utils/permissions'

export function usePlatformOfficeSelect() {
  const api = useApi()
  const toast = useToast()
  const { refreshIdentity } = useSanctumAuth()
  const { me, bumpSessionEpoch, sessionEpoch } = useDashboard()

  const offices = ref<PlatformOfficeSummary[]>([])
  const loading = ref(false)
  const switching = ref(false)
  const loadError = ref<string | null>(null)
  const q = ref('')

  const enabled = computed(() => isPlatformAdmin(me.value))
  const currentOfficeId = computed(() => me.value?.office?.id ?? null)
  const privileged = computed(() => me.value?.access_mode === 'platform_privileged')

  async function loadOffices(params?: { q?: string }) {
    if (!enabled.value) {
      offices.value = []
      return
    }
    loading.value = true
    loadError.value = null
    try {
      const res = await api.platform.offices.list({
        per_page: 100,
        q: params?.q ?? (q.value || undefined)
      })
      offices.value = res.data || []
    } catch (caught) {
      // Fallback: lista de tenants legada se /platform/offices ainda não existir.
      try {
        const legacy = await api.platform.tenants.list({ per_page: 100 })
        offices.value = (legacy.data || []).map((row) => {
          const r = row as Record<string, unknown>
          return {
            id: Number(r.id ?? r.office_id ?? 0),
            name: String(r.name ?? r.office_name ?? `Escritório #${r.id ?? '?'}`),
            slug: String(r.slug ?? r.office_slug ?? ''),
            is_active: r.is_active !== false,
            plan: r.plan != null ? String(r.plan) : null
          } satisfies PlatformOfficeSummary
        }).filter(o => o.id > 0)
      } catch {
        offices.value = []
        loadError.value = apiErrorMessage(caught, 'Não foi possível listar escritórios da plataforma.')
      }
    } finally {
      loading.value = false
    }
  }

  async function selectOffice(officeId: number): Promise<boolean> {
    if (!enabled.value || switching.value) return false
    if (officeId === currentOfficeId.value && privileged.value) return true

    switching.value = true
    try {
      await api.platform.offices.select(officeId)
      await refreshIdentity()
      bumpSessionEpoch()
      const label = offices.value.find(o => o.id === officeId)?.name
      toast.add({
        title: 'Contexto privilegiado',
        description: label || `Escritório #${officeId}`,
        color: 'warning'
      })
      if (import.meta.client) {
        const path = useRoute().fullPath
        window.location.assign(path)
      }
      return true
    } catch (caught) {
      toast.add({
        title: apiErrorMessage(caught, 'Falha ao selecionar escritório (contexto privilegiado).'),
        color: 'error'
      })
      return false
    } finally {
      switching.value = false
    }
  }

  async function clearSelection(): Promise<boolean> {
    if (!enabled.value || switching.value) return false
    switching.value = true
    try {
      await api.platform.offices.clear()
      await refreshIdentity()
      bumpSessionEpoch()
      toast.add({
        title: 'Contexto privilegiado encerrado',
        color: 'neutral'
      })
      if (import.meta.client) {
        window.location.assign('/admin')
      }
      return true
    } catch (caught) {
      toast.add({
        title: apiErrorMessage(caught, 'Falha ao limpar seleção privilegiada.'),
        color: 'error'
      })
      return false
    } finally {
      switching.value = false
    }
  }

  return {
    offices,
    loading,
    switching,
    loadError,
    q,
    enabled,
    currentOfficeId,
    privileged,
    loadOffices,
    selectOffice,
    clearSelection,
    sessionEpoch
  }
}
