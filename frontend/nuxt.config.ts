// https://nuxt.com/docs/api/configuration/nuxt-config
const useSanctumProxy = process.env.NUXT_SANCTUM_PROXY === 'true'
const apiBase = useSanctumProxy ? '/api/sanctum' : (process.env.NUXT_PUBLIC_API_BASE || '')
// Compose define CHOKIDAR_USEPOLLING=true; em bind-mount Docker o inotify do host
// frequentemente não chega no container — polling é necessário para HMR.
const usePolling = process.env.CHOKIDAR_USEPOLLING !== 'false'

export default defineNuxtConfig({

  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@vueuse/nuxt',
    'nuxt-auth-sanctum'
  ],
  ssr: false,

  devtools: {
    enabled: process.env.NUXT_DEVTOOLS === 'true'
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      apiBase
    }
  },

  // Watcher do Nuxt (rotas, plugins, reinício de config)
  watchers: {
    chokidar: {
      usePolling,
      interval: 300
    }
  },

  compatibilityDate: '2026-06-30',

  nitro: {
    preset: 'static'
  },

  vite: {
    server: {
      watch: {
        usePolling,
        interval: 300
      },
      hmr: {
        protocol: 'ws',
        host: 'localhost',
        clientPort: Number(process.env.FRONTEND_DEV_PORT || 3000)
      }
    }
  },

  // Watcher do Vite (HMR de .vue/.ts/.css) + WebSocket no host.
  // Hook garante merge: só `vite.server.watch` às vezes é sobrescrito pelo Nuxt.
  hooks: {
    'vite:extendConfig'(config) {
      config.server ||= {}
      config.server.watch = {
        ...(config.server.watch || {}),
        usePolling,
        interval: 300
      }
      config.server.hmr = {
        ...(typeof config.server.hmr === 'object' ? config.server.hmr : {}),
        protocol: 'ws',
        host: 'localhost',
        clientPort: Number(process.env.FRONTEND_DEV_PORT || 3000)
      }
    }
  },

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  },

  sanctum: {
    baseUrl: apiBase,
    mode: 'cookie',
    endpoints: {
      csrf: '/sanctum/csrf-cookie',
      login: '/login',
      logout: '/logout',
      user: '/api/v1/me'
    },
    redirect: {
      onLogin: false,
      onLogout: '/login',
      onAuthOnly: '/login',
      onGuestOnly: '/'
    },
    globalMiddleware: {
      enabled: false
    },
    serverProxy: {
      enabled: useSanctumProxy,
      route: '/api/sanctum',
      baseUrl: process.env.NUXT_SANCTUM_PROXY_BASE || 'http://localhost:8080'
    }
  }
})
