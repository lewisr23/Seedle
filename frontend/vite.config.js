import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    // Pinned so this never races another project's dev server for 5173.
    // strictPort makes Vite fail loudly instead of quietly hopping to the next
    // free port, which silently breaks CORS against the API's allowed origins.
    port: 5180,
    strictPort: true,
    // Bind-mounted files don't emit filesystem events through Docker Desktop,
    // so inside a container the dev server only notices host edits if it polls.
    watch: process.env.DOCKER ? { usePolling: true, interval: 300 } : undefined,
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.js',
    css: false,
  },
})
