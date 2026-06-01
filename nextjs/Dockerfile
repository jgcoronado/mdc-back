FROM node:22-alpine AS builder
WORKDIR /app
ENV NODE_OPTIONS=--max-old-space-size=768

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

FROM node:22-alpine
WORKDIR /app

ENV NODE_ENV=production
ENV PORT=3000

COPY --from=builder /app/public ./public
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static

EXPOSE 3000

USER node

CMD ["node", "server.js"]
