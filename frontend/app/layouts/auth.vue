<script setup lang="ts">
/**
 * Layout de autenticação — painel dividido no estilo das páginas de auth
 * do Nuxt UI SaaS / AuthForm docs (branding + cartão do formulário).
 */
const year = new Date().getFullYear()
</script>

<template>
  <div class="min-h-screen lg:grid lg:grid-cols-2 bg-default">
    <!-- Painel de marca (desktop) -->
    <aside
      class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-elevated/40 border-r border-default p-10 xl:p-14"
      aria-hidden="true"
    >
      <div class="absolute inset-0 bg-gradient-to-br from-primary/15 via-transparent to-primary/5 pointer-events-none" />
      <div class="absolute -right-24 -top-24 size-80 rounded-full bg-primary/10 blur-3xl pointer-events-none" />
      <div class="absolute -left-16 bottom-0 size-64 rounded-full bg-primary/10 blur-3xl pointer-events-none" />

      <div class="relative z-10">
        <div class="inline-flex items-center gap-2.5">
          <span class="flex size-10 items-center justify-center rounded-lg bg-primary/15 ring-1 ring-primary/25">
            <UIcon name="i-lucide-receipt" class="size-5 text-primary" />
          </span>
          <div>
            <p class="font-semibold text-highlighted leading-tight">
              NFS-e ADN
            </p>
            <p class="text-xs text-muted">
              Ambiente de Dados Nacional
            </p>
          </div>
        </div>
      </div>

      <div class="relative z-10 max-w-md space-y-6">
        <h2 class="text-3xl xl:text-4xl font-semibold text-highlighted text-pretty tracking-tight">
          Captura e organização de NFS-e para o escritório
        </h2>
        <p class="text-base text-muted text-pretty leading-relaxed">
          Painel interno para sincronização via ADN, gestão de certificados A1,
          catálogo fiscal e exportações — com isolamento por escritório e trilha de auditoria.
        </p>
        <ul class="space-y-3 text-sm text-muted">
          <li class="flex items-start gap-2.5">
            <UIcon name="i-lucide-shield-check" class="size-4 mt-0.5 text-primary shrink-0" />
            <span>Acesso exclusivo a funcionários do escritório</span>
          </li>
          <li class="flex items-start gap-2.5">
            <UIcon name="i-lucide-lock-keyhole" class="size-4 mt-0.5 text-primary shrink-0" />
            <span>Sessão protegida com CSRF e segundo fator para administradores</span>
          </li>
          <li class="flex items-start gap-2.5">
            <UIcon name="i-lucide-building-2" class="size-4 mt-0.5 text-primary shrink-0" />
            <span>Dados isolados por escritório — sem portal de cliente final</span>
          </li>
        </ul>
      </div>

      <p class="relative z-10 text-xs text-muted">
        © {{ year }} NFS-e ADN · uso interno
      </p>
    </aside>

    <!-- Formulário -->
    <div class="flex flex-col min-h-screen">
      <header class="flex items-center justify-between gap-3 p-4 sm:p-6 lg:justify-end">
        <div class="inline-flex items-center gap-2 lg:hidden">
          <UIcon name="i-lucide-receipt" class="size-5 text-primary" />
          <span class="font-semibold text-sm">NFS-e ADN</span>
        </div>
        <UColorModeButton />
      </header>

      <main class="flex flex-1 flex-col items-center justify-center px-4 pb-10 sm:px-6">
        <div class="w-full max-w-md">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
