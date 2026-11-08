export type Game = 'poe1' | 'poe2'

export type MetricPeriod = '24h' | '7d' | 'league'

export interface League {
  game: Game
  slug: string
  name: string
  isActive: boolean
  lastSnapshotAt: string | null
  /** Items whose return is reliable enough to count in the market return. */
  basketSize: number
  /** All items of the basket. */
  basketTotal: number
  marketReturnSeries: SeriesPoint[]
}

/** A row of the items table of a league, see `.claude-spec/api-items.md`. */
export interface ItemMetricRow {
  slug: string
  name: string
  category: string | null
  /** Price in the selected currency; `null` when the item has no metrics in the last bucket. */
  price: number | null
  logReturn: number | null
  residual: number | null
  residualMad: number | null
  beta: number | null
  observations: number
  /** Items traded per hour; `null` when the volume is unknown. */
  units: number | null
  /** Trust in the return from 0 to 1; `null` when the volume is unknown. */
  returnWeight: number | null
  /** Too few observations for the metrics to be trusted. */
  lowData: boolean
  /** `|residual| > 2 × residualMad`, computed on the server. */
  isUnusual: boolean
  /** Prices of the last 7 days, oldest first. */
  sparkline: SeriesPoint[]
}

export interface PaginatedMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

export interface ItemsPageMeta extends PaginatedMeta {
  /** Currency the page is measured in; `null` when the league has no metric currencies. */
  currency: string | null
  /** Start of the bucket the metrics are taken from; `null` when none is calculated yet. */
  lastBucket: string | null
}

export interface ItemsPage {
  data: ItemMetricRow[]
  meta: ItemsPageMeta
}

export interface ItemCategories {
  categories: string[]
  /** Whether the league has items without a category. */
  hasUncategorized: boolean
}

export interface SeriesPoint {
  bucket: string
  value: number
}

/**
 * A currency the league metrics are measured in; `slug` is its trade id and the
 * value of the `currency` query parameter.
 */
export interface LeagueCurrency {
  slug: string
  name: string
}

export interface ItemMove {
  slug: string
  name: string
  price: number
  logReturn: number
}

export interface UnusualMove extends ItemMove {
  residual: number
  residualMad: number
  ratio: number
  beta: number
  observations: number
  units: number | null
  returnWeight: number | null
}

export interface LeagueOverview {
  currency: string | null
  period: MetricPeriod
  lastBucket: string | null
  kpi: {
    marketReturnLastBucket: number | null
    marketReturn7d: number | null
    basketSize: number
    basketTotal: number
    lastSnapshotAt: string | null
  }
  marketReturnSeries: SeriesPoint[]
  unusualMoves: UnusualMove[]
  topGainers: ItemMove[]
  topLosers: ItemMove[]
}
