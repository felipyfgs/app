<script setup lang="ts">
/**
 * Casca reutilizável de página autenticada do painel (lista, settings, home simples).
 *
 * Anatomia canônica (template customers.vue / settings.vue):
 *   UDashboardPanel
 *     → header: UDashboardNavbar (+ leading collapse) → toolbar opcional (slot)
 *     → body: KPIs/stats (slot) → conteúdo principal (default) → footer/paginação (slot)
 *
 * Fontes:
 * - Produto: `frontend/app/pages/clients/index.vue`
 * - Template: `.reference/nuxt-dashboard-template/app/pages/customers.vue`
 * - Settings: `.reference/nuxt-dashboard-template/app/pages/settings.vue`
 *
 * Uso:
 * - Slots genéricos: navbar-trailing, navbar-right, toolbar, kpis, default, footer
 * - UTable: presets de `~/utils/table-ui` (proibido `:ui` inventado solto)
 * - Empty de lista: preferir `UEmpty` (Nuxt UI) em vez de texto plano
 * - Header ordenável: `sortHeader` em `~/utils/table-sort`
 *
 * Não colocar domínio fiscal/API neste primitivo. FiscalModuleTable e páginas
 * de monitoring/work/settings apenas **compõem** esta casca.
 */
withDefaults(defineProps<{
  /** id do UDashboardPanel (ex.: "syncs", "health") */
  panelId: string
  /** Título do UDashboardNavbar */
  title: string
  /** data-testid no navbar — padrão page-navbar (checklist template) */
  navbarTestId?: string | null
  /** data-testid opcional no panel */
  panelTestId?: string | null
  /** class no root do panel */
  panelClass?: string | null
  /** ui do UDashboardPanel (ex.: { body: 'lg:py-12' } em settings) */
  panelUi?: Record<string, unknown> | null
  /** painel redimensionável (mestre–detalhe / inbox) */
  resizable?: boolean
  defaultSize?: number | null
  minSize?: number | null
  maxSize?: number | null
  /** UDashboardSidebarCollapse no leading (false em painéis secundários) */
  showCollapse?: boolean
  /** prop `toggle` do UDashboardNavbar (false esconde toggle mobile no 2º painel) */
  navbarToggle?: boolean | undefined
}>(), {
  navbarTestId: 'page-navbar',
  panelTestId: null,
  panelClass: null,
  panelUi: null,
  resizable: false,
  defaultSize: null,
  minSize: null,
  maxSize: null,
  showCollapse: true,
  navbarToggle: undefined
})
</script>

<template>
  <UDashboardPanel
    :id="panelId"
    :data-testid="panelTestId || undefined"
    :class="panelClass || undefined"
    :ui="panelUi || undefined"
    :resizable="resizable"
    :default-size="defaultSize ?? undefined"
    :min-size="minSize ?? undefined"
    :max-size="maxSize ?? undefined"
  >
    <template #header>
      <UDashboardNavbar
        :title="title"
        :data-testid="navbarTestId || undefined"
        :toggle="navbarToggle"
      >
        <template
          v-if="showCollapse || $slots['navbar-leading']"
          #leading
        >
          <slot name="navbar-leading">
            <UDashboardSidebarCollapse v-if="showCollapse" />
          </slot>
        </template>
        <template
          v-if="$slots['navbar-trailing']"
          #trailing
        >
          <slot name="navbar-trailing" />
        </template>
        <template #right>
          <slot name="navbar-right" />
        </template>
      </UDashboardNavbar>

      <!-- Toolbar(s) opcional(is) no header -->
      <slot name="toolbar" />
    </template>

    <template #body>
      <slot name="kpis" />
      <slot />
      <slot name="footer" />
    </template>
  </UDashboardPanel>
</template>
