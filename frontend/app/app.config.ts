export default defineAppConfig({
  ui: {
    colors: {
      primary: 'green',
      neutral: 'zinc'
    },
    // Superfícies de alerta alinhadas ao tema (zinc + green): sem blocos solid
    // vermelho/amarelo/azul que quebram o dark mode do painel.
    alert: {
      defaultVariants: {
        color: 'primary',
        variant: 'subtle'
      }
    }
  }
})
