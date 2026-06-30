export default defineNuxtConfig({
  ssr: false,

  modules: ['@pinia/nuxt', '@nuxt/eslint'],

  css: ['~/assets/css/main.css'],

  app: {
    baseURL: '/',
    head: {
      title: 'Grail HR',
      meta: [
        { name: 'robots', content: 'noindex,nofollow' },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_GRAIL_HR_API_BASE
        || 'https://grail-hr.ddev.site/wp-json/grail-hr/v1',
    },
  },

  devtools: {
    enabled: true,
  },

  typescript: {
    strict: true,
  },
});