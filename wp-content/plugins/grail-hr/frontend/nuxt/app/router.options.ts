import type { RouterConfig } from '@nuxt/schema';
import { createWebHashHistory } from 'vue-router';

export default {
  history: () => createWebHashHistory('/'),

  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    }

    return {
      top: 0,
      left: 0,
    };
  },
} satisfies RouterConfig;