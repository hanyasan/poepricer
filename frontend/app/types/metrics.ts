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

export interface MarketBreadth {
  up: number
  down: number
  flat: number
}

/** A bar of the return histogram; `from` is `null` for the lower tail, `to` for the upper one. */
export interface MarketBin {
  from: number | null
  to: number | null
  count: number
}

export interface MarketCategory {
  /** `null` for the items without a category. */
  category: string | null
  median: number
  count: number
}

export interface MarketItem {
  slug: string
  name: string
  category: string | null
  price: number
  logReturn: number
  units: number | null
  returnWeight: number | null
}

/** Distribution of the item returns of one bucket, see `.claude-spec/api-market.md`. */
export interface MarketDistribution {
  currency: string | null
  bucket: string | null
  /** Starts of the latest buckets, newest first. */
  buckets: string[]
  marketReturn: number | null
  median: number | null
  breadth: MarketBreadth
  bins: MarketBin[]
  byCategory: MarketCategory[]
  items: MarketItem[]
}

/** Description of an item on its page, see `.claude-spec/api-item-detail.md`. */
export interface ItemInfo {
  slug: string
  name: string
  category: string | null
}

/** Metrics of an item in the last bucket. */
export interface ItemCurrent {
  price: number
  logReturn: number | null
  residual: number | null
  residualMad: number | null
  observations: number
  units: number | null
  returnWeight: number | null
  lowData: boolean
  isUnusual: boolean
}

/** One recalculation of the beta of an item. */
export interface ItemBeta {
  calculatedAt: string
  beta: number
  rawBeta: number
  rSquared: number | null
  observations: number
}

export interface ItemSummary {
  item: ItemInfo
  /** `null` when the league has no metric currencies. */
  currency: string | null
  lastBucket: string | null
  /** `null` when the item has no metrics in the last bucket. */
  current: ItemCurrent | null
  /** `null` when the beta was never calculated. */
  beta: ItemBeta | null
}

/** A point of the item charts. */
export interface ItemSeriesPoint {
  bucket: string
  price: number
  residual: number | null
  residualMad: number | null
}

export interface ItemHistory {
  currency: string | null
  period: MetricPeriod
  lastBucket: string | null
  series: ItemSeriesPoint[]
  /** Recalculations of the beta within the period, oldest first. */
  betaHistory: ItemBeta[]
}

/** A row of the table of the item values by bucket. */
export interface ItemBucketRow {
  bucket: string
  price: number
  logReturn: number | null
  residual: number | null
  residualMad: number | null
  observations: number
  units: number | null
  returnWeight: number | null
}

export interface ItemBucketsMeta extends PaginatedMeta {
  currency: string | null
  period: MetricPeriod
}

export interface ItemBucketsPage {
  data: ItemBucketRow[]
  meta: ItemBucketsMeta
}
