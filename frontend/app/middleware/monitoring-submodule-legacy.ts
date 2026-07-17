/**
 * Redireciona paths legados `/monitoring/{modulo}/{submodule}` para o path
 * limpo do item da sidebar (sem query). Tabs são estado local da página.
 *
 * Canônico: `/monitoring/simples-mei`, `/monitoring/dctfweb`
 */
export default defineNuxtRouteMiddleware((to) => {
  const moduleKey = to.path.startsWith('/monitoring/dctfweb')
    ? 'dctfweb'
    : 'simples_mei'

  const segment = Array.isArray(to.params.submodule)
    ? to.params.submodule[0]
    : to.params.submodule

  return navigateTo(
    monitoringLegacySubmoduleLocation(moduleKey, to.query, segment),
    { replace: true, redirectCode: 301 }
  )
})
