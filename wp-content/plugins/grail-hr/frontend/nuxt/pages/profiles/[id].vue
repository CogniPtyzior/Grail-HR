<!-- Profile detail editor. V1 focuses on high-value summary fields while keeping the full JSON as backend source of truth. -->
<script setup lang="ts">
import { BriefcaseBusiness, ChevronDown, FileText, IdCard, Save, Target, Trash2 } from 'lucide-vue-next';
import { cvAnalysisSchema } from '~/utils/analysisSchema';
import type { ProfileDetail } from '~/types/profile';
import AppHeader from '~/components/layout/AppHeader.vue';
import ProfileStatusBadge from '~/components/profiles/ProfileStatusBadge.vue';

const route = useRoute();
const api = useApi();
const profile = ref<ProfileDetail | null>(null);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const dirty = ref(false);
const actionLoading = ref(false);
const openSections = reactive<Record<string, boolean>>({
  summary: true,
  candidate: true,
  targeting: true,
  seniority: false,
});

const experienceFields = [
  { key: 'company', label: 'Entreprise' },
  { key: 'role', label: 'Poste' },
  { key: 'location', label: 'Lieu' },
  { key: 'start_date', label: 'Début' },
  { key: 'end_date', label: 'Fin' },
  { key: 'description', label: 'Description', kind: 'textarea' as const },
];
const educationFields = [
  { key: 'school', label: 'École / organisme' },
  { key: 'degree', label: 'Diplôme' },
  { key: 'field', label: 'Domaine' },
  { key: 'description', label: 'Description', kind: 'textarea' as const },
];
const skillFields = [
  { key: 'label', label: 'Libellé' },
  { key: 'category', label: 'Catégorie' },
  { key: 'level', label: 'Niveau' },
  { key: 'evidence', label: 'Justification', kind: 'textarea' as const },
];
const toolFields = [
  { key: 'label', label: 'Nom' },
  { key: 'category', label: 'Catégorie' },
  { key: 'evidence', label: 'Justification', kind: 'textarea' as const },
];
const labelDescriptionFields = [
  { key: 'label', label: 'Libellé' },
  { key: 'description', label: 'Description', kind: 'textarea' as const },
  { key: 'evidence', label: 'Justification', kind: 'textarea' as const },
];
const languageFields = [
  { key: 'label', label: 'Langue' },
  { key: 'level', label: 'Niveau' },
  { key: 'evidence', label: 'Justification', kind: 'textarea' as const },
];
const interestFields = [
  { key: 'label', label: 'Libellé' },
  { key: 'description', label: 'Description', kind: 'textarea' as const },
];
const warningFields = [
  { key: 'type', label: 'Type' },
  { key: 'message', label: 'Message', kind: 'textarea' as const },
];


onMounted(load);

function wait(milliseconds: number) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function load() {
  loading.value = true;
  error.value = '';

  for (let attempt = 1; attempt <= 2; attempt += 1) {
    try {
      profile.value = await api.request<ProfileDetail>(`/profiles/${route.params.id}`);
      loading.value = false;
      return;
    } catch (exception) {
      if (attempt === 1) {
        await wait(450);
        continue;
      }

      error.value = exception instanceof Error ? exception.message : 'Impossible de charger la fiche profil.';
      loading.value = false;
    }
  }
}

function toggleSection(key: string) {
  openSections[key] = !openSections[key];
}


async function save() {
  if (!profile.value) return;
  const parsed = cvAnalysisSchema.safeParse(profile.value.analysis);

  if (!parsed.success) {
    error.value = 'Le profil contient des données invalides. Vérifiez les champs modifiés.';
    return;
  }

  saving.value = true;
  error.value = '';
  profile.value = await api.request<ProfileDetail>(`/profiles/${profile.value.id}`, {
    method: 'PUT',
    body: JSON.stringify(profile.value.analysis),
  });
  dirty.value = false;
  saving.value = false;
}

async function runProfileAction(path: string) {
  if (!profile.value) return;
  actionLoading.value = true;
  error.value = '';

  try {
    profile.value = await api.request<ProfileDetail>(`/profiles/${profile.value.id}/${path}`, { method: 'POST' });
    dirty.value = false;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Action impossible.';
  } finally {
    actionLoading.value = false;
  }
}

async function deleteProfile() {
  if (!profile.value) return;
  const confirmed = window.confirm('Supprimer ce profil ? Cette action le placera en corbeille sans suppression physique.');

  if (!confirmed) return;

  actionLoading.value = true;
  error.value = '';

  try {
    await api.request(`/profiles/${profile.value.id}`, { method: 'DELETE' });
    await navigateTo('/profiles');
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Suppression impossible.';
  } finally {
    actionLoading.value = false;
  }
}

</script>

<template>
  <div>
    <AppHeader />
    <main class="ghr-page">
    <div v-if="loading" class="ghr-card">Chargement du profil…</div>
    <div v-else-if="!profile && error" class="ghr-card">
      <p role="alert" class="ghr-alert ghr-alert--error">{{ error }}</p>
      <NuxtLink class="ghr-button ghr-button--secondary" to="/profiles">Retour aux profils</NuxtLink>
    </div>
    <article v-else-if="profile" class="ghr-card ghr-profile-editor">
      <header class="ghr-toolbar">
        <div>
          <NuxtLink to="/profiles">← Retour aux profils</NuxtLink>
          <h1>{{ profile.title }}</h1>
          <p class="ghr-muted">Relisez les informations extraites, corrigez les éléments nécessaires, puis validez le profil.</p>
          <ProfileStatusBadge :review-status="profile.review_status" :analysis-status="profile.analysis_status" />
        </div>
        <div class="ghr-topbar__actions">
          <NuxtLink class="ghr-button ghr-button--secondary" :to="`/profiles/replace/${profile.id}`">
            Réinitialiser et importer un CV
          </NuxtLink>
          <button
            v-if="profile.permissions.can_validate && profile.review_status !== 'validated'"
            class="ghr-button ghr-button--secondary"
            :disabled="actionLoading"
            @click="runProfileAction('validate')"
          >
            Valider
          </button>
          <button
            v-if="profile.review_status === 'validated' && profile.permissions.can_edit"
            class="ghr-button ghr-button--secondary"
            :disabled="actionLoading"
            @click="runProfileAction('reopen')"
          >
            Réouvrir
          </button>
          <button
            v-if="profile.permissions.can_archive && profile.review_status !== 'archived'"
            class="ghr-button ghr-button--secondary"
            :disabled="actionLoading"
            @click="runProfileAction('archive')"
          >
            Archiver
          </button>
          <button
            v-if="profile.permissions.can_archive && profile.review_status === 'archived'"
            class="ghr-button ghr-button--secondary"
            :disabled="actionLoading"
            @click="runProfileAction('restore')"
          >
            Restaurer
          </button>
          <button
            v-if="profile.permissions.can_delete"
            class="ghr-button ghr-button--danger"
            :disabled="actionLoading"
            @click="deleteProfile"
          >
            <Trash2 :size="16" aria-hidden="true" /> Supprimer
          </button>
          <button class="ghr-button ghr-button--primary" :disabled="saving || !dirty" @click="save">
            <Save :size="16" aria-hidden="true" /> {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </header>

      <p v-if="dirty" class="ghr-muted">Modifications non enregistrées.</p>
      <p v-if="error" role="alert" class="ghr-error">{{ error }}</p>

      <section class="ghr-collapsible-panel" :class="{ 'ghr-collapsible-panel--closed': !openSections.summary }">
        <button class="ghr-panel-toggle" type="button" :aria-expanded="openSections.summary" @click="toggleSection('summary')">
          <ChevronDown class="ghr-section__chevron" :size="18" aria-hidden="true" />
          <FileText :size="20" aria-hidden="true" />
          <span>
            <strong>Résumé</strong>
            <span class="ghr-muted">Synthèse courte du profil, rédigée pour une lecture rapide par un recruteur.</span>
          </span>
        </button>
        <div v-show="openSections.summary" class="ghr-panel-body ghr-form-grid">
          <label class="ghr-field ghr-field--wide">
            <span>Titre profil</span>
            <input v-model="profile.analysis.summary.profile_title" class="ghr-input" @input="dirty = true">
          </label>
          <label class="ghr-field">
            <span>Résumé court</span>
            <textarea
              v-model="profile.analysis.summary.short_summary"
              class="ghr-textarea"
              maxlength="350"
              @input="dirty = true"
            />
          </label>
          <label class="ghr-field">
            <span>Résumé long</span>
            <textarea
              v-model="profile.analysis.summary.long_summary"
              class="ghr-textarea"
              maxlength="900"
              @input="dirty = true"
            />
          </label>
        </div>
      </section>

      <section class="ghr-collapsible-panel" :class="{ 'ghr-collapsible-panel--closed': !openSections.candidate }">
        <button class="ghr-panel-toggle" type="button" :aria-expanded="openSections.candidate" @click="toggleSection('candidate')">
          <ChevronDown class="ghr-section__chevron" :size="18" aria-hidden="true" />
          <IdCard :size="20" aria-hidden="true" />
          <span>
            <strong>Identité candidat</strong>
            <span class="ghr-muted">Coordonnées et informations de contact à vérifier avant validation.</span>
          </span>
        </button>
        <div v-show="openSections.candidate" class="ghr-panel-body ghr-form-grid ghr-form-grid--compact">
          <label class="ghr-field"><span>Nom complet</span><input v-model="profile.analysis.candidate.full_name" class="ghr-input" @input="dirty = true"></label>
          <label class="ghr-field"><span>Email</span><input v-model="profile.analysis.candidate.email" class="ghr-input" @input="dirty = true"></label>
          <label class="ghr-field"><span>Téléphone</span><input v-model="profile.analysis.candidate.phone" class="ghr-input" @input="dirty = true"></label>
          <label class="ghr-field"><span>Localisation</span><input v-model="profile.analysis.candidate.location" class="ghr-input" @input="dirty = true"></label>
        </div>
      </section>

      <section class="ghr-collapsible-panel" :class="{ 'ghr-collapsible-panel--closed': !openSections.targeting }">
        <button class="ghr-panel-toggle" type="button" :aria-expanded="openSections.targeting" @click="toggleSection('targeting')">
          <ChevronDown class="ghr-section__chevron" :size="18" aria-hidden="true" />
          <Target :size="20" aria-hidden="true" />
          <span>
            <strong>Orientation métier</strong>
            <span class="ghr-muted">Métier principal identifié et pistes de positionnement.</span>
          </span>
        </button>
        <div v-show="openSections.targeting" class="ghr-panel-body ghr-form-grid ghr-form-grid--compact">
          <label class="ghr-field ghr-field--wide"><span>Métier principal</span><input v-model="profile.analysis.career_targeting.primary_job.label" class="ghr-input" @input="dirty = true"></label>
        </div>
      </section>

      <section class="ghr-collapsible-panel" :class="{ 'ghr-collapsible-panel--closed': !openSections.seniority }">
        <button class="ghr-panel-toggle" type="button" :aria-expanded="openSections.seniority" @click="toggleSection('seniority')">
          <ChevronDown class="ghr-section__chevron" :size="18" aria-hidden="true" />
          <BriefcaseBusiness :size="20" aria-hidden="true" />
          <span>
            <strong>Seniorité</strong>
            <span class="ghr-muted">Niveau estimé à partir des responsabilités et dates présentes dans le CV.</span>
          </span>
        </button>
        <div v-show="openSections.seniority" class="ghr-panel-body ghr-form-grid ghr-form-grid--compact">
          <label class="ghr-field"> <span>Niveau</span>
            <select v-model="profile.analysis.seniority.level" class="ghr-select" @change="dirty = true">
              <option value="junior">Junior</option>
              <option value="confirmed">Confirmé</option>
              <option value="senior">Senior</option>
              <option value="lead">Lead</option>
              <option value="manager">Manager</option>
              <option value="expert">Expert</option>
              <option value="unknown">Inconnu</option>
            </select>
          </label>
        </div>
      </section>

      <AnalysisListSection
        v-model:items="profile.analysis.experiences"
        title="Expériences professionnelles"
        description="Postes, missions et réalisations détectés dans le parcours professionnel."
        :fields="experienceFields"
        default-open
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.education"
        title="Formations"
        :default-open="false"
        description="Diplômes, écoles, certifications et formations extraits du CV."
        :fields="educationFields"
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.skills"
        title="Compétences"
        :default-open="false"
        description="Compétences techniques ou métier identifiées dans le parcours."
        :fields="skillFields"
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.tools"
        title="Outils"
        :default-open="false"
        description="Logiciels, plateformes, frameworks ou outils explicitement utilisés."
        :fields="toolFields"
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.know_how"
        title="Savoir-faire"
        :default-open="false"
        description="Capacités opérationnelles démontrées par les expériences."
        :fields="labelDescriptionFields"
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.soft_skills"
        title="Savoir-être"
        :default-open="false"
        description="Qualités comportementales suggérées par le CV. À relire avec prudence."
        :fields="labelDescriptionFields"
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.languages"
        title="Langues"
        :default-open="false"
        description="Langues mentionnées et niveau estimé lorsqu’il est indiqué."
        :fields="languageFields"
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.interests"
        title="Centres d’intérêt"
        :default-open="false"
        description="Informations personnelles non professionnelles éventuellement utiles au contexte candidat."
        :fields="interestFields"
        @changed="dirty = true"
      />
      <AnalysisListSection
        v-model:items="profile.analysis.warnings"
        title="Points à vérifier"
        :default-open="false"
        description="Éléments incertains, manquants ou ambigus détectés pendant l’analyse."
        :fields="warningFields"
        @changed="dirty = true"
      />
    </article>
  </main>
  </div>
</template>