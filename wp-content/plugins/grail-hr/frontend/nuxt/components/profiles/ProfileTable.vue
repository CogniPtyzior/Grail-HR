<!-- Responsive profile data table. Server-side pagination/filtering is driven by the parent page. -->
<script setup lang="ts">
import { Eye, AlertTriangle, FilePlus2 } from 'lucide-vue-next';
import type { ProfileListItem } from '~/types/profile';
import ProfileStatusBadge from '~/components/profiles/ProfileStatusBadge.vue';

defineProps<{ items: ProfileListItem[]; loading: boolean; hasActiveFilters?: boolean }>();

const clean = (value?: string) => value?.trim() ?? '';

const profilePrimary = (profile: ProfileListItem) =>
  clean(profile.profile_title) || clean(profile.candidate_full_name) || clean(profile.title) || 'Profil CV';

const profileSecondary = (profile: ProfileListItem) => {
  const title = clean(profile.profile_title);
  const candidateName = clean(profile.candidate_full_name);

  return title && candidateName && title !== candidateName ? candidateName : '';
};

const profileSummary = (profile: ProfileListItem) => {
  const summary = clean(profile.summary_short);
  const primary = profilePrimary(profile);
  const secondary = profileSecondary(profile);

  return summary && summary !== primary && summary !== secondary ? summary : '';
};
</script>

<template>
  <div class="ghr-card">
    <div v-if="loading" class="ghr-empty-state">Chargement des profils…</div>
    <div v-else-if="items.length === 0" class="ghr-empty-state">
      <FilePlus2 :size="36" aria-hidden="true" />
      <h2>{{ hasActiveFilters ? 'Aucun profil trouvé' : 'Aucun profil pour le moment' }}</h2>
      <p class="ghr-muted">
        {{ hasActiveFilters ? 'Aucun profil ne correspond à cette recherche avec les filtres actuels.' : 'Importez un CV PDF ou créez une fiche manuellement pour commencer.' }}
      </p>
      <NuxtLink v-if="!hasActiveFilters" class="ghr-button ghr-button--primary" to="/profiles/new">Créer un profil</NuxtLink>
    </div>
    <div v-else>
      <div class="ghr-table-wrap ghr-profiles-desktop">
        <table class="ghr-table">
          <thead>
            <tr>
              <th>Profil</th>
              <th>Métier principal</th>
              <th>Seniorité</th>
              <th>Contact</th>
              <th>Statut</th>
              <th>Alertes</th>
              <th>Dernière modification</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="profile in items" :key="profile.id">
              <td>
                <strong>{{ profilePrimary(profile) }}</strong>
                <div v-if="profileSecondary(profile)" class="ghr-muted">{{ profileSecondary(profile) }}</div>
                <div v-if="profileSummary(profile)" class="ghr-muted">{{ profileSummary(profile) }}</div>
              </td>
              <td>{{ profile.primary_job_label || 'Non renseigné' }}</td>
              <td>{{ profile.seniority_level || 'unknown' }}</td>
              <td>
                <div>{{ profile.candidate_email || '—' }}</div>
                <div class="ghr-muted">{{ profile.candidate_phone }}</div>
              </td>
              <td><ProfileStatusBadge :review-status="profile.review_status" :analysis-status="profile.analysis_status" /></td>
              <td>
                <span v-if="profile.warnings_count > 0" class="ghr-warning-count" :title="`${profile.warnings_count} point(s) à vérifier`">
                  <AlertTriangle :size="16" aria-hidden="true" /> {{ profile.warnings_count }}
                </span>
                <span v-else>—</span>
              </td>
              <td>{{ profile.updated_at }}</td>
              <td>
                <NuxtLink class="ghr-button ghr-button--secondary" :to="`/profiles/${profile.id}`">
                  <Eye :size="16" aria-hidden="true" /> Ouvrir
                </NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="ghr-profile-cards" aria-label="Profils">
        <article v-for="profile in items" :key="`mobile-${profile.id}`" class="ghr-profile-card">
          <header>
            <div>
              <h2>{{ profilePrimary(profile) }}</h2>
              <p v-if="profileSecondary(profile)" class="ghr-muted">{{ profileSecondary(profile) }}</p>
            </div>
            <ProfileStatusBadge :review-status="profile.review_status" :analysis-status="profile.analysis_status" />
          </header>
          <p v-if="profileSummary(profile)" class="ghr-muted">{{ profileSummary(profile) }}</p>
          <dl>
            <div><dt>Métier</dt><dd>{{ profile.primary_job_label || 'Non renseigné' }}</dd></div>
            <div><dt>Seniorité</dt><dd>{{ profile.seniority_level || 'unknown' }}</dd></div>
            <div><dt>Contact</dt><dd>{{ profile.candidate_email || '—' }}</dd></div>
            <div><dt>Alertes</dt><dd>{{ profile.warnings_count }}</dd></div>
            <div><dt>Mis à jour</dt><dd>{{ profile.updated_at }}</dd></div>
          </dl>
          <NuxtLink class="ghr-button ghr-button--secondary" :to="`/profiles/${profile.id}`">
            <Eye :size="16" aria-hidden="true" /> Ouvrir
          </NuxtLink>
        </article>
      </div>
    </div>
  </div>
</template>
