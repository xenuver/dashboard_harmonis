import react from '@vitejs/plugin-react'
import { defineConfig, type PluginOption } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import path from "path"
import { visualizer } from "rollup-plugin-visualizer";

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    visualizer({
      // filename: 'chunk-stats.html',
      // emitFile: true,
      // template: 'treemap',
    }) as PluginOption,
  ],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  build: {
    rolldownOptions: {
      output: {
        codeSplitting: {
          groups: [
            {
              name: 'react-runtime',
              test: /node_modules\/(react|react-dom|scheduler)\//,
              priority: 30,
            },
            {
              name: 'react-router',
              test: /node_modules\/(react-router|react-router-dom)\//,
              priority: 30,
            },
            {
              name: 'recharts-plugin',
              test: /node_modules\/(recharts)\//,
              priority: 30,
            },
            {
              name: 'base-ui-plugin',
              test: /node_modules\/(@base-ui)\//,
              priority: 30,
            },
            {
              name: 'lodash-plugin',
              test: /node_modules\/(lodash)\//,
              priority: 30,
            },
            {
              name: 'plugins',
              test: /node_modules\//,
              priority: 10,
            },
          ],
        },
      },
    },
  },
})
