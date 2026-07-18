import type { MitListaApuracoes317Payload } from '~/types/fiscal-modules'

/**
 * Leitor local da lista MIT 317. A consulta oficial é sempre explícita no
 * backend; abrir esta interface nunca inicia job nem chama o SERPRO.
 */
export function useMitListaApuracoes() {
  const { fiscal } = useApi()

  async function fetchLocalList(
    clientId: number,
    params?: { year?: number }
  ): Promise<MitListaApuracoes317Payload> {
    return fiscal.mit.listaApuracoes(clientId, params)
  }

  return { fetchLocalList }
}
