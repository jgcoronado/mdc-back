FROM node:22-alpine AS builder
WORKDIR /app
ENV NODE_OPTIONS=--max-old-space-size=768

# better-sqlite3 needs build tools and libs at compile time.
RUN apk add --no-cache python3 make g++ gcc musl-dev linux-headers

COPY package*.json ./
RUN npm install --legacy-peer-deps

COPY . .
RUN npm run build

FROM node:22-alpine
WORKDIR /app

ENV NODE_ENV=production
ENV PORT=3000

# better-sqlite3 needs native libs at runtime.
RUN apk add --no-cache libstdc++ gcompat

COPY --from=builder /app/public ./public
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static
COPY --from=builder /app/node_modules ./node_modules

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD wget -qO- http://localhost:3000/api/health || exit 1

USER node

CMD ["node", "server.js"]
