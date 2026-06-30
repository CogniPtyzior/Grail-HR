<!-- New profile page: manual shell creation or PDF-based creation. -->
<script setup lang="ts">
import { ArrowLeft, FileText, PenLine, UploadCloud } from 'lucide-vue-next';

const api = useApi();
const mode = ref<'cv' | 'manual'>('cv');
const file = ref<File | null>(null);
const title = ref('');
const loading = ref(false);
const error = ref('');
const progressMessage = ref('');
const isDragging = ref(false);
const analysisStatus = ref({ openai_configured: true, analyses_per_hour: 10, remaining_analyses: 10 });
const analysisDisabledReason = computed(() => {
  if (!analysisStatus.value.openai_configured) return 'L’analyse IA n’est pas configurée. Contactez l’administrateur du site.';
  if (analysisStatus.value.remaining_analyses <= 0) return 'Limite atteinte. Réessayez plus tard ou contactez l’administrateur du site.';

  return '';
});
const selectedFileLabel = computed(() => file.value ? `${file.value.name} — ${(file.value.size / 1024 / 1024).toFixed(2)} Mo` : 'Aucun fichier sélectionné');
const titleHelp = computed(() => title.value.trim() ? 'Ce nom sera utilisé si l’analyse IA ne peut pas déterminer un intitulé fiable.' : 'Optionnel, mais recommandé pour retrouver le profil si l’analyse échoue.');

onMounted(async () => {
  try {
    type AnalysisStatus = { openai_configured: boolean; analyses_per_hour: number; remaining_analyses: number };
    analysisStatus.value = await api.request<AnalysisStatus>('/analysis/status');
  } catch {
    analysisStatus.value.openai_configured = false;
  }
});

function selectFile(selectedFile: File | null) {
  file.value = selectedFile;

  if (selectedFile) {
    error.value = '';
  }
}

function onFile(event: Event) {
  selectFile((event.target as HTMLInputElement).files?.[0] || null);
}

function onDrop(event: DragEvent) {
  isDragging.value = false;
  selectFile(event.dataTransfer?.files?.[0] || null);
}

type ProfileCreationResponse = { id?: number; data?: { id?: number }; profile?: { id?: number } };

function createdProfileId(response: ProfileCreationResponse | unknown): number | null {
  const payload = response as ProfileCreationResponse;
  const id = payload?.id ?? payload?.data?.id ?? payload?.profile?.id;
  const numericId = Number(id);

  return Number.isFinite(numericId) && numericId > 0 ? numericId : null;
}

async function goToCreatedProfile(response: ProfileCreationResponse | unknown) {
  const id = createdProfileId(response);

  if (!id) {
    throw new Error('Profil créé, mais la fiche n’a pas pu être ouverte automatiquement. Revenez à la liste des profils.');
  }

  progressMessage.value = 'Profil créé, ouverture de la fiche…';
  await navigateTo(`/profiles/${id}`);
}

async function create() {
  loading.value = true;
  error.value = '';
  progressMessage.value = '';

  try {
    if (mode.value === 'manual') {
      progressMessage.value = 'Création du profil…';
      const profile = await api.request<ProfileCreationResponse>('/profiles', { method: 'POST', body: JSON.stringify({ title: title.value }) });
      await goToCreatedProfile(profile);
      return;
    }

    if (!file.value) throw new Error('Importez un fichier PDF avant de lancer l’analyse.');
    if (file.value.type !== 'application/pdf') throw new Error('Format non supporté. Importez un fichier PDF uniquement.');
    if (file.value.size > 5 * 1024 * 1024) throw new Error('Le fichier est trop volumineux. La taille maximale est de 5 Mo.');

    const body = new FormData();
    body.append('cv', file.value);
    if (title.value.trim()) body.append('title', title.value.trim());
    progressMessage.value = 'Analyse du CV et création du profil…';
    const profile = await api.request<ProfileCreationResponse>('/profiles/from-cv', { method: 'POST', body });
    await goToCreatedProfile(profile);
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Création impossible.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div>
    <AppHeader />
    <main class="ghr-page">
      <NuxtLink class="ghr-back-link" to="/profiles"><ArrowLeft :size="16" aria-hidden="true" /> Retour aux profils</NuxtLink>

      <section class="ghr-card ghr-create-card">
        <header class="ghr-page-header ghr-page-header--compact">
          <div>
            <p class="ghr-eyebrow">Nouveau profil</p>
            <h1>Créer un profil</h1>
            <p class="ghr-muted">Créez une fiche manuelle ou importez un CV PDF pour générer une analyse structurée à relire.</p>
          </div>
        </header>

        <div class="ghr-segmented" role="tablist" aria-label="Mode de création du profil">
          <button
            type="button"
            class="ghr-segmented__button"
            :class="{ 'ghr-segmented__button--active': mode === 'cv' }"
            role="tab"
            :aria-selected="mode === 'cv'"
            @click="mode = 'cv'"
          >
            <FileText :size="16" aria-hidden="true" /> Depuis un CV
          </button>
          <button
            type="button"
            class="ghr-segmented__button"
            :class="{ 'ghr-segmented__button--active': mode === 'manual' }"
            role="tab"
            :aria-selected="mode === 'manual'"
            @click="mode = 'manual'"
          >
            <PenLine :size="16" aria-hidden="true" /> Manuellement
          </button>
        </div>

        <div v-if="mode === 'cv'" class="ghr-upload-panel">
          <label class="ghr-field">
            <span>Nom du profil</span>
            <input v-model="title" class="ghr-input" placeholder="Ex. Jeanne Martin — Responsable paie">
            <small class="ghr-help-text">{{ titleHelp }}</small>
          </label>

          <label
            class="ghr-upload-box"
            :class="{ 'ghr-upload-box--dragging': isDragging }"
            @dragenter.prevent="isDragging = true"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
          >
            <UploadCloud :size="32" aria-hidden="true" />
            <span class="ghr-upload-box__title">Choisir un CV PDF</span>
            <span class="ghr-muted">Déposez le fichier ici ou cliquez pour le sélectionner.</span>
            <input class="ghr-upload-box__input" type="file" accept="application/pdf" @change="onFile">
          </label>
          <p class="ghr-file-label">{{ selectedFileLabel }}</p>
          <p class="ghr-muted">Formats acceptés : PDF uniquement — taille maximale : 5 Mo.</p>
        </div>

        <div v-else class="ghr-form">
          <label class="ghr-field">
            <span>Nom ou titre du profil</span>
            <input v-model="title" class="ghr-input" placeholder="Ex. Profil CV du jour">
          </label>
        </div>

        <p v-if="error" role="alert" class="ghr-alert ghr-alert--error">{{ error }}</p>
        <p v-if="mode === 'cv' && analysisDisabledReason" class="ghr-alert ghr-alert--warning">
          {{ analysisDisabledReason }}
        </p>
        <p v-if="mode === 'cv'" class="ghr-muted">
          Limite actuelle : {{ analysisStatus.analyses_per_hour }} analyses par utilisateur par heure.
        </p>
        <p v-if="loading && progressMessage" class="ghr-progress-message">{{ progressMessage }}</p>
        <ol v-if="mode === 'cv' && loading" class="ghr-progress-list" aria-label="Progression de l’analyse">
          <li>Création du profil</li>
          <li>Envoi temporaire du fichier</li>
          <li>Extraction du texte</li>
          <li>Analyse IA</li>
          <li>Vérification du résultat</li>
        </ol>

        <div class="ghr-actions-row">
          <NuxtLink class="ghr-button ghr-button--secondary" to="/profiles">Annuler</NuxtLink>
          <button
            class="ghr-button ghr-button--primary"
            type="button"
            :disabled="loading || (mode === 'cv' && analysisDisabledReason !== '')"
            :title="mode === 'cv' ? analysisDisabledReason : ''"
            @click="create"
          >
            {{ loading ? 'Création…' : mode === 'cv' ? 'Analyser et créer le profil' : 'Créer le profil manuel' }}
          </button>
        </div>
      </section>
    </main>
  </div>
</template>