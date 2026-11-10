const DEFAULT_LANGUAGE = 'ru-RU'

/**
 * Language tag of the current locale for `Intl`. The composables of the app are not
 * reachable from every place the formatters are called from, so the tag is taken from
 * the Nuxt app when there is one and falls back to the default language otherwise.
 */
export function intlLanguage(): string {
  return tryUseNuxtApp()?.$i18n.localeProperties.value.language ?? DEFAULT_LANGUAGE
}

const dateFormatters = new Map<string, Intl.DateTimeFormat>()
const priceFormatters = new Map<string, Intl.NumberFormat>()
const timeFormatters = new Map<string, Intl.DateTimeFormat>()

/** A short date with the time, in the time zone of the browser; the same one for the whole app. */
export function dateTimeFormatter(language: string = intlLanguage()): Intl.DateTimeFormat {
  let formatter = dateFormatters.get(language)

  if (formatter === undefined) {
    formatter = new Intl.DateTimeFormat(language, {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
    dateFormatters.set(language, formatter)
  }

  return formatter
}

/** A log return as a signed percent: 0.0132 becomes "+1.32%". */
export function formatReturn(value: number): string {
  return `${value > 0 ? '+' : ''}${(value * 100).toFixed(2)}%`
}

export function returnClass(value: number | null): string {
  if (value === null || value === 0) return 'text-muted'
  return value > 0 ? 'text-success' : 'text-error'
}

/** Items traded per hour; an unknown volume is shown as a dash. */
export function formatUnits(value: number | null): string {
  if (value === null) return '—'
  return value < 10 ? value.toFixed(1) : Math.round(value).toString()
}

/** Muted style for a return that is only partly trusted because the item is rarely traded. */
export function weightClass(weight: number | null): string {
  return weight !== null && weight < 1 ? 'text-muted' : ''
}

export function formatPrice(value: number): string {
  const language = intlLanguage()
  let formatter = priceFormatters.get(language)

  if (formatter === undefined) {
    formatter = new Intl.NumberFormat(language, { maximumSignificantDigits: 4 })
    priceFormatters.set(language, formatter)
  }

  return formatter.format(value)
}

/** An unsigned percent for a scale such as a MAD: 0.0049 becomes "0.49%". */
export function formatPercent(value: number): string {
  return `${(value * 100).toFixed(2)}%`
}

/** A value that may be missing, shown as a dash. */
export function orDash<T>(value: T | null, format: (value: T) => string): string {
  return value === null ? '—' : format(value)
}

/** A beta or another estimate shown with two decimals: 1.7972 becomes "1.80". */
export function formatCoefficient(value: number): string {
  return value.toFixed(2)
}

/** How many usual fluctuations a residual makes: 2.94 becomes "2.9×". */
export function formatRatio(value: number): string {
  return `${value.toFixed(1)}×`
}

/** A bucket or beta moment as a short label of a chart axis. */
export function formatBucketLabel(moment: string): string {
  return dateTimeFormatter().format(new Date(moment))
}

const BUCKET_HOURS = 12

/** A bucket is the 12 hours from its start; the end drops the date when it falls on the same day: "10.11, 00:00 – 12:00". */
export function formatBucketRange(start: string): string {
  const from = new Date(start)
  const to = new Date(from.getTime() + BUCKET_HOURS * 3_600_000)
  const sameDay = from.toDateString() === to.toDateString()
  const end = sameDay
    ? new Intl.DateTimeFormat(intlLanguage(), { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(to)
    : dateTimeFormatter().format(to)

  return `${dateTimeFormatter().format(from)} – ${end}`
}

/** The edge of a histogram bar as a signed percent without a trailing zero: 0.05 becomes "+5%". */
export function formatBinEdge(value: number): string {
  const percent = Number((value * 100).toFixed(2))

  return `${percent > 0 ? '+' : ''}${percent}%`
}

/** Whether a return falls into a bar of the histogram: the lower edge is in, the upper one is out. */
export function binContains(bin: { from: number | null; to: number | null }, value: number): boolean {
  return (bin.from === null || value >= bin.from) && (bin.to === null || value < bin.to)
}

/**
 * A moment of the time axis of a chart: the time of day for a window of a day or shorter,
 * the date with the time for a longer one.
 */
export function formatChartTime(moment: number, windowHours: number): string {
  if (windowHours > 24) return dateTimeFormatter().format(moment)

  const language = intlLanguage()
  let formatter = timeFormatters.get(language)

  if (formatter === undefined) {
    formatter = new Intl.DateTimeFormat(language, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
    timeFormatters.set(language, formatter)
  }

  return formatter.format(moment)
}
