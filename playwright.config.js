const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/Browser',
  timeout: 60_000,
  expect: { timeout: 20_000 },
  reporter: [
    ['line'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],
  use: {
    baseURL: process.env.APP_URL || 'http://127.0.0.1:8000',
    viewport: { width: 1440, height: 900 },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
