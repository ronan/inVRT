import tailwindcss from "@tailwindcss/vite";
// @ts-check
import { defineConfig } from 'astro/config';
import { fileURLToPath } from 'url';
import { dirname } from 'path';


const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const srcdir = __dirname + '/src';

console.log(__dirname);

// https://astro.build/config
export default defineConfig({
  build: {
    inlineStylesheets: 'always',
  },
  base: '/workspaces/invrt/src/js/invrt-reporter/src',
  vite: {
    resolve: {
      alias : {
        "@/*": `./src/*`,
      }
    },
    plugins: [tailwindcss()],
    build: {
      emptyOutDir: false,
      // Inline any small assets (e.g. icon SVGs) as data: URIs.
      assetsInlineLimit: 1024 * 1024,
    },
  },
});
