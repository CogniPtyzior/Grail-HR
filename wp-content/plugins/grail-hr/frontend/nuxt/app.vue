<!--
  Root component. It keeps the application scoped under .grail-hr-app so WordPress theme CSS does not own the UI.
-->
<script setup lang="ts">
if (import.meta.client) {
  const hash = window.location.hash || '';

  if (hash.startsWith('#/#/')) {
    const cleanedHash = hash.replace(/^#\/#\//, '#/');
    window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}${cleanedHash}`);
  }
}
</script>

<template>
  <div class="grail-hr-app ghr-app">
    <NuxtPage />
  </div>
</template>
