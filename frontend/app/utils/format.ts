const priceFormatter = new Intl.NumberFormat('ru-RU', { maximumSignificantDigits: 4 })

/** A log return as a signed percent: 0.0132 becomes "+1.32%". */
export function formatReturn(value: number): string {
  return `${value > 0 ? '+' : ''}${(value * 100).toFixed(2)}%`
}

export function returnClass(value: number | null): string {
  if (value === null || value === 0) return 'text-muted'
  return value > 0 ? 'text-success' : 'text-error'
}

export function formatPrice(value: number): string {
  return priceFormatter.format(value)
}
