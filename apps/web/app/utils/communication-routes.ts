/** Path canônico da lista de atendimento. */
export const COMMUNICATION_INDEX_PATH = '/communication'

/** Deep-link de conversa (estilo Chatwoot: …/conversations/{id}). */
export function communicationConversationPath(id: number): string {
  return `${COMMUNICATION_INDEX_PATH}/conversations/${id}`
}

export function parseCommunicationConversationId(param: unknown): number | null {
  const raw = Array.isArray(param) ? param[0] : param
  const id = Number(raw)
  return Number.isInteger(id) && id > 0 ? id : null
}

export function isCommunicationNavActive(path: string): boolean {
  return path === COMMUNICATION_INDEX_PATH || path.startsWith(`${COMMUNICATION_INDEX_PATH}/`)
}
