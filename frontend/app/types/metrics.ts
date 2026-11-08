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

export interface ItemMetricRow {
  slug: string
  name: string
  price: number
  logReturn: number | null
  residual: number | null
  residualMad: number | null
  beta: number
  observations: number
  /** Items traded per hour; `null` when the volume is unknown. */
  units: number | null
  /** Trust in the return from 0 to 1; `null` when the volume is unknown. */
  returnWeight: number | null
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
    marketReturn12h: number | null
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
