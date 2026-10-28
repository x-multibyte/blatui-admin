import { defineConfig } from 'vitepress';

export default defineConfig({
  title: 'BlatUI Admin',
  description: 'An elegant, adaptive administration panel and lifecycle engine for Laravel, built on BlatUI.',
  base: '/blatui-admin/',
  cleanUrls: true,
  srcExclude: ['WRITING_GUIDE.md'],
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Architecture', link: '/guide/architecture' },
      {
        text: 'Packagist',
        link: 'https://packagist.org/packages/x-multibyte/blatui-admin',
      },
    ],
    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Getting Started', link: '/guide/getting-started' },
        ],
      },
      {
        text: 'Core Concepts',
        items: [
          { text: 'Architecture & Coexistence', link: '/guide/architecture' },
        ],
      },
    ],
    search: {
      provider: 'local',
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/x-multibyte/blatui-admin' },
    ],
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © Roy Thia (x-multibyte)',
    },
  },
});
