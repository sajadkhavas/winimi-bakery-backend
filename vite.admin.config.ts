import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/filament/admin/theme.css',
        'resources/js/filament/media-upload-optimizer.js',
      ],
      buildDirectory: 'build-admin',
      hotFile: 'storage/vite-admin.hot',
      refresh: false,
    }),
  ],
});
