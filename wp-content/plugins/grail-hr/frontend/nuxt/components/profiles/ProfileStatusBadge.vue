<!-- User-facing status badges. Review status is primary; analysis status is shown only when it needs attention. -->
<script setup lang="ts">
const props = defineProps<{ reviewStatus: string; analysisStatus: string }>();

function reviewLabel(reviewStatus: string) {
  if (reviewStatus === 'validated') return 'Validé';
  if (reviewStatus === 'archived') return 'Archivé';
  if (reviewStatus === 'edited') return 'Modifié';
  return 'À relire';
}

function analysisLabel(analysisStatus: string): string {
  if (analysisStatus === 'error') return 'Analyse en erreur';
  if (analysisStatus === 'none') return 'Sans analyse';
  if (['pending', 'extracting', 'analyzing', 'validating'].includes(analysisStatus)) return 'Analyse en cours';
  return '';
}

const secondaryAnalysisLabel = computed(() => analysisLabel(props.analysisStatus));
const reviewBadgeClass = computed(() => ({
  'ghr-badge--archived': props.reviewStatus === 'archived',
  'ghr-badge--validated': props.reviewStatus === 'validated',
}));
const analysisBadgeClass = computed(() => ({
  'ghr-badge--analysis-error': props.analysisStatus === 'error',
  'ghr-badge--secondary': props.analysisStatus !== 'error',
}));
</script>

<template>
  <span class="ghr-status-badges">
    <span class="ghr-badge" :class="reviewBadgeClass">{{ reviewLabel(reviewStatus) }}</span>
    <span v-if="secondaryAnalysisLabel" class="ghr-badge" :class="analysisBadgeClass">{{ secondaryAnalysisLabel }}</span>
  </span>
</template>