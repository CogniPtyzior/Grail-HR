<!-- Login page using WordPress credentials and Grail HR access checks. -->
<script setup lang="ts">
import { Eye, EyeOff, ShieldCheck } from 'lucide-vue-next';

const route = useRoute();
const auth = useAuthStore();
const config = useRuntimeConfig();
const login = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');

const infoMessage = computed(() => {
  if (route.query.reason === 'logged_out') return 'Vous êtes déconnecté.';
  if (route.query.reason === 'session_expired') return 'Votre session a expiré. Veuillez vous reconnecter.';
  return '';
});

async function submit() {
  loading.value = true;
  error.value = '';

  try {
    const apiBase = grailHrApiBase(String(config.public.apiBase));
    const response = await fetch(`${apiBase}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login: login.value, password: password.value }),
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(payload.message || 'Connexion impossible. Vérifiez vos identifiants et votre accès Grail HR.');
    }

    auth.persist(payload.token, payload.user);
    await navigateTo('/profiles');
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Connexion impossible. Veuillez réessayer.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="ghr-login-page" aria-labelledby="ghr-login-title">
    <section class="ghr-login-card">
      <div class="ghr-login-card__brand" aria-hidden="true"><ShieldCheck :size="28" /></div>
      <p class="ghr-eyebrow">Grail HR</p>
      <h1 id="ghr-login-title">Connexion</h1>
      <p class="ghr-muted">Connectez-vous avec votre compte WordPress autorisé pour accéder à l’analyse de CV.</p>

      <p v-if="infoMessage" class="ghr-alert ghr-alert--info" role="status">{{ infoMessage }}</p>
      <p v-if="error" role="alert" class="ghr-alert ghr-alert--error">{{ error }}</p>

      <form class="ghr-form" @submit.prevent="submit">
        <label class="ghr-field">
          <span>Identifiant ou email</span>
          <input v-model="login" class="ghr-input" autocomplete="username" required>
        </label>
        <label class="ghr-field">
          <span>Mot de passe</span>
          <span class="ghr-password-field">
            <input
              v-model="password"
              class="ghr-input"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              required
            >
            <button
              class="ghr-icon-button"
              type="button"
              :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
              @click="showPassword = !showPassword"
            >
              <EyeOff v-if="showPassword" :size="18" aria-hidden="true" />
              <Eye v-else :size="18" aria-hidden="true" />
            </button>
          </span>
        </label>
        <button class="ghr-button ghr-button--primary ghr-button--block" type="submit" :disabled="loading">
          {{ loading ? 'Connexion…' : 'Se connecter' }}
        </button>
      </form>
    </section>
  </main>
</template>
