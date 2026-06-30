// Authentication store. The token is deliberately kept in sessionStorage for V1.
import { defineStore } from 'pinia';

interface AuthUser {
  id: number;
  display_name: string;
  email: string;
  capabilities: string[];
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: '' as string,
    user: null as AuthUser | null,
  }),
  getters: {
    isAuthenticated: (state) => state.token !== '',
  },
  actions: {
    loadFromSession() {
      if (import.meta.client) {
        this.token = sessionStorage.getItem('grail_hr_token') || '';
        this.user = JSON.parse(sessionStorage.getItem('grail_hr_user') || 'null');
      }
    },
    persist(token: string, user: AuthUser) {
      this.token = token;
      this.user = user;
      sessionStorage.setItem('grail_hr_token', token);
      sessionStorage.setItem('grail_hr_user', JSON.stringify(user));
    },
    clearSession() {
      this.token = '';
      this.user = null;
      if (import.meta.client) {
        sessionStorage.removeItem('grail_hr_token');
        sessionStorage.removeItem('grail_hr_user');
      }
    },
  },
});
