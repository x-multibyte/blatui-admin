import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@blatui': path.resolve(dirname, 'vendor/anousss007/blatui/stubs/foundations'),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        admin: path.resolve(dirname, 'resources/js/admin.js'),
      },
      output: {
        entryFileNames: 'admin.js',
        // Never collide with a constant name: split chunks keep their own hashed names.
        chunkFileNames: 'admin-[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'admin.css';
          }
          return '[name][extname]';
        },
      },
    },
  },
});
