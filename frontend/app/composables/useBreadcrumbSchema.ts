export interface BreadcrumbStep {
  name: string
  /** Path without the language prefix; the last step may omit it, it is the current page. */
  path?: string
}

/**
 * Structured data of the breadcrumbs for the search engines: the trail from the main
 * page to the current one. The paths get the prefix of the current language.
 */
export function useBreadcrumbSchema(steps: MaybeRefOrGetter<BreadcrumbStep[]>) {
  const localePath = useLocalePath()

  useSchemaOrg(
    computed(() => [
      defineBreadcrumb({
        itemListElement: toValue(steps).map((step) => ({
          name: step.name,
          item: step.path === undefined ? undefined : localePath(step.path),
        })),
      }),
    ]),
  )
}
