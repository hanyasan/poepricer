/**
 * Name of the league of the current route for the titles of the pages: the league of
 * the main list, or its slug when the league is not part of it.
 */
export function useLeagueName() {
  const route = useRoute()
  const store = useLeaguesStore()

  const league = computed(() => store.bySlug(route.params.league as string))
  const leagueName = computed(() => league.value?.name ?? (route.params.league as string))

  return { league, leagueName }
}
