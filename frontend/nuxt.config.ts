/**
 * routeRules are compiled into the server build, so the Laravel address is
 * read from the environment when `nuxt dev` starts or `nuxt build` runs
 * (in production it is a build argument of the frontend image).
 */
const apiBase = process.env.NUXT_API_BASE ?? 'http://app:8000'

const apiProxyRule = { proxy: `${apiBase}/api/**` } as Record<string, unknown>

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  future: { compatibilityVersion: 4 },
  ssr: true,
  devtools: { enabled: false },
  modules: ['@nuxt/ui', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],
  icon: {
    // Outside /api, which is proxied to Laravel.
    localApiEndpoint: '/_nuxt_icon',
    // The scanner skips node_modules by default, so point it at the file where
    // Nuxt UI declares its default icons in addition to the app's own sources.
    clientBundle: {
      scan: {
        globInclude: ['app/**/*.vue', 'node_modules/@nuxt/ui/dist/shared/*.mjs'],
        globExclude: ['.nuxt', '.output'],
      },
    },
  },
  nitro: {
    routeRules: {
      '/api/**': apiProxyRule,
    },
  },
  typescript: { strict: true },
})
