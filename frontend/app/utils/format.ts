const priceFormatter = new Intl.NumberFormat('ru-RU', { maximumSignificantDigits: 4 })

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
  return priceFormatter.format(value)
}

/** An unsigned percent for a scale such as a MAD: 0.0049 becomes "0.49%". */
export function formatPercent(value: number): string {
  return `${(value * 100).toFixed(2)}%`
}

/** A value that may be missing, shown as a dash. */
export function orDash<T>(value: T | null, format: (value: T) => string): string {
  return value === null ? '—' : format(value)
}
