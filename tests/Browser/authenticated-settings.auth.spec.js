const { test, expect } = require('@playwright/test');
const { loginNextcloud, skipUnlessAuthEnabled } = require('./helpers/nextcloud-auth');

test.use({
  trace: 'off',
  screenshot: 'off',
  video: 'off',
});

test.describe('SakuraAlbum authenticated personal settings @auth', () => {
  test.beforeEach(() => {
    skipUnlessAuthEnabled();
  });

  test('albentest can open SakuraAlbum settings and read live status without writing', async ({ page }) => {
    await loginNextcloud(page);
    await page.goto('/settings/user/sakuraalbum', { waitUntil: 'domcontentloaded' });

    const appRoot = page.locator('#sakuraalbum-personal-settings');
    await expect(appRoot).toBeVisible({ timeout: 30000 });
    await expect(page.locator('.sakuraalbum-settings h2')).toHaveText('SakuraAlbum');
    await expect(page.getByRole('button', { name: 'Speichern und Hintergrundlauf vormerken' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Quellordner hinzufuegen' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Veraltete pruefen' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Konto-Reset pruefen' })).toBeVisible();

    const resetButton = page.locator('#ska-reset-preview');
    await expect(resetButton).toHaveCSS('color', 'rgb(255, 255, 255)');
    await expect(resetButton).toHaveCSS('background-color', 'rgb(180, 35, 24)');

    const status = await page.evaluate(async () => {
      const url = OC.generateUrl('/apps/sakuraalbum/api/v1/sync/status') + '?limit=2&sampleLimit=2';
      const response = await fetch(url, {
        headers: {
          requesttoken: OC.requestToken || '',
        },
      });

      return {
        ok: response.ok,
        status: response.status,
        body: await response.json().catch(() => null),
      };
    });

    expect(status.status).toBe(200);
    expect(status.ok).toBe(true);
    expect(status.body).toHaveProperty('queue');
    expect(status.body.queue).toHaveProperty('mode');
    expect(status.body).toHaveProperty('effectiveSettings');
  });

  test('albentest can open the folder picker without changing settings', async ({ page }) => {
    await loginNextcloud(page);
    await page.goto('/settings/user/sakuraalbum', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('#sakuraalbum-personal-settings')).toBeVisible({ timeout: 30000 });
    await page.getByRole('button', { name: 'Quellordner hinzufuegen' }).click();

    await expect(page.locator('.sakuraalbum-folder-browser')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.sakuraalbum-folder-list')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Diesen Ordner verwenden' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Schliessen' })).toBeVisible();
  });

  test('albentest settings stay readable on a narrow mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await loginNextcloud(page);
    await page.goto('/settings/user/sakuraalbum', { waitUntil: 'domcontentloaded' });

    const appRoot = page.locator('#sakuraalbum-personal-settings');
    await expect(appRoot).toBeVisible({ timeout: 30000 });
    await expect(page.locator('.sakuraalbum-rule-card-list').first()).toBeVisible();
    await expect(page.getByText('Visuelle Einrichtung')).toBeVisible();
    await expect(page.getByText('1 App')).toBeVisible();
    await expect(page.getByText('2 Quellen')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Speichern und Hintergrundlauf vormerken' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Veraltete pruefen' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Konto-Reset pruefen' })).toBeVisible();

    const overflow = await page.evaluate(() => {
      const root = document.querySelector('#sakuraalbum-personal-settings');
      if (!root) {
        return [];
      }
      const viewportWidth = document.documentElement.clientWidth;
      return Array.from(root.querySelectorAll('button, input, select, textarea, .sakuraalbum-rule-card, .sakuraalbum-first-run'))
        .map((element) => {
          const rect = element.getBoundingClientRect();
          return {
            tag: element.tagName,
            text: element.textContent || element.getAttribute('title') || '',
            left: rect.left,
            right: rect.right,
            width: rect.width,
            viewportWidth,
          };
        })
        .filter((entry) => entry.width > 0 && (entry.left < -1 || entry.right > viewportWidth + 1));
    });
    expect(overflow).toEqual([]);
  });
});
