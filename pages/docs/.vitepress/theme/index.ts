import DefaultTheme from 'vitepress/theme';
import type { Theme } from 'vitepress';
import BladePreview from './components/BladePreview.vue';

export default {
  extends: DefaultTheme,
  enhanceApp({ app }) {
    app.component('BladePreview', BladePreview);
  },
} satisfies Theme;
