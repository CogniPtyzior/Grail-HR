export default defineNuxtRouteMiddleware((to) => {
  if (!import.meta.client) {
    return;
  }

  const hashRoute = window.location.hash.match(/^#(\/.*)$/)?.[1] || '';
  const targetPath = hashRoute && hashRoute !== '/' ? hashRoute : '/profiles';
  const isLoginRoute = to.path === '/login';
  const hasStoredToken = Boolean(sessionStorage.getItem('grail_hr_token'));

  if (!hasStoredToken && !isLoginRoute) {
    return navigateTo('/login', { replace: true });
  }

  if (hasStoredToken && isLoginRoute) {
    return navigateTo('/profiles', { replace: true });
  }

  if (hasStoredToken && to.path === '/') {
    return navigateTo(targetPath, { replace: true });
  }
});