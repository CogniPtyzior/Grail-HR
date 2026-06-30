<!-- Topbar with primary navigation and a clear logout action. -->
<script setup lang="ts">
import { LogOut, User } from 'lucide-vue-next';

const auth = useAuthStore();
const api = useApi();

async function logout() {
  try {
    await api.request('/auth/logout', { method: 'POST' });
  } catch {
    // Logout must never trap the user. Local session is cleared even if the server request fails.
  } finally {
    auth.clearSession();
    await navigateTo('/login?reason=logged_out');
  }
}
</script>

<template>
  <header class="ghr-topbar">
    <NuxtLink class="ghr-topbar__brand" to="/profiles" aria-label="Grail HR - Accueil">
      Grail HR
    </NuxtLink>
    <nav class="ghr-topbar__nav" aria-label="Navigation principale">
      <NuxtLink class="ghr-topbar__link" to="/profiles">Profils CV</NuxtLink>
      <NuxtLink class="ghr-topbar__link" to="/profiles/new">Créer un profil</NuxtLink>
    </nav>
    <div class="ghr-topbar__actions">
      <span class="ghr-user-chip"><User :size="16" aria-hidden="true" /> {{ auth.user?.display_name || 'Utilisateur' }}</span>
      <button class="ghr-button ghr-button--logout" type="button" title="Se déconnecter" aria-label="Se déconnecter" @click="logout">
        Déconnexion <LogOut class="ghr-button__icon--right" :size="16" aria-hidden="true" />
      </button>
    </div>
  </header>
</template>
