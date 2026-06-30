// Central API client. It injects bearer tokens, maps user-friendly errors and clears expired sessions.
export function useApi() {
  const config = useRuntimeConfig();
  const auth = useAuthStore();

  async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
    // On hard refresh, route components may call the API before the store has
    // been hydrated by a page action. Read sessionStorage once before the call.
    auth.loadFromSession();

    const headers = new Headers(options.headers);

    if (!(options.body instanceof FormData)) {
      headers.set('Content-Type', 'application/json');
    }

    if (auth.token) {
      headers.set('Authorization', `Bearer ${auth.token}`);
    }

    const apiBase = grailHrApiBase(String(config.public.apiBase));
    const response = await fetch(`${apiBase}${path}`, { ...options, headers });
    const payload = await response.json().catch(() => ({}));

    if (response.status === 401) {
      auth.clearSession();
      await navigateTo('/login?reason=session_expired', { replace: true });
      throw new Error(payload.message || 'Votre session a expiré. Veuillez vous reconnecter.');
    }

    if (!response.ok) {
      throw new Error(payload.message || 'Une erreur est survenue. Veuillez réessayer.');
    }

    return payload as T;
  }

  return { request };
}
