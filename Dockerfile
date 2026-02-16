FROM node:22-alpine AS frontend-build
WORKDIR /frontend
ENV NODE_OPTIONS=--max-old-space-size=768

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

FROM node:22-alpine
WORKDIR /app

COPY package*.json ./
RUN npm ci --omit=dev

COPY . .
COPY --from=frontend-build /frontend/dist ./public

ENV NODE_ENV=production
ENV APP_PORT=80

EXPOSE 80

USER node

CMD ["node", "index.js"]
