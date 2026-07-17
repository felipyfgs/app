import { fileURLToPath } from 'node:url'

export default defineNuxtConfig({
  ssr: false,
  alias: {
    '~': fileURLToPath(new URL('../../../app', import.meta.url)),
    '@': fileURLToPath(new URL('../../../app', import.meta.url))
  },
  compatibilityDate: '2026-06-30'
})
