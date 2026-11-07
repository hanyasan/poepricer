# Development: `nuxt dev` with the sources bind-mounted by docker-compose.yml.
# Production: the Nitro server that renders the pages (SSR) and proxies /api to Laravel.
FROM node:24-alpine AS base

WORKDIR /app

FROM base AS development

ENV HOST=0.0.0.0 \
    PORT=3000

EXPOSE 3000 24678

CMD ["sh", "-c", "npm ci && exec npm run dev"]

FROM base AS build

# routeRules are compiled into the server build, so the Laravel address has to
# be known here rather than at container start.
ARG NUXT_API_BASE=http://nginx
ENV NUXT_API_BASE=${NUXT_API_BASE}

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

FROM base AS production

ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=3000

COPY --from=build --chown=node:node /app/.output ./.output

USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
