export default defineNuxtRouteMiddleware((to) => {
  const moduleKey = to.path.startsWith('/monitoring/dctfweb')
    ? 'dctfweb'
    : 'simples_mei'

  return navigateTo(
    monitoringLegacySubmoduleLocation(moduleKey, to.query),
    { replace: true, redirectCode: 301 }
  )
})
