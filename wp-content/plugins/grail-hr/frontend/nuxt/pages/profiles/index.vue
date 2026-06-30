<!-- Profile list with additive global search, filters and server-side pagination. -->
<script setup lang="ts">
import { LogOut, Plus, Search } from 'lucide-vue-next';
import AppHeader from '~/components/layout/AppHeader.vue';
import ProfileTable from '~/components/profiles/ProfileTable.vue';
import type { PaginatedProfiles, ProfileListItem } from '~/types/profile';

const api = useApi();
const auth = useAuthStore();

const search = ref('');
const debouncedSearch = ref('');
const status = ref('');
const seniority = ref('');
const sortBy = ref('updated_at');
const sortOrder = ref<'asc' | 'desc'>('desc');
const page = ref(1);
const perPage = 25;

const loading = ref(false);
const error = ref('');

const profiles = ref<ProfileListItem[]>([]);
const pagination = ref({
  page: 1,
  per_page: perPage,
  total: 0,
  total_pages: 1,
});

let timer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const hasActiveFilters = computed(() => {
  return Boolean(debouncedSearch.value || status.value || seniority.value);
});

watch(search, (value) => {
  if (timer) {
    clearTimeout(timer);
  }

  const normalized = value.trim().replace(/\s+/g, ' ');

  timer = setTimeout(() => {
    debouncedSearch.value = normalized.length === 1 ? '' : normalized;
    page.value = 1;
  }, 350);
});

watch([status, seniority, sortBy, sortOrder], () => {
  page.value = 1;
});

watch(
  [debouncedSearch, status, seniority, sortBy, sortOrder, page],
  () => {
    fetchProfiles();
  },
  { immediate: true },
);

function normalizeProfilesResponse(response: unknown): PaginatedProfiles {
  const payload = response as {
    data?: unknown;
    items?: unknown;
    pagination?: Partial<PaginatedProfiles['pagination']>;
  };

  const items = Array.isArray(payload?.data)
    ? payload.data
    : Array.isArray(payload?.items)
      ? payload.items
      : [];

  const responsePagination = payload?.pagination || {};

  const total = Number(responsePagination.total ?? items.length);
  const responsePerPage = Number(responsePagination.per_page || perPage);
  const totalPages = Math.max(
    1,
    Number(
      responsePagination.total_pages
      || Math.ceil(total / responsePerPage)
      || 1,
    ),
  );

  return {
    data: items as ProfileListItem[],
    pagination: {
      page: Number(responsePagination.page || page.value || 1),
      per_page: responsePerPage,
      total,
      total_pages: totalPages,
    },
  };
}

async function fetchProfiles() {
  const currentRequest = ++requestId;

  loading.value = true;
  error.value = '';

  const params = new URLSearchParams({
    page: String(page.value),
    per_page: String(perPage),
    sort_by: sortBy.value,
    sort_order: sortOrder.value,
  });

  if (debouncedSearch.value.length >= 2) {
    params.set('search', debouncedSearch.value);
  }

  if (status.value) {
    params.set('review_status', status.value);
  }

  if (seniority.value) {
    params.set('seniority_level', seniority.value);
  }

  try {
    const response = await api.request<PaginatedProfiles>(`/profiles?${params.toString()}`);
    const normalized = normalizeProfilesResponse(response);

    if (currentRequest !== requestId) {
      return;
    }

    profiles.value = normalized.data;
    pagination.value = normalized.pagination;
  } catch (exception) {
    if (currentRequest !== requestId) {
      return;
    }

    profiles.value = [];
    pagination.value = {
      page: page.value,
      per_page: perPage,
      total: 0,
      total_pages: 1,
    };

    error.value = exception instanceof Error
      ? exception.message
      : 'Impossible de charger les profils.';
  } finally {
    if (currentRequest === requestId) {
      loading.value = false;
    }
  }
}

async function logout() {
  try {
    await api.request('/auth/logout', { method: 'POST' });
  } catch {
    // La déconnexion locale doit rester possible même si l’API ne répond pas.
  } finally {
    auth.clearSession();
    await navigateTo('/login?reason=logged_out');
  }
}

function previousPage() {
  if (page.value > 1) {
    page.value -= 1;
  }
}

function nextPage() {
  if (page.value < pagination.value.total_pages) {
    page.value += 1;
  }
}
</script>

<template>
  <div>
    <AppHeader />

    <main class="ghr-page">
    <header class="ghr-page-header">
      <div>
        <p class="ghr-eyebrow">Profils candidats</p>
        <h1>Profils CV</h1>
        <p class="ghr-muted">
          Retrouvez les profils générés depuis les CV importés, relisez les analyses et validez les fiches prêtes.
        </p>
        <p class="ghr-muted">
          Les CV sources ne sont pas conservés après analyse. Seule la fiche profil structurée est sauvegardée.
        </p>
      </div>

      <div class="ghr-page-actions" aria-label="Actions de la page">
        <NuxtLink class="ghr-button ghr-button--primary" to="/profiles/new">
          Nouveau profil
          <Plus class="ghr-button__icon--right" :size="16" aria-hidden="true" />
        </NuxtLink>

        <button
          class="ghr-button ghr-button--logout"
          type="button"
          title="Se déconnecter"
          aria-label="Se déconnecter"
          @click="logout"
        >
          Déconnexion
          <LogOut class="ghr-button__icon--right" :size="16" aria-hidden="true" />
        </button>
      </div>
    </header>

    <section class="ghr-card ghr-filter-card" aria-label="Filtres des profils">
      <label class="ghr-field ghr-field--grow">
        <span>
          <Search :size="16" aria-hidden="true" />
          Recherche globale
        </span>
        <input
          v-model="search"
          class="ghr-input"
          placeholder="Nom, email, métier, localisation…"
        >
      </label>

      <label class="ghr-field">
        <span>Statut</span>
        <select v-model="status" class="ghr-select">
          <option value="">Tous</option>
          <option value="to_review">À relire</option>
          <option value="edited">Modifié</option>
          <option value="validated">Validé</option>
          <option value="archived">Archivé</option>
        </select>
      </label>

      <label class="ghr-field">
        <span>Seniorité</span>
        <select v-model="seniority" class="ghr-select">
          <option value="">Toutes</option>
          <option value="junior">Junior</option>
          <option value="confirmed">Confirmé</option>
          <option value="senior">Senior</option>
          <option value="lead">Lead</option>
          <option value="manager">Manager</option>
          <option value="expert">Expert</option>
          <option value="unknown">Inconnu</option>
        </select>
      </label>

      <label class="ghr-field">
        <span>Tri</span>
        <select v-model="sortBy" class="ghr-select">
          <option value="updated_at">Dernière modification</option>
          <option value="created_at">Création</option>
          <option value="review_status">Statut</option>
          <option value="seniority_level">Seniorité</option>
          <option value="primary_job_label">Métier principal</option>
        </select>
      </label>

      <label class="ghr-field">
        <span>Ordre</span>
        <select v-model="sortOrder" class="ghr-select">
          <option value="desc">Décroissant</option>
          <option value="asc">Croissant</option>
        </select>
      </label>
    </section>

    <p v-if="error" role="alert" class="ghr-alert ghr-alert--error">
      {{ error }}
    </p>

    <ProfileTable
      :items="profiles"
      :loading="loading"
      :has-active-filters="hasActiveFilters"
    />

    <nav class="ghr-pagination" aria-label="Pagination">
      <button
        class="ghr-button ghr-button--secondary"
        type="button"
        :disabled="page <= 1 || loading"
        @click="previousPage"
      >
        Page précédente
      </button>

      <span>
        Page {{ pagination.page }} / {{ pagination.total_pages || 1 }}
      </span>

      <button
        class="ghr-button ghr-button--secondary"
        type="button"
        :disabled="page >= pagination.total_pages || loading"
        @click="nextPage"
      >
        Page suivante
      </button>
    </nav>
  </main>
  </div>
</template>