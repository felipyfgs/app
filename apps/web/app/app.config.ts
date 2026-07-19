/**
 * Tokens canônicos do painel — Inter × Nuxt UI.
 *
 * primary  → orange (#FF7A00 main; 400/600/700 brand Inter)
 * neutral  → zinc (dark ≈ #0D0D0D)
 * success  → green (#00A868)
 * warning  → amber (#FFB800)
 * error    → red (#E5222D)
 * info     → blue (#1E7FE6)
 *
 * Escalas em `assets/css/main.css`. Não reintroduzir primary green do template.
 */
export default defineAppConfig({
  ui: {
    colors: {
      primary: 'orange',
      secondary: 'violet',
      neutral: 'zinc',
      success: 'green',
      warning: 'amber',
      error: 'red',
      info: 'blue'
    },
    // Superfícies de alerta alinhadas ao tema: subtle evita blocos solid
    // que quebram contraste no dark mode.
    alert: {
      defaultVariants: {
        color: 'primary',
        variant: 'subtle'
      }
    },
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
