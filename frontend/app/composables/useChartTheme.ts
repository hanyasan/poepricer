const AXIS_LINE = '#6E7079'
const GRID_LINE = '#E0E6F1'
const TEXT_LIGHT = '#464646'
const TEXT_DARK = '#E0E6F1'

/** Size of the point markers of the charts, big enough to tell the points apart. */
const SYMBOL_SIZE = 9

/**
 * Colors of the ECharts charts that follow the color mode of the page. On the dark
 * theme the grid lines are as dim as the axis line is on the light one and the other
 * way round, so the bright grid does not glare.
 */
export function useChartTheme() {
  const colorMode = useColorMode()
  const isDark = computed(() => colorMode.value === 'dark')

  /** Main line of a chart and the extra one; growth, fall and the selected bar of the bar charts. */
  const colors = { primary: '#5470c6', secondary: '#a1a1aa', up: '#3ba272', down: '#ee6666', selected: '#fac858' }

  /** The x axis line is the zero line of the charts that cross zero. */
  const xAxisLine = computed(() => ({ lineStyle: { color: isDark.value ? GRID_LINE : AXIS_LINE } }))

  /** Color of the text drawn on the plot, such as the label of a mark line. */
  const textColor = computed(() => (isDark.value ? TEXT_DARK : TEXT_LIGHT))

  const gridLine = computed(() => ({ lineStyle: { color: isDark.value ? AXIS_LINE : GRID_LINE } }))

  /** A solid point of the color of its line. */
  function marker(color: string) {
    return { color }
  }

  return { colors, symbolSize: SYMBOL_SIZE, xAxisLine, gridLine, textColor, marker }
}
