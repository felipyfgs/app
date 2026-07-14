interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}

function payloadFrom(error: unknown): ApiErrorPayload | undefined {
  if (!error || typeof error !== 'object') {
    return undefined
  }

  const candidate = error as {
    data?: ApiErrorPayload
    response?: { _data?: ApiErrorPayload }
  }

  return candidate.data || candidate.response?._data
}

export function apiErrorMessage(error: unknown, fallback: string): string {
  const payload = payloadFrom(error)
  if (payload?.message && typeof payload.message === 'string') {
    return payload.message
  }
  return fallback
}

export function apiFieldErrors(error: unknown): Record<string, string[]> {
  return payloadFrom(error)?.errors || {}
}
