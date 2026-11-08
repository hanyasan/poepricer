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

/** A bucket or beta moment as a short label of a chart axis. */
export function formatBucketLabel(moment: string): string {
  return dateTimeFormatter().format(new Date(moment))
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
