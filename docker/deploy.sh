#!/usr/bin/env bash
# Builds the production images on this machine and ships them to the server
# over SSH (no registry). The server only loads images and runs compose.
#
#   DEPLOY_HOST=user@server docker/deploy.sh
#
# DEPLOY_HOST       ssh target (required)
# DEPLOY_PATH       directory on the server, relative to its home (default: poepricer).
#                   It must contain .env.production; compose keeps the same project
#                   name, so volumes survive a change of directory.
# DEPLOY_PLATFORM   target architecture (default: linux/amd64)
set -euo pipefail

: "${DEPLOY_HOST:?Set DEPLOY_HOST, e.g. user@server}"
DEPLOY_PATH="${DEPLOY_PATH:-poepricer}"
export DEPLOY_PLATFORM="${DEPLOY_PLATFORM:-linux/amd64}"

cd "$(dirname "$0")/.."

IMAGE_TAG="$(git rev-parse --short HEAD)"
if ! git diff --quiet HEAD --; then
    IMAGE_TAG="${IMAGE_TAG}-dirty$(date +%H%M%S)"
    echo "Working tree has uncommitted changes, tagging as ${IMAGE_TAG}"
fi
export IMAGE_TAG

compose=(docker compose -f docker-compose.prod.yml -f docker-compose.build.yml)

echo "==> Building images (${IMAGE_TAG}, ${DEPLOY_PLATFORM})"
"${compose[@]}" build

echo "==> Preparing ${DEPLOY_HOST}:${DEPLOY_PATH}"
ssh "$DEPLOY_HOST" "mkdir -p '${DEPLOY_PATH}/docker/caddy'"
scp -q docker-compose.prod.yml "${DEPLOY_HOST}:${DEPLOY_PATH}/docker-compose.prod.yml"
scp -q docker/caddy/Caddyfile "${DEPLOY_HOST}:${DEPLOY_PATH}/docker/caddy/Caddyfile"
ssh "$DEPLOY_HOST" "test -f '${DEPLOY_PATH}/.env.production'" \
    || { echo "Missing ${DEPLOY_PATH}/.env.production on the server" >&2; exit 1; }

echo "==> Shipping images"
docker save \
    "poepricer-app:${IMAGE_TAG}" \
    "poepricer-nginx:${IMAGE_TAG}" \
    "poepricer-frontend:${IMAGE_TAG}" \
    | gzip | ssh "$DEPLOY_HOST" 'gunzip | docker load'

echo "==> Starting release ${IMAGE_TAG}"
ssh "$DEPLOY_HOST" "cd '${DEPLOY_PATH}' \
    && echo '${IMAGE_TAG}' > .deploy-tag \
    && IMAGE_TAG='${IMAGE_TAG}' docker compose -f docker-compose.prod.yml up -d --no-build --pull never --remove-orphans \
    && docker image prune -af --filter 'until=168h'"

echo "==> Done. Rollback: ssh ${DEPLOY_HOST} \"cd ${DEPLOY_PATH} && IMAGE_TAG=<old-tag> docker compose -f docker-compose.prod.yml up -d --no-build\""
