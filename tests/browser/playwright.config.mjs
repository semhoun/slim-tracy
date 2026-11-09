import { defineConfig } from '@playwright/test';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { buildSync } from 'esbuild';

const storage = mkdtempSync(join(tmpdir(), 'slim-tracy-browser-'));
const bundle = join(storage, 'frameworks.js');
buildSync({ entryPoints: ['tests/http/frameworks.js'], outfile: bundle, bundle: true, format: 'iife', define: { 'process.env.NODE_ENV': '"development"' } });
const server = (port, mode) => ({
  command: `php -S 127.0.0.1:${port} tests/http/${mode === 'frontend' ? 'frontend' : 'api'}.php`,
  cwd: process.cwd(),
  url: `http://127.0.0.1:${port}/health`,
  env: { FIXTURE_MODE: mode, FIXTURE_STORAGE: storage, FIXTURE_BUNDLE: bundle, ...(mode === 'frontend' ? {} : { PHP_CLI_SERVER_WORKERS: '4' }) },
  reuseExistingServer: false,
});

export default defineConfig({
  testDir: '.',
  testMatch: '*.spec.mjs',
  outputDir: join(tmpdir(), 'slim-tracy-playwright-results'),
  fullyParallel: false,
  workers: 1,
  use: { browserName: 'chromium', headless: true },
  webServer: [server(8781, 'enabled'), server(8782, 'frontend'), server(8783, 'disabled'), server(8784, 'production'), server(8785, 'enabled')],
});
