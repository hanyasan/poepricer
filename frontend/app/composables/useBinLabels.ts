import type { MarketBin } from '~/types/metrics'

/** Labels of the bars of the return histogram. */
export function useBinLabels() {
  const { t } = useI18n()

  /** Full description of the range of a bar. */
  function rangeLabel(bin: MarketBin): string {
    if (bin.from === null) return t('market.histogram.lowerTail', { to: formatBinEdge(bin.to ?? 0) })
    if (bin.to === null) return t('market.histogram.upperTail', { from: formatBinEdge(bin.from) })

    return t('market.histogram.between', { from: formatBinEdge(bin.from), to: formatBinEdge(bin.to) })
  }

  /** Short label of the axis: the lower edge of a bar, tails with a sign of their direction. */
  function axisLabel(bin: MarketBin): string {
    if (bin.from === null) return `< ${formatBinEdge(bin.to ?? 0)}`
    if (bin.to === null) return `≥ ${formatBinEdge(bin.from)}`

    return formatBinEdge(bin.from)
  }

  return { rangeLabel, axisLabel }
}
