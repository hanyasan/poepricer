import type { SitemapUrlInput } from '#sitemap/types'

interface LeagueRow {
  slug: string
  lastSnapshotAt: string | null
}

interface ItemsPage {
  data: { slug: string }[]
  meta: { lastPage: number }
}

/** Asks the sitemap module to add the English twin and the hreflang alternatives of an address. */
const withLocales = { _i18nTransform: true }

/** The largest page the items endpoint serves. */
const PER_PAGE = 100

/**
 * Pages of the leagues and their items for the sitemap. The static pages are found by
 * the sitemap module itself; the league and item addresses come from the API. A failed
 * API call fails the whole route, so the sitemap is never built from a partial list. Every
 * address is marked for the i18n transform, which adds its English twin.
 */
export default defineEventHandler(async (): Promise<SitemapUrlInput[]> => {
  const leagues = await $fetch<LeagueRow[]>('/api/leagues')
  const urls: SitemapUrlInput[] = []

  for (const league of leagues) {
    const lastmod = league.lastSnapshotAt ?? undefined

    urls.push(
      { loc: `/${league.slug}`, lastmod, ...withLocales },
      { loc: `/${league.slug}/items`, lastmod, ...withLocales },
      { loc: `/${league.slug}/market`, lastmod, ...withLocales },
    )

    let lastPage = 1

    for (let page = 1; page <= lastPage; page++) {
      const items = await $fetch<ItemsPage>(`/api/leagues/${league.slug}/items`, {
        query: { page, perPage: PER_PAGE },
      })

      lastPage = items.meta.lastPage
      for (const item of items.data) {
        urls.push(
          { loc: `/${league.slug}/items/${item.slug}`, lastmod, ...withLocales },
          { loc: `/${league.slug}/items/${item.slug}/prices`, lastmod, ...withLocales },
        )
      }
    }
  }

  return urls
})
