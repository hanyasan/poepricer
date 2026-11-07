export type Game = 'poe1' | 'poe2'

export type MetricCurrency = 'divine' | 'chaos' | 'exalted'

export type MetricPeriod = '24h' | '7d' | 'league'

export interface League {
  game: Game
  slug: string
  name: string
  isActive: boolean
  lastSnapshotAt: string | null
  basketSize: number
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
}

export interface SeriesPoint {
  bucket: string
  value: number
}
