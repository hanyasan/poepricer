/**
 * Liveness of the Nitro server itself, independent of Laravel: the container
 * healthcheck uses it so that a slow or restarting backend does not mark the
 * frontend unhealthy (and block Caddy through `depends_on`).
 */
export default defineEventHandler(() => 'ok')
