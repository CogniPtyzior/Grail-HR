<!-- Replaces the current analysis of an existing profile while keeping the WordPress profile post. -->
<script setup lang="ts">
import { UploadCloud } from 'lucide-vue-next';
import AppHeader from '~/components/layout/AppHeader.vue';
import ProfileStatusBadge from '~/components/profiles/ProfileStatusBadge.vue';

const route = useRoute();
const api = useApi();
const file = ref<File | null>(null);
const loading = ref(false);
const error = ref('');
const isDragging = ref(false);
const analysisStatus = ref({ openai_configured: true, analyses_per_hour: 10, remaining_analyses: 10 });

onMounted(async () => {
  try {
    type AnalysisStatus = { openai_configured: boolean; analyses_per_hour: number; remaining_analyses: number };
    analysisStatus.value = await api.request<AnalysisStatus>('/analysis/status');
  } catch {
    analysisStatus.value.openai_configured = false;
  }
});

const analysisDisabledReason = computed(() => {
  if (!analysisStatus.value.openai_configured) return 'L’analyse IA n’est pas configurée. Contactez l’administrateur du site.';
  if (analysisStatus.value.remaining_analyses <= 0) return 'Limite atteinte. Réessayez plus tard ou contactez l’administrateur du site.';

  return '';
});
const selectedFileLabel = computed(() => file.value ? `${file.value.name} — ${(file.value.size / 1024 / 1024).toFixed(2)} Mo` : 'Aucun fichier sélectionné');

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

async function replace() {
  if (!file.value) {
    error.value = 'Importez un fichier PDF avant de lancer l’analyse.';
    return;
  }

  if (file.value.type !== 'application/pdf') {
    error.value = 'Format non supporté. Importez un fichier PDF uniquement.';
    return;
  }

  if (file.value.size > 5 * 1024 * 1024) {
    error.value = 'Le fichier est trop volumineux. La taille maximale est de 5 Mo.';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const body = new FormData();
    body.append('cv', file.value);
    await api.request(`/profiles/${route.params.id}/replace-analysis`, { method: 'POST', body });
    await navigateTo(`/profiles/${route.params.id}`);
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Remplacement impossible.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div>
    <AppHeader />
    <main class="ghr-page">
      <section class="ghr-card">
        <NuxtLink :to="`/profiles/${route.params.id}`">← Retour au profil</NuxtLink>
        <h1>Remplacer l’analyse du profil</h1>
        <p class="ghr-muted">Le profil sera conservé, mais les données extraites seront remplacées par celles du nouveau CV.</p>
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
        <p v-if="error" role="alert" class="ghr-error">{{ error }}</p>
        <p v-if="analysisDisabledReason" class="ghr-muted">{{ analysisDisabledReason }}</p>
        <button
          class="ghr-button ghr-button--primary"
          :disabled="loading || analysisDisabledReason !== ''"
          :title="analysisDisabledReason"
          @click="replace"
        >
          {{ loading ? 'Analyse en cours…' : 'Analyser et remplacer l’analyse' }}
        </button>
      </section>
    </main>
  </div>
</template>