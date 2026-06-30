// Resolves the REST API base URL from WordPress runtime config first, then Nuxt public runtime config.
export function grailHrApiBase(fallback: string): string {
  if (typeof window !== 'undefined') {
    const config = (window as unknown as { GrailHRConfig?: { apiBase?: string } }).GrailHRConfig;

    if (config?.apiBase) {
      return config.apiBase.replace(/\/$/, '');
    }
  }

  return fallback.replace(/\/$/, '');
}
