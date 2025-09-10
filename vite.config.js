import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'assets/js/dashboard',
    rollupOptions: {
      output: {
        entryFileNames: 'dashboard.js',
        chunkFileNames: 'dashboard-[hash].js',
        assetFileNames: 'dashboard-[hash].[ext]'
      }
    }
  },
  server: {
    port: 3000,
    host: true
  }
})