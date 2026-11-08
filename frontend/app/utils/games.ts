import type { Game } from '~/types/metrics'

/** Product names of the games, the same in every language. */
const GAME_TITLES: Record<Game, string> = { poe1: 'Path of Exile', poe2: 'Path of Exile 2' }

export const GAMES = Object.keys(GAME_TITLES) as Game[]

export function gameTitle(game: Game): string {
  return GAME_TITLES[game]
}

/** The game a league slug starts with (`poe2-standard`); `undefined` for an unexpected slug. */
export function gameFromSlug(slug: string): Game | undefined {
  return GAMES.find((game) => slug.startsWith(`${game}-`))
}
