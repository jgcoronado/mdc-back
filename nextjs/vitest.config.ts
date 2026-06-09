import { defineConfig } from 'vitest/config';
import tsconfigPaths from 'vite-tsconfig-paths';

export default defineConfig({
  plugins: [tsconfigPaths()],
  test: {
    environment: 'node',
    globals: true,
    setupFiles: ['__tests__/setup.ts'],
    pool: 'forks',
    env: {
      SECRET_KEY: 'test-secret-key-for-testing-purposes-only-32plus',
      DB_PATH: ':memory:',
      NODE_ENV: 'test',
      COOKIE_SECURE: 'false',
    },
  },
});
