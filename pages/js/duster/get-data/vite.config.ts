import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vitejs.dev/config/
export default defineConfig({
  base: "./",
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@shared': fileURLToPath(new URL('../shared/src', import.meta.url)),
      '@assets': fileURLToPath(new URL('../shared/src/assets', import.meta.url))
    }
  },
  build: {
    rollupOptions: {
      output: [
        {
          entryFileNames: "[name].js",
          assetFileNames: "[name][extname]"
        } ]
    }
  }
})
