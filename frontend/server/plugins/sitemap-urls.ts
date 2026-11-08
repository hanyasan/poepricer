import type { SitemapUrlInput } from '#sitemap/types'

/** How long the list of addresses is reused between the sitemaps of the languages. */
const REUSE_MS = 60_000

let pending: { at: number; urls: Promise<SitemapUrlInput[]> } | null = null

/**
 * The module swallows the errors of its own sources and answers 200 with whatever it
 * has, and then caches that answer. The addresses are therefore loaded here, in the hook
 * that runs before the sources are read: an error thrown here makes the sitemap answer 500
 * and nothing is cached, so a crawler retries instead of getting an empty file.
 * The route is reached through the Nitro fetch, outside the /api proxy.
 */
function loadUrls(): Promise<SitemapUrlInput[]> {
  if (pending === null || Date.now() - pending.at > REUSE_MS) {
    const urls = $fetch<SitemapUrlInput[]>('/_sitemap-urls')

    pending = { at: Date.now(), urls }
    urls.catch(() => {
      pending = null
    })
  }

  return pending.urls
}

export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('sitemap:sources', async (context) => {
    context.sources.push({ urls: await loadUrls() })
  })
})
