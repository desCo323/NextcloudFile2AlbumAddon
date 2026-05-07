const fs = require('node:fs');
const path = require('node:path');
const { test, expect } = require('@playwright/test');

test('SakuraAlbum button states keep readable contrast', async ({ page }) => {
  const cssPath = path.join(__dirname, '..', '..', 'css', 'settings.css');
  const styles = fs.readFileSync(cssPath, 'utf8');

  await page.setContent(`
    <!doctype html>
    <html>
      <head>
        <style>${styles}</style>
      </head>
      <body>
        <main class="sakuraalbum-settings">
          <button id="normal" type="button">Alle verwalteten pruefen</button>
          <button id="danger" class="sakuraalbum-button-danger" type="button">Konto-Reset pruefen</button>
          <button id="disabled" type="button" disabled>Gesperrt</button>
        </main>
      </body>
    </html>
  `);

  await expect(page.locator('#normal')).toBeVisible();
  await expect(page.locator('#danger')).toHaveCSS('color', 'rgb(255, 255, 255)');
  await expect(page.locator('#danger')).toHaveCSS('background-color', 'rgb(180, 35, 24)');
  await page.locator('#danger').hover();
  await expect(page.locator('#danger')).toHaveCSS('color', 'rgb(255, 255, 255)');
  await expect(page.locator('#danger')).toHaveCSS('background-color', 'rgb(146, 27, 19)');
  await expect(page.locator('#disabled')).not.toHaveCSS('color', 'rgba(0, 0, 0, 0)');
});
