const fs = require('node:fs');
const path = require('node:path');
const { test, expect } = require('@playwright/test');

test('SakuraAlbum admin cockpit remains readable and exposes live regions', async ({ page }) => {
  const css = fs.readFileSync(path.join(process.cwd(), 'css/settings.css'), 'utf8');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.setContent(`
    <!doctype html>
    <html>
      <head>
        <style>
          :root {
            --color-main-text: #222;
            --color-text-maxcontrast: #555;
            --color-main-background: #fff;
            --color-background-darker: #ececec;
            --color-background-dark: #f5f5f5;
            --color-border: #c8c8c8;
            --color-primary-element: #00679e;
            --color-primary-element-text: #fff;
            --color-success: #0f7b3f;
            --color-error: #b42318;
          }
          ${css}
        </style>
      </head>
      <body>
        <main class="sakuraalbum-settings">
          <section class="sakuraalbum-admin-guide" aria-label="SakuraAlbum Admin-Ueberblick">
            <div class="sakuraalbum-panel-head">
              <div>
                <h3>Admin-Cockpit</h3>
                <p>Freigabe, Lastgrenzen, Automatik und Diagnose.</p>
              </div>
            </div>
            <div class="sakuraalbum-status-lanes">
              <div><span>1 Freigabe</span><strong>Global aktiv</strong><small>Alle Benutzer</small></div>
              <div><span>2 Lastschutz</span><strong>Quoten aktiv</strong><small>50000 Dateien pro Job</small></div>
              <div><span>3 Automatik</span><strong>Datei-Events</strong><small>10 Benutzer, 120 Sekunden pro Lauf</small></div>
              <div><span>4 Diagnose</span><strong>Debug aktiv</strong><small>14 Tage Aufbewahrung</small></div>
            </div>
          </section>
          <div id="ska-admin-status" class="sakuraalbum-status" role="status" aria-live="polite" aria-atomic="true">Auto-Status geladen.</div>
          <div id="ska-log-output" role="region" aria-label="SakuraAlbum Admin-Ausgabe" tabindex="-1">
            <div class="sakuraalbum-admin-guide">
              <div class="sakuraalbum-status-lanes">
                <div><span>Queue</span><strong>0 wartend</strong><small>0 in Arbeit, 0 fehlerhaft</small></div>
                <div><span>Cron</span><strong>Kein Lauf vorgemerkt</strong><small>Hintergrundjob-Modus cron ist aktiv</small></div>
              </div>
            </div>
          </div>
          <div class="sakuraalbum-actions">
            <button type="button">Auto-Status laden</button>
            <button class="primary" type="button">Speichern</button>
          </div>
        </main>
      </body>
    </html>
  `);

  await expect(page.getByText('Admin-Cockpit')).toBeVisible();
  await expect(page.locator('#ska-admin-status')).toHaveAttribute('role', 'status');
  await expect(page.locator('#ska-admin-status')).toHaveAttribute('aria-live', 'polite');
  await expect(page.locator('#ska-log-output')).toHaveAttribute('role', 'region');

  const overflow = await page.evaluate(() => {
    const viewportWidth = document.documentElement.clientWidth;
    return Array.from(document.querySelectorAll('button, .sakuraalbum-admin-guide, .sakuraalbum-status-lanes div'))
      .map((element) => {
        const rect = element.getBoundingClientRect();
        return { text: element.textContent || '', left: rect.left, right: rect.right, width: rect.width, viewportWidth };
      })
      .filter((entry) => entry.width > 0 && (entry.left < -1 || entry.right > viewportWidth + 1));
  });
  expect(overflow).toEqual([]);
});
