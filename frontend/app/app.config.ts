export default defineAppConfig({
  ui: {
    colors: {
      // Nuxt UI paints highlights with the primary color, green by default.
      primary: 'sky',
    },
    skeleton: {
      // Between the page and the default fill (bg-elevated), which is too light on the dark theme.
      base: 'dark:bg-[oklch(0.24_0.04_262)]',
    },
  },
})
