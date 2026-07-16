<script setup lang="ts">
/**
 * Banner visível quando PLATFORM_ADMIN opera em access_mode=platform_privileged.
 * A11y: role=status + aria-live; contraste warning.
 */
const { me, isPlatformPrivileged } = useDashboard()
const { clearSelection, switching } = usePlatformOfficeSelect()

const officeName = computed(() => me.value?.office?.name || 'Escritório')
const officeSlug = computed(() => me.value?.office?.slug || '')
</script>

<template>
  <div
    v-if="isPlatformPrivileged"
    data-testid="privileged-context-banner"
    role="status"
    aria-live="polite"
    class="border-b border-warning/40 bg-warning/10"
  >
    <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center justify-between gap-2 px-4 py-2 sm:px-6">
      <div class="flex min-w-0 items-start gap-2 text-sm">
        <UIcon
          name="i-lucide-shield-alert"
          class="mt-0.5 size-4 shrink-0 text-warning"
          aria-hidden="true"
        />
        <div class="min-w-0">
          <p class="font-medium text-highlighted">
            Contexto privilegiado da plataforma
          </p>
          <p class="text-xs text-muted">
            Operando como ADMIN efetivo em
            <strong class="text-highlighted">{{ officeName }}</strong>
            <span v-if="officeSlug"> ({{ officeSlug }})</span>
            · ator: {{ me?.name || me?.email }} · auditoria interna ativa
          </p>
        </div>
      </div>
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="i-lucide-log-out"
        label="Sair do contexto"
        :loading="switching"
        aria-label="Encerrar contexto privilegiado do escritório"
        data-testid="privileged-context-exit"
        @click="() => { void clearSelection() }"
      />
    </div>
  </div>
</template>
