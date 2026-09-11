FROM node:22-alpine

WORKDIR /app

# Dependencies are installed inside the image so that the anonymous
# /app/node_modules volume in docker-compose.yml is seeded with Linux builds of
# the platform-specific binaries (esbuild, rollup) rather than the host's.
COPY package.json package-lock.json ./
RUN npm ci

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
