/**
 * Makes sure the leagues are in the store: they are fetched once per page
 * render on the server and reach the browser through the Pinia payload.
 */
export async function useLeagues() {
  const store = useLeaguesStore()

  await callOnce('leagues', () => store.load())

  const { items: leagues, status } = storeToRefs(store)

  return { leagues, status, refresh: store.load }
}

/**
 * A single league of the main list by its slug; `undefined` when the league
 * is not part of it.
 */
export async function useLeague(slug: MaybeRefOrGetter<string>) {
  const store = useLeaguesStore()

  await callOnce('leagues', () => store.load())

  const league = computed(() => store.bySlug(toValue(slug)))

  return { league }
}
