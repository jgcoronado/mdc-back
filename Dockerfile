FROM node:22-alpine AS frontend-build
WORKDIR /frontend
ENV NODE_OPTIONS=--max-old-space-size=768

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

FROM node:22-alpine AS app-build
WORKDIR /app

# Toolchain required to compile better-sqlite3 native bindings.
RUN apk add --no-cache python3 make g++ \
  && ln -sf python3 /usr/bin/python

COPY package*.json ./
RUN npm ci --omit=dev

FROM node:22-alpine
WORKDIR /app

COPY --from=app-build /app/node_modules ./node_modules
COPY . .
COPY --from=frontend-build /frontend/dist ./public

RUN mkdir -p /app/data && chown -R node:node /app/data

ENV NODE_ENV=production
ENV APP_PORT=80
ENV DB_PATH=/app/data/mdc.db

EXPOSE 80

USER node

CMD ["node", "index.js"]
