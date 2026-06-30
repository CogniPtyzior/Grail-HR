<script setup lang="ts">
onMounted(() => {
  const hashRoute = window.location.hash.match(/^#(\/.*)$/)?.[1] || '';
  navigateTo(hashRoute && hashRoute !== '/' ? hashRoute : '/profiles', { replace: true });
});
</script>

<template>
  <main class="ghr-page">
    <p class="ghr-muted">Chargement de Grail HR...</p>
  </main>
</template>