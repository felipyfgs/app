/**
 * Tokens canônicos do painel — alinhados ao template
 * `.reference/nuxt-dashboard-template/app/app.config.ts` (primary green / neutral zinc).
 * Não introduzir paleta paralela nem cores raw nas páginas.
 */
export default defineAppConfig({
  ui: {
    colors: {
      primary: 'green',
      neutral: 'zinc'
    },
    // Superfícies de alerta alinhadas ao tema: subtle evita blocos solid
    // que quebram contraste no dark mode.
    alert: {
      defaultVariants: {
        color: 'primary',
        variant: 'subtle'
      }
    },
    // Densidade padrão de formulários Settings / Auth (Nuxt UI).
    formField: {
      defaultVariants: {
        size: 'md'
      }
    },
    button: {
      defaultVariants: {
        size: 'md'
      }
    }
  }
})
