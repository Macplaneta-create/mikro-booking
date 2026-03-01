import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react-swc';
import path from 'path';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    outDir: '../assets/admin',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        format: 'iife',
        entryFileNames: 'index.js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
    // Generate manifest for WordPress integration
    manifest: true,
  },
  server: {
    port: 3000,
    host: true, // Allow external access (needed for Laragon)
    origin: 'http://localhost:3000',
    cors: true,
    hmr: {
      protocol: 'ws',
      host: 'localhost',
      port: 3000,
    },
    proxy: {
      '/wp-json': {
        target: 'http://gorytajemnic.test', // Your Laragon domain
        changeOrigin: true,
        secure: false,
      },
    },
  },
});
